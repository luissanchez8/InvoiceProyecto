<?php

/**
 * Controller ChangeDeliveryNoteStatusController
 *
 * Cambia el estado de un albarán.
 * Estados válidos: SENT, DELIVERED
 */

namespace App\Http\Controllers\V1\Admin\DeliveryNote;

use App\Http\Controllers\Controller;
use App\Models\DeliveryNote;
use Illuminate\Http\Request;

class ChangeDeliveryNoteStatusController extends Controller
{
    public function __invoke(Request $request, DeliveryNote $deliveryNote)
    {
        $this->authorize('update', $deliveryNote);

        if ($request->status == DeliveryNote::STATUS_SENT) {
            $deliveryNote->status = DeliveryNote::STATUS_SENT;
            $deliveryNote->sent = true;
        } elseif ($request->status == DeliveryNote::STATUS_DELIVERED) {
            // Onfactu: entregar albarán sin número → asignar antes de guardar.
            if (empty($deliveryNote->delivery_note_number)) {
                $deliveryNote->assignNumber();
                $deliveryNote->refresh();
            }
            $deliveryNote->status = DeliveryNote::STATUS_DELIVERED;
        }

        $deliveryNote->save();

        return response()->json([
            'data' => $deliveryNote,
            'success' => true,
        ]);
    }
}
