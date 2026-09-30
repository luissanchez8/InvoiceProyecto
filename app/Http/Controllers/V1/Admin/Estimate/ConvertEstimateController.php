<?php

namespace App\Http\Controllers\V1\Admin\Estimate;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Services\Facturacion\ConvertirEnFactura;
use App\Services\Facturacion\NoSePuedeFacturar;
use Illuminate\Http\Request;

/**
 * Convierte un presupuesto en factura.
 *
 * Onfactu v.1.17.0: todo lo hace ConvertirEnFactura, el mismo camino que
 * proformas y albaranes. La factura nace en borrador, resta los anticipos y
 * queda enlazada al presupuesto, que pasa a aceptado y facturado.
 */
class ConvertEstimateController extends Controller
{
    public function __invoke(Request $request, Estimate $estimate)
    {
        $this->authorize('create', Invoice::class);

        try {
            $invoice = ConvertirEnFactura::desde([$estimate]);
        } catch (NoSePuedeFacturar $e) {
            return $e->respuesta();
        }

        return new InvoiceResource($invoice);
    }
}
