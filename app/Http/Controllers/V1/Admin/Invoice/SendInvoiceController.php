<?php

namespace App\Http\Controllers\V1\Admin\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendInvoiceRequest;
use App\Models\Invoice;

class SendInvoiceController extends Controller
{
    /**
     * Mail a specific invoice to the corresponding customer's email address.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(SendInvoiceRequest $request, Invoice $invoice)
    {
        $this->authorize('send invoice', $invoice);

        // Onfactu v.1.14.3: un borrador no es una factura; se aprueba antes.
        if ($invoice->status === Invoice::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Aprueba la factura antes de enviarla.',
            ], 422);
        }

        $invoice->send($request->all());

        return response()->json([
            'success' => true,
        ]);
    }
}
