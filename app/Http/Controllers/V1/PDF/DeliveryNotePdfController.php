<?php

/**
 * Controller DeliveryNotePdfController — Genera/descarga PDF de albarán
 *
 * Accesible via URL pública /delivery-notes/pdf/{unique_hash}.
 * Si viene ?preview, devuelve HTML para previsualización.
 * Si no, devuelve el PDF generado o lo genera al vuelo.
 */

namespace App\Http\Controllers\V1\PDF;

use App\Http\Controllers\Controller;
use App\Models\DeliveryNote;
use Illuminate\Http\Request;

class DeliveryNotePdfController extends Controller
{
    public function __invoke(Request $request, DeliveryNote $deliveryNote)
    {
        if ($request->has('preview')) {
            return $deliveryNote->getPDFData();
        }

        return $deliveryNote->getGeneratedPDFOrStream('delivery_note');
    }
}
