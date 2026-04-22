<?php

/**
 * Controller ConvertDeliveryNoteToInvoiceController
 *
 * Onfactu — Convierte un albarán en una factura.
 * La factura resultante nace como BORRADOR SIN NÚMERO. El número se
 * asignará cuando el usuario apruebe la factura (VeriFactu).
 *
 * Copia ítems, impuestos y datos relevantes del albarán. El albarán
 * permanece en su estado actual (no lo modifica).
 */

namespace App\Http\Controllers\V1\Admin\DeliveryNote;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\CompanySetting;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Vinkla\Hashids\Facades\Hashids;

class ConvertDeliveryNoteToInvoiceController extends Controller
{
    public function __invoke(Request $request, DeliveryNote $deliveryNote)
    {
        $this->authorize('create', Invoice::class);

        $deliveryNote->load(['items', 'items.taxes', 'customer', 'taxes']);

        $invoice_date = Carbon::now();
        $due_date = null;

        $dueDateEnabled = CompanySetting::getSetting(
            'invoice_set_due_date_automatically',
            $request->header('company')
        );

        if ($dueDateEnabled === 'YES') {
            $dueDateDays = intval(CompanySetting::getSetting(
                'invoice_due_date_days',
                $request->header('company')
            ));
            $due_date = Carbon::now()->addDays($dueDateDays)->format('Y-m-d');
        }

        $exchange_rate = $deliveryNote->exchange_rate;

        // Numeración diferida: factura nace SIN número, se asigna al aprobar
        $invoice = Invoice::create([
            'creator_id' => Auth::id(),
            'invoice_date' => $invoice_date->format('Y-m-d'),
            'due_date' => $due_date,
            'invoice_number' => null,
            'sequence_number' => null,
            'customer_sequence_number' => null,
            'reference_number' => $deliveryNote->reference_number,
            'customer_id' => $deliveryNote->customer_id,
            'company_id' => $request->header('company'),
            'template_name' => 'invoice4',
            'status' => Invoice::STATUS_DRAFT,
            'paid_status' => Invoice::STATUS_UNPAID,
            'sub_total' => $deliveryNote->sub_total,
            'discount' => $deliveryNote->discount,
            'discount_type' => $deliveryNote->discount_type,
            'discount_val' => $deliveryNote->discount_val,
            'total' => $deliveryNote->total,
            'due_amount' => $deliveryNote->total,
            'tax_per_item' => $deliveryNote->tax_per_item,
            'discount_per_item' => $deliveryNote->discount_per_item,
            'tax' => $deliveryNote->tax,
            'notes' => $deliveryNote->notes,
            'exchange_rate' => $exchange_rate,
            'base_discount_val' => $deliveryNote->discount_val * $exchange_rate,
            'base_sub_total' => $deliveryNote->sub_total * $exchange_rate,
            'base_total' => $deliveryNote->total * $exchange_rate,
            'base_tax' => $deliveryNote->tax * $exchange_rate,
            'currency_id' => $deliveryNote->currency_id,
            'sales_tax_type' => $deliveryNote->sales_tax_type,
            'sales_tax_address_type' => $deliveryNote->sales_tax_address_type,
        ]);

        $invoice->unique_hash = Hashids::connection(Invoice::class)->encode($invoice->id);
        $invoice->save();

        // Copiar items con sus impuestos
        foreach ($deliveryNote->items->toArray() as $item) {
            $item['company_id'] = $request->header('company');
            $item['exchange_rate'] = $exchange_rate;
            $item['base_price'] = $item['price'] * $exchange_rate;
            $item['base_discount_val'] = $item['discount_val'] * $exchange_rate;
            $item['base_tax'] = $item['tax'] * $exchange_rate;
            $item['base_total'] = $item['total'] * $exchange_rate;

            // Limpiar id y delivery_note_id para no chocar
            unset($item['id']);
            unset($item['delivery_note_id']);

            $newItem = $invoice->items()->create($item);

            if (! empty($item['taxes'])) {
                foreach ($item['taxes'] as $tax) {
                    $tax['company_id'] = $request->header('company');
                    unset($tax['id']);
                    if ($tax['amount']) {
                        $newItem->taxes()->create($tax);
                    }
                }
            }
        }

        // Copiar impuestos a nivel factura
        if ($deliveryNote->taxes) {
            foreach ($deliveryNote->taxes->toArray() as $tax) {
                $tax['company_id'] = $request->header('company');
                $tax['exchange_rate'] = $exchange_rate;
                $tax['base_amount'] = $tax['amount'] * $exchange_rate;
                $tax['currency_id'] = $deliveryNote->currency_id;
                unset($tax['delivery_note_id']);
                unset($tax['id']);

                $invoice->taxes()->create($tax);
            }
        }

        return new InvoiceResource($invoice->fresh());
    }
}
