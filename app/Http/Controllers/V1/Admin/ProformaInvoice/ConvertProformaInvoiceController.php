<?php

namespace App\Http\Controllers\V1\Admin\ProformaInvoice;

use App\Http\Controllers\Controller;
use App\Models\ProformaInvoice;
use App\Services\Facturacion\ConvertirEnFactura;
use App\Services\Facturacion\NoSePuedeFacturar;
use Illuminate\Http\Request;

/**
 * Convierte una proforma en factura.
 *
 * Onfactu v.1.17.0: todo lo hace ConvertirEnFactura, el mismo camino que
 * presupuestos y albaranes. La proforma queda aceptada, con su factura en
 * converted_invoice_id y en documentos_facturados.
 */
class ConvertProformaInvoiceController extends Controller
{
    public function __invoke(Request $request, ProformaInvoice $proformaInvoice)
    {
        $this->authorize('create', ProformaInvoice::class);

        try {
            $invoice = ConvertirEnFactura::desde([$proformaInvoice]);
        } catch (NoSePuedeFacturar $e) {
            return $e->respuesta();
        }

        return response()->json([
            'data' => $invoice,
            'success' => true,
        ]);
    }
}
