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
 * Onfactu — numeración diferida (opción C):
 * No basta con devolver MAX(sequence_number) + 1, porque ese número puede
 * estar ocupado por un documento manual puro (sequence_number NULL pero
 * invoice_number coincidente con el candidato).
 *
 * Si el candidato original (MAX+1 formateado) está ocupado, avanzamos hasta
 * encontrar uno libre. En ese caso devolvemos isSkipped=true, indicando al
 * frontend que el número es un "hueco reservado" y que debe persistirlo
 * al guardar como borrador (no descartarlo con la lógica de "sugerencia sin
 * tocar"). Si el primer candidato estaba libre, isSkipped=false y el
 * frontend puede descartar la sugerencia al guardar borrador (comportamiento
 * estándar que libera números para reaprovechar).
 */
class NextNumberController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice, Estimate $estimate, Payment $payment, ProformaInvoice $proformaInvoice, DeliveryNote $deliveryNote)
    {
        $key = $request->key;
        $result = ['nextNumber' => null, 'isSkipped' => false];
        $companyId = $request->header('company');

        $serial = (new SerialNumberFormatter)
            ->setCompany($companyId)
            ->setCustomer($request->userId);

        try {
            switch ($key) {
                case 'invoice':
                    $result = $this->nextFreeNumberFor(
                        $serial, $invoice, $request->model_id,
                        Invoice::class, 'invoice_number', $companyId
                    );
                    break;

                case 'estimate':
                    $result = $this->nextFreeNumberFor(
                        $serial, $estimate, $request->model_id,
                        Estimate::class, 'estimate_number', $companyId
                    );
                    break;

                case 'payment':
                    // Payment no tiene numeración diferida
                    $result = [
                        'nextNumber' => $serial->setModel($payment)
                            ->setModelObject($request->model_id)
                            ->getNextNumber(),
                        'isSkipped' => false,
                    ];
                    break;

                case 'proforma_invoice':
                case 'proformainvoice':
                    $result = $this->nextFreeNumberFor(
                        $serial, $proformaInvoice, $request->model_id,
                        ProformaInvoice::class, 'proforma_invoice_number', $companyId
                    );
                    break;

                case 'delivery_note':
                case 'deliverynote':
                    $result = $this->nextFreeNumberFor(
                        $serial, $deliveryNote, $request->model_id,
                        DeliveryNote::class, 'delivery_note_number', $companyId
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
            'nextNumber' => $result['nextNumber'],
            'isSkipped' => $result['isSkipped'],
        ]);
    }

    /**
     * Busca el siguiente número libre para un tipo de documento.
     *
     * @return array{nextNumber: string, isSkipped: bool}
     */
    protected function nextFreeNumberFor(
        SerialNumberFormatter $serial,
        $modelInstance,
        $modelId,
        string $modelClass,
        string $numberField,
        $companyId
    ): array {
        $serial->setModel($modelInstance)
            ->setModelObject($modelId)
            ->setNextNumbers();

        $seq = $serial->nextSequenceNumber ?: 1;
        $candidate = $serial->getNextNumber();
        $isSkipped = false;

        $maxAttempts = 1000;
        for ($i = 0; $i < $maxAttempts; $i++) {
            // Excluimos el propio documento si hay model_id (cuando el frontend
            // pide sugerencia desde una pantalla de edit/view para una factura
            // existente). Si no excluyéramos, una factura borrador con número
            // "INV-000040" se contaría como "ocupada" y la sugerencia saltaría
            // a 41 o más, provocando falsos positivos en el aviso amber
            // "estás saltando la numeración".
            $query = $modelClass::where('company_id', $companyId)
                ->where($numberField, $candidate);

            if (! empty($modelId)) {
                $query->where('id', '<>', $modelId);
            }

            $exists = $query->exists();

            if (! $exists) {
                return ['nextNumber' => $candidate, 'isSkipped' => $isSkipped];
            }

            // Ocupado: marcamos como skipped y avanzamos
            $isSkipped = true;
            $seq++;
            $serial->nextSequenceNumber = $seq;
            $candidate = $serial->getNextNumber();
        }

        // Fallback
        return ['nextNumber' => $candidate, 'isSkipped' => $isSkipped];
    }
}

