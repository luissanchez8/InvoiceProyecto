<?php

namespace App\Http\Controllers\V1\Admin\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Rectificative;
use App\Services\SerialNumberFormatter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Onfactu — Crear una factura rectificativa.
 *
 * POST /api/v1/invoices/{invoice}/rectify
 *
 * Reglas de negocio:
 *   - Solo se puede rectificar una factura en estado COMPLETED.
 *   - No se puede rectificar una factura con cobros registrados.
 *   - No se puede rectificar una factura ya rectificada.
 *   - Desde la v.1.18.0 se puede rectificar una rectificativa (la Agencia
 *     Tributaria lo admite): la nueva le da la vuelta y la factura original
 *     vuelve a valer. Siempre entera: no hay rectificación parcial.
 *   - El motivo es obligatorio (v.1.18.0): se guarda en rectificacion_motivo
 *     y se escribe en las notas, que salen en el PDF.
 *   - La rectificativa es un clon EXACTO con el signo cambiado (rectificacion total).
 *   - Nace en COMPLETED + UNPAID, con fecha de HOY y serie propia (REC).
 *   - No es editable ni borrable.
 *
 * Efectos:
 *   - La factura original pasa a PAID con due_amount = 0 (queda saldada
 *     porque la rectificativa la anula).
 *   - Se escribe una nota cruzada en ambos documentos.
 */
