<?php

namespace App\Http\Controllers\V1\Admin\DeliveryNote;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Services\Facturacion\ConvertirEnFactura;
use App\Services\Facturacion\NoSePuedeFacturar;
use Illuminate\Http\Request;

/**
 * Convierte un albarán en factura.
 *
 * Onfactu v.1.17.0: todo lo hace ConvertirEnFactura. Para varios albaranes en
 * una factura, FacturacionController::convertir.
 */
class ConvertDeliveryNoteToInvoiceController extends Controller
{
    public function __invoke(Request $request, DeliveryNote $deliveryNote)
    {
        $this->authorize('create', Invoice::class);

        try {
            $invoice = ConvertirEnFactura::desde([$deliveryNote]);
        } catch (NoSePuedeFacturar $e) {
            return $e->respuesta();
        }

        return new InvoiceResource($invoice);
    }
}
