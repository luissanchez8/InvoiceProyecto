<?php

namespace App\Http\Controllers\V1\Admin\Invoice;

use App\Exceptions\AprobacionFacturaException;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Jobs\GenerateInvoicePdfJob;
use App\Models\Invoice;
use App\Services\AprobarFactura;
use App\Services\VerifactuPublicador;
use Illuminate\Http\Request;

/**
 * Onfactu v.1.13.0 — Aprobar una factura.
 *
 * POST /api/v1/invoices/{invoice}/approve   { usar_fecha_hoy?: bool }
 *
 * Antes solo funcionaba con VeriFactu activado. Ahora es el único paso para
 * salir del borrador, para todos: asigna el número y bloquea la factura
 * (AprobarFactura). Con VeriFactu, además, la envía (VerifactuPublicador).
 *
 * Si no se puede aprobar, responde 422 con un código (fecha_anterior,
 * fecha_posterior, mes_cerrado, incompleta, ya_aprobada), el mensaje y si
 * aprobar con la fecha de hoy lo resolvería (puede_usar_hoy).
 */
class ApproveInvoiceController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice)
    {
        $this->authorize('send invoice', $invoice);

        try {
            $invoice = AprobarFactura::aprobar($invoice, $request->boolean('usar_fecha_hoy'));
        } catch (AprobacionFacturaException $e) {
            return $e->respuesta();
        }

        $verifactu = VerifactuPublicador::siEstaActivo($invoice);

        // El PDF del borrador no llevaba número: se rehace.
        GenerateInvoicePdfJob::dispatch($invoice, true);

        return response()->json([
            'success'   => true,
            'verifactu' => $verifactu,
            'data'      => new InvoiceResource($invoice->fresh()),
        ]);
    }
}
