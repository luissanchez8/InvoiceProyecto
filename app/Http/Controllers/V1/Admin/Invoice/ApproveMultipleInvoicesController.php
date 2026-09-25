<?php

namespace App\Http\Controllers\V1\Admin\Invoice;

use App\Exceptions\AprobacionFacturaException;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateInvoicePdfJob;
use App\Models\Invoice;
use App\Services\AprobarFactura;
use App\Services\VerifactuPublicador;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Onfactu v.1.13.0 — Aprobar varias facturas a la vez desde la lista.
 *
 * POST /api/v1/invoices/approve-multiple   { ids: [..] }
 *
 * Se aprueban por orden de fecha, para que los números sigan ese orden. Una
 * que no se puede aprobar no frena a las demás: se devuelve en "errores" con
 * su motivo. Las que no son borradores se ignoran.
 */
class ApproveMultipleInvoicesController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'ids'   => ['required', 'array', 'max:200'],
            'ids.*' => ['integer'],
        ]);

        $facturas = Invoice::where('company_id', $request->header('company'))
            ->whereIn('id', $request->ids)
            ->where('status', Invoice::STATUS_DRAFT)
            ->with('customer:id,name')
            ->orderBy('invoice_date')->orderBy('id')
            ->get();

        $aprobadas = [];
        $errores = [];

        foreach ($facturas as $factura) {
            $this->authorize('send invoice', $factura);

            try {
                $factura = AprobarFactura::aprobar($factura);
                VerifactuPublicador::siEstaActivo($factura);
                GenerateInvoicePdfJob::dispatch($factura, true);
                $aprobadas[] = $factura->invoice_number;
            } catch (AprobacionFacturaException $e) {
                $errores[] = [
                    'id'      => $factura->id,
                    'cliente' => $factura->customer?->name,
                    'fecha'   => Carbon::parse($factura->invoice_date)->format('d/m/Y'),
                    'mensaje' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success'   => true,
            'aprobadas' => $aprobadas,
            'errores'   => $errores,
            'ignoradas' => count(array_unique($request->ids)) - $facturas->count(),
        ]);
    }
}
