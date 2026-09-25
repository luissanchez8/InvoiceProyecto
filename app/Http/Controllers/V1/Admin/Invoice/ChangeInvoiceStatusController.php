<?php

namespace App\Http\Controllers\V1\Admin\Invoice;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

/**
 * Onfactu v.1.13.0 — Marcar una factura como enviada o como cobrada.
 *
 * Ninguna de las dos cambia el estado: una factura solo es Borrador o
 * Aprobada, y se aprueba con ApproveInvoiceController.
 *
 *  - SENT: apunta que se ha enviado (fuera de Onfactu, por ejemplo). Vale
 *    también para un borrador que se manda al cliente para que lo revise.
 *  - PAID: la da por cobrada sin registrar un cobro, como hacía antes
 *    "Marcar como completada". Solo para facturas aprobadas.
 *
 * COMPLETED se acepta como sinónimo de PAID, por las pantallas antiguas.
 */
class ChangeInvoiceStatusController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice)
    {
        $this->authorize('send invoice', $invoice);

        if ($request->status === 'SENT') {
            $invoice->sent = true;
            $invoice->sent_at = $invoice->sent_at ?? now();
            $invoice->save();
        } elseif (in_array($request->status, ['PAID', Invoice::STATUS_COMPLETED], true)) {
            if ($invoice->status !== Invoice::STATUS_APPROVED) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aprueba la factura antes de marcarla como cobrada.',
                ], 422);
            }

            $invoice->paid_status = Invoice::STATUS_PAID;
            $invoice->due_amount = 0;
            $invoice->base_due_amount = 0;
            $invoice->overdue = false;
            $invoice->save();
        }

        return response()->json([
            'success' => true,
        ]);
    }
}
