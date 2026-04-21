<?php

namespace App\Http\Controllers\V1\Admin\Estimate;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendEstimatesRequest;
use App\Models\Estimate;
use Illuminate\Support\Facades\Log;

class SendEstimateController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * Onfactu — numeración diferida:
     * Antes de enviar el presupuesto por email, nos aseguramos de que tenga
     * un número asignado (enviar sin número no tiene sentido, el cliente
     * no puede referenciarlo).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(SendEstimatesRequest $request, Estimate $estimate)
    {
        $this->authorize('send estimate', $estimate);

        // Si el presupuesto no tiene número aún, lo asignamos ahora.
        if (empty($estimate->estimate_number)) {
            try {
                $estimate = $estimate->assignNumber();
            } catch (\App\Exceptions\NumberCollisionException $e) {
                Log::warning('Colisión al asignar número de presupuesto al enviar', [
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
                Log::error('Error al asignar número de presupuesto al enviar', [
                    'estimate_id' => $estimate->id,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], 422);
            }
        }

        $response = $estimate->send($request->all());

        return response()->json($response);
    }
}
