<?php

namespace App\Http\Controllers\V1\Admin\Estimate;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChangeEstimateStatusController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * Onfactu — numeración diferida:
     * Si el cambio es DRAFT → SENT (o cualquier transición que saque al
     * presupuesto del estado DRAFT), se asigna el número automáticamente
     * antes de guardar el nuevo status. Si hay colisión, devuelve 409 con
     * detalles del conflicto.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request, Estimate $estimate)
    {
        $this->authorize('send estimate', $estimate);

        $newStatus = $request->input('status');

        // ── Numeración diferida (Onfactu) ────────────────────────────────
        // Si el presupuesto está en borrador y pasa a un estado "emitido"
        // (SENT, VIEWED, ACCEPTED), le asignamos número antes de cambiar.
        $estadosEmitidos = [
            Estimate::STATUS_SENT,
            Estimate::STATUS_VIEWED,
            Estimate::STATUS_ACCEPTED,
        ];

        $necesitaNumero = $estimate->status === Estimate::STATUS_DRAFT
            && in_array($newStatus, $estadosEmitidos, true);

        if ($necesitaNumero) {
            try {
                $estimate = $estimate->assignNumber();
            } catch (\App\Exceptions\NumberCollisionException $e) {
                Log::warning('Colisión al asignar número de presupuesto', [
                    'estimate_id' => $estimate->id,
                    'details' => $e->getDetails(),
                ]);

                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'error_code' => 'number_collision',
                    'details' => $e->getDetails(),
                ], 409);
            } catch (\Throwable $e) {
                Log::error('Error al asignar número de presupuesto', [
                    'estimate_id' => $estimate->id,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], 422);
            }
        }
        // ────────────────────────────────────────────────────────────────

        $estimate->update($request->only('status'));

        return response()->json([
            'success' => true,
            'data' => $estimate->fresh(),
        ]);
    }
}
