<?php

/**
 * Controller ChangeDeliveryNoteStatusController
 *
 * Cambia el estado de un albarán.
 * Estados válidos: SENT, DELIVERED
 *
 * Onfactu — numeración diferida:
 * Al pasar de DRAFT a SENT o DELIVERED, se asigna el delivery_note_number
 * antes de guardar. En caso de colisión, 409 con detalles del conflicto.
 */

namespace App\Http\Controllers\V1\Admin\DeliveryNote;

use App\Http\Controllers\Controller;
use App\Models\DeliveryNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChangeDeliveryNoteStatusController extends Controller
{
    public function __invoke(Request $request, DeliveryNote $deliveryNote)
    {
        $this->authorize('update', $deliveryNote);

        $newStatus = $request->input('status');

        // ── Numeración diferida (Onfactu) ────────────────────────────────
        $estadosEmitidos = [
            DeliveryNote::STATUS_SENT,
            DeliveryNote::STATUS_DELIVERED,
        ];

        $necesitaNumero = $deliveryNote->status === DeliveryNote::STATUS_DRAFT
            && in_array($newStatus, $estadosEmitidos, true);

        if ($necesitaNumero) {
            try {
                $deliveryNote = $deliveryNote->assignNumber();
            } catch (\App\Exceptions\NumberCollisionException $e) {
                Log::warning('Colisión al asignar número de albarán', [
                    'delivery_note_id' => $deliveryNote->id,
                    'details' => $e->getDetails(),
                ]);

                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'error_code' => 'number_collision',
                    'details' => $e->getDetails(),
                ], 409);
            } catch (\Throwable $e) {
                Log::error('Error al asignar número de albarán', [
                    'delivery_note_id' => $deliveryNote->id,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], 422);
            }
        }
        // ────────────────────────────────────────────────────────────────

        if ($request->status == DeliveryNote::STATUS_SENT) {
            $deliveryNote->status = DeliveryNote::STATUS_SENT;
            $deliveryNote->sent = true;
        } elseif ($request->status == DeliveryNote::STATUS_DELIVERED) {
            $deliveryNote->status = DeliveryNote::STATUS_DELIVERED;
        }

        $deliveryNote->save();

        return response()->json([
            'data' => $deliveryNote,
            'success' => true,
        ]);
    }
}
