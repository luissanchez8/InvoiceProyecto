<?php

/**
 * Controller ChangeProformaInvoiceStatusController
 *
 * Cambia el estado de una factura proforma.
 * Estados válidos: SENT, ACCEPTED, REJECTED
 */

namespace App\Http\Controllers\V1\Admin\ProformaInvoice;

use App\Http\Controllers\Controller;
use App\Models\ProformaInvoice;
use Illuminate\Http\Request;

class ChangeProformaInvoiceStatusController extends Controller
{
    public function __invoke(Request $request, ProformaInvoice $proformaInvoice)
    {
        $this->authorize('update', $proformaInvoice);

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