class RectifyInvoiceController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice)
    {
        $this->authorize('create', Invoice::class);

        $motivo = trim((string) $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
        ], [
            'motivo.required' => 'Indica el motivo de la rectificación.',
        ])['motivo']);

        // ─── Validaciones ───────────────────────────────────────────────
        if ($invoice->status !== Invoice::STATUS_APPROVED) {
            return $this->error('Solo se pueden rectificar facturas aprobadas.');
        }

        $yaRectificada = Invoice::where('rectifies_invoice_id', $invoice->id)->first();
        if ($yaRectificada) {
            return $this->error(
                "Esta factura ya fue rectificada por {$yaRectificada->invoice_number}."
            );
        }

        // ─── Creacion ───────────────────────────────────────────────────
        $rectificativa = DB::transaction(function () use ($request, $invoice, $motivo) {

            $companyId = $request->header('company') ?: $invoice->company_id;

            // Numeracion propia de la serie REC
            $serial = (new SerialNumberFormatter)
                ->setModel(Rectificative::class)
                ->setCompany($companyId)
                ->setCustomer($invoice->customer_id)
                ->setNextNumbers();

            $numero = $serial->getNextNumber();
            $rate = $invoice->exchange_rate;
            // v.1.18.0: cambia el signo (antes -abs, que dejaba en negativo las
            // líneas que ya lo eran, como la de un anticipo descontado, y no
            // servía para rectificar una rectificativa)
            $neg = fn ($v) => $v === null ? null : -$v;

            $rect = Invoice::create([
                'invoice_date'              => Carbon::now()->format('Y-m-d'),
                'due_date'                  => Carbon::now()->format('Y-m-d'),
                'invoice_number'            => $numero,
                'sequence_number'           => $serial->nextSequenceNumber,
                'customer_sequence_number'  => $serial->nextCustomerSequenceNumber,
                'reference_number'          => $invoice->invoice_number,
                'customer_id'               => $invoice->customer_id,
                'company_id'                => $companyId,
                'template_name'             => $invoice->template_name ?: 'invoice1',
                'status'                    => Invoice::STATUS_APPROVED,
                'approved_at'               => now(),
                'paid_status'               => Invoice::STATUS_UNPAID,
                'rectifies_invoice_id'      => $invoice->id,
                'rectificacion_motivo'      => $motivo,

                'sub_total'         => $neg($invoice->sub_total),
                'total'             => $neg($invoice->total),
                'due_amount'        => $neg($invoice->total),
                'tax'               => $neg($invoice->tax),
                'discount_val'      => $neg($invoice->discount_val),
                'discount'          => $invoice->discount,
                'discount_type'     => $invoice->discount_type,
                'tax_per_item'      => $invoice->tax_per_item,
                'discount_per_item' => $invoice->discount_per_item,

                'exchange_rate'      => $rate,
                'base_sub_total'     => $neg($invoice->sub_total) * $rate,
                'base_total'         => $neg($invoice->total) * $rate,
                'base_due_amount'    => $neg($invoice->total) * $rate,
                'base_tax'           => $neg($invoice->tax) * $rate,
                'base_discount_val'  => $neg($invoice->discount_val) * $rate,

                'currency_id'             => $invoice->currency_id,
                'payment_method_id'       => $invoice->payment_method_id,
                'sales_tax_type'          => $invoice->sales_tax_type,
                'sales_tax_address_type'  => $invoice->sales_tax_address_type,

                'notes' => $this->notaRectificativa($invoice, $motivo),
            ]);

            $rect->unique_hash = Hashids::connection(Invoice::class)->encode($rect->id);
            $rect->save();

            // ─── Lineas, en negativo ───
            $invoice->load('items.taxes');

            foreach ($invoice->items as $item) {
                $data = $item->toArray();
                unset($data['id'], $data['invoice_id'], $data['created_at'], $data['updated_at'], $data['taxes']);

                $data['company_id']        = $companyId;
                $data['quantity']          = $item->quantity;          // cantidad igual
                $data['price']             = $item->price;             // precio unitario igual
                $data['discount_val']      = $neg($item->discount_val);
                $data['tax']               = $neg($item->tax);
                $data['total']             = $neg($item->total);
                $data['exchange_rate']     = $rate;
                $data['base_price']        = $item->price * $rate;
                $data['base_discount_val'] = $neg($item->discount_val) * $rate;
                $data['base_tax']          = $neg($item->tax) * $rate;
                $data['base_total']        = $neg($item->total) * $rate;

                $nuevoItem = $rect->items()->create($data);

                foreach ($item->taxes as $tax) {
                    $t = $tax->toArray();
                    unset($t['id'], $t['invoice_id'], $t['invoice_item_id'], $t['created_at'], $t['updated_at']);
                    $t['company_id'] = $companyId;
                    $t['amount']     = $neg($tax->amount);
                    $t['base_amount'] = $neg($tax->base_amount ?? $tax->amount);

                    if ($t['amount']) {
                        $nuevoItem->taxes()->create($t);
                    }
                }
            }

            // ─── Impuestos a nivel de factura ───
            foreach ($invoice->taxes as $tax) {
                $t = $tax->toArray();
                unset($t['id'], $t['invoice_id'], $t['invoice_item_id'], $t['created_at'], $t['updated_at']);
                $t['company_id']  = $companyId;
                $t['amount']      = $neg($tax->amount);
                $t['base_amount'] = $neg($tax->base_amount ?? $tax->amount);
                $rect->taxes()->create($t);
            }

            // ─── Campos personalizados ───
            if ($invoice->fields()->exists()) {
                $custom = [];
                foreach ($invoice->fields as $f) {
                    $custom[] = ['id' => $f->custom_field_id, 'value' => $f->defaultAnswer];
                }
                $rect->addCustomFields($custom);
            }

            // ─── Actualizar la factura original ───
            // Solo la nota cruzada: al estar COMPLETED ya viene con
            // paid_status = PAID y due_amount = 0 (lo hace
            // ChangeInvoiceStatusController al completarla).
            $invoice->notes = $this->notaOriginal($invoice, $numero);
            $invoice->save();

            return $rect;
        });

        return new InvoiceResource($rectificativa->fresh());
    }

    /**
     * Nota que se escribe EN LA RECTIFICATIVA.
     * Se antepone al texto que tuviera la factura original.
     */
    private function notaRectificativa(Invoice $original, string $motivo): string
    {
        // Se escribe en HTML porque el campo notes usa editor rich text: si va
        // en texto plano queda pegado al parrafo siguiente en el PDF.
        $aviso = '<p><strong>Esta factura rectifica a la factura '
               .e($original->invoice_number)
               .' de fecha '.Carbon::parse($original->invoice_date)->format('d/m/Y')
               .'.</strong></p><p><strong>Motivo:</strong> '.nl2br(e($motivo)).'</p>';

        return trim($aviso.self::sinAvisos((string) $original->notes));
    }

    /**
     * Las notas de la original sin los avisos que escribe este controlador:
     * al rectificar una rectificativa no se arrastran "rectifica a...",
     * "Motivo: ..." ni "ha sido rectificada por..." de la anterior.
     */
    private static function sinAvisos(string $notas): string
    {
        $notas = preg_replace('#<p><strong>Esta factura (rectifica a la factura|ha sido rectificada por).*?</strong></p>#su', '', $notas);
        $notas = preg_replace('#<p><strong>Motivo:</strong>.*?</p>#su', '', $notas);

        return trim($notas);
    }

    /**
     * Nota que se escribe EN LA FACTURA ORIGINAL.
     */
    private function notaOriginal(Invoice $original, string $numeroRect): string
    {
        $aviso = '<p><strong>Esta factura ha sido rectificada por la factura rectificativa '
               .e($numeroRect)
               .' de fecha '.Carbon::now()->format('d/m/Y').'.</strong></p>';

        $notas = (string) $original->notes;

        // Evitar duplicar el aviso si ya estuviera
        if (str_contains($notas, 'ha sido rectificada por')) {
            return $notas;
        }

        return trim($aviso.$notas);
    }

    private function error(string $mensaje)
    {
        return response()->json([
            'error'   => 'cannot_rectify',
            'message' => $mensaje,
            'errors'  => ['rectify' => [$mensaje]],
        ], 422);
    }
}
