<?php

/**
 * Controller ChangeProformaInvoiceStatusController
 *
 * Cambia el estado de una factura proforma.
 * Estados válidos: SENT, ACCEPTED, REJECTED
 *
 * Onfactu — numeración diferida:
 * Al pasar de DRAFT a cualquier estado emitido (SENT/VIEWED/ACCEPTED), se
 * asigna el proforma_invoice_number antes de guardar. En caso de colisión,
 * devuelve 409 con detalles del documento conflictivo.
 */

namespace App\Http\Controllers\V1\Admin\ProformaInvoice;

use App\Http\Controllers\Controller;
use App\Models\ProformaInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChangeProformaInvoiceStatusController extends Controller
{
    public function __invoke(Request $request, ProformaInvoice $proformaInvoice)
    {
        $this->authorize('update', $proformaInvoice);

        $newStatus = $request->input('status');

        // ── Numeración diferida (Onfactu) ────────────────────────────────
        $estadosEmitidos = [
            ProformaInvoice::STATUS_SENT,
            ProformaInvoice::STATUS_VIEWED,
            ProformaInvoice::STATUS_ACCEPTED,
        ];

        $necesitaNumero = $proformaInvoice->status === ProformaInvoice::STATUS_DRAFT
            && in_array($newStatus, $estadosEmitidos, true);

        if ($necesitaNumero) {
            try {
                $proformaInvoice = $proformaInvoice->assignNumber();
            } catch (\App\Exceptions\NumberCollisionException $e) {
                Log::warning('Colisión al asignar número de proforma', [
                    'proforma_invoice_id' => $proformaInvoice->id,
                    'details' => $e->getDetails(),
                ]);

                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'error_code' => 'number_collision',
                    'details' => $e->getDetails(),
                ], 409);
            } catch (\Throwable $e) {
                Log::error('Error al asignar número de proforma', [
                    'proforma_invoice_id' => $proformaInvoice->id,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], 422);
            }
        }
        // ────────────────────────────────────────────────────────────────

        // Actualizar estado según el request
        if ($request->status == ProformaInvoice::STATUS_SENT) {
            $proformaInvoice->status = ProformaInvoice::STATUS_SENT;
            $proformaInvoice->sent = true;
        } elseif ($request->status == ProformaInvoice::STATUS_ACCEPTED) {
            $proformaInvoice->status = ProformaInvoice::STATUS_ACCEPTED;
        } elseif ($request->status == ProformaInvoice::STATUS_REJECTED) {
            $proformaInvoice->status = ProformaInvoice::STATUS_REJECTED;
        }

        $proformaInvoice->save();

        return response()->json([
            'data' => $proformaInvoice,
            'success' => true,
        ]);
    }
}
