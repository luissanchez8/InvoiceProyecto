<?php

/**
 * Controller ConvertProformaInvoiceController
 *
 * Convierte una factura proforma en una factura real.
 * Copia todos los datos (ítems, impuestos, campos personalizados)
 * y marca la proforma como ACCEPTED con referencia a la nueva factura.
 */

namespace App\Http\Controllers\V1\Admin\ProformaInvoice;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateInvoicePdfJob;
use App\Models\ProformaInvoice;
use Illuminate\Http\Request;

class ConvertProformaInvoiceController extends Controller
{
    public function __invoke(Request $request, ProformaInvoice $proformaInvoice)
    {
        $this->authorize('create', ProformaInvoice::class);

        // Ejecutar la conversión (definida en el modelo)
        $invoice = $proformaInvoice->convertToInvoice();

        // Generar PDF de la nueva factura
        GenerateInvoicePdfJob::dispatch($invoice);

        return response()->json([
            'data' => $invoice,
            'success' => true,
        ]);
    }
}
