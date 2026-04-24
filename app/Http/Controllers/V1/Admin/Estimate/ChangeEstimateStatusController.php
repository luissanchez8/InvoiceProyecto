<?php

namespace App\Http\Controllers\V1\Admin\Estimate;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use Illuminate\Http\Request;

class ChangeEstimateStatusController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request, Estimate $estimate)
    {
        $this->authorize('send estimate', $estimate);

        $newStatus = $request->input('status');

        // Onfactu: si el presupuesto pasa a ACEPTADO y aún no tiene número
        // (era borrador), asignarle el siguiente número de serie. Enviar /
        // rechazar NO asignan número — pueden tramitarse sobre un borrador.
        if ($newStatus === Estimate::STATUS_ACCEPTED && empty($estimate->estimate_number)) {
            $estimate->assignNumber();
        }

        $estimate->update($request->only('status'));

        return response()->json([
            'success' => true,
        ]);
    }
}
