<?php

namespace App\Http\Controllers\V1\Admin\General;

use App\Http\Controllers\Controller;
use App\Models\DeliveryNote;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Services\SerialNumberFormatter;
use Illuminate\Http\Request;

/**
 * Devuelve el siguiente número sugerido para un tipo de documento.
 *
 * Onfactu — numeración diferida:
 * No basta con devolver MAX(sequence_number) + 1, porque ese número puede
 * estar ocupado por un documento manual puro (sequence_number NULL pero
 * invoice_number coincidente con el candidato). Si lo devolvemos tal cual,
 * el usuario lo verá como sugerencia pero al aprobar colisionará.
 *
 * Aquí, si el candidato ya existe en la BD, avanzamos hasta encontrar uno
 * libre. Esto hace que la sugerencia que ve el usuario sea siempre válida.
 */
class NextNumberController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice, Estimate $estimate, Payment $payment, ProformaInvoice $proformaInvoice, DeliveryNote $deliveryNote)
    {
        $key = $request->key;
        $nextNumber = null;
        $companyId = $request->header('company');

        $serial = (new SerialNumberFormatter)
            ->setCompany($companyId)
            ->setCustomer($request->userId);

        try {
            switch ($key) {
                case 'invoice':
                    $nextNumber = $this->nextFreeNumberFor(
                        $serial,
                        $invoice,
                        $request->model_id,
                        Invoice::class,
                        'invoice_number',
                        $companyId
                    );
                    break;

                case 'estimate':
                    $nextNumber = $this->nextFreeNumberFor(
                        $serial,
                        $estimate,
                        $request->model_id,
                        Estimate::class,
                        'estimate_number',
                        $companyId
                    );
                    break;

                case 'payment':
                    // Payment no tiene numeración diferida, comportamiento original
                    $nextNumber = $serial->setModel($payment)
                        ->setModelObject($request->model_id)
                        ->getNextNumber();
                    break;

                case 'proforma_invoice':
                case 'proformainvoice':
                    $nextNumber = $this->nextFreeNumberFor(
                        $serial,
                        $proformaInvoice,
                        $request->model_id,
                        ProformaInvoice::class,
                        'proforma_invoice_number',
                        $companyId
                    );
                    break;

                case 'delivery_note':
                case 'deliverynote':
                    $nextNumber = $this->nextFreeNumberFor(
                        $serial,
                        $deliveryNote,
                        $request->model_id,
                        DeliveryNote::class,
                        'delivery_note_number',
                        $companyId
                    );
                    break;

                default:
                    return;
            }
        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'nextNumber' => $nextNumber,
        ]);
    }

    /**
     * Obtiene el siguiente número libre para un tipo de documento.
     *
     * 1. Usa SerialNumberFormatter para calcular el candidato estándar
     *    (MAX(sequence_number) + 1 formateado).
     * 2. Si ese candidato ya existe en la tabla (por manual puro, dato
     *    histórico, etc.), avanza al siguiente sequence_number y vuelve a
     *    formatear, hasta encontrar uno libre.
     *
     * @param  \App\Services\SerialNumberFormatter  $serial
     * @param  mixed  $modelInstance  Instancia vacía para setModel
     * @param  mixed  $modelId
     * @param  class-string  $modelClass  Para hacer la query de existencia
     * @param  string  $numberField  Nombre de la columna (invoice_number, etc.)
     * @param  mixed  $companyId
     */
    protected function nextFreeNumberFor(
        SerialNumberFormatter $serial,
        $modelInstance,
        $modelId,
        string $modelClass,
        string $numberField,
        $companyId
    ): string {
        $serial->setModel($modelInstance)
            ->setModelObject($modelId)
            ->setNextNumbers();

        $seq = $serial->nextSequenceNumber ?: 1;
        $candidate = $serial->getNextNumber();

        // Comprobar si el candidato ya existe. Si sí, avanzar.
        $maxAttempts = 1000;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $exists = $modelClass::where('company_id', $companyId)
                ->where($numberField, $candidate)
                ->exists();

            if (! $exists) {
                return $candidate;
            }

            // Ocupado: avanzar al siguiente
            $seq++;
            $serial->nextSequenceNumber = $seq;
            // Regeneramos el número con el nuevo sequence
            $candidate = $serial->getNextNumber();
        }

        // Fallback: devolver lo que haya aunque colisione (no debería llegar nunca aquí)
        return $candidate;
    }
}
