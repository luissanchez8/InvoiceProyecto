<?php

/**
 * Controller ProformaInvoicePdfController — Genera/descarga PDF de factura proforma
 *
 * Accesible via URL pública /proforma-invoices/pdf/{unique_hash}.
 * Si viene ?preview, devuelve HTML para previsualización.
 * Si no, devuelve el PDF generado o lo genera al vuelo.
 */

namespace App\Http\Controllers\V1\PDF;

use App\Http\Controllers\Controller;
use App\Models\ProformaInvoice;
use Illuminate\Http\Request;

class ProformaInvoicePdfController extends Controller
{
    public function __invoke(Request $request, ProformaInvoice $proformaInvoice)
    {
        if ($request->has('preview')) {
            return $proformaInvoice->getPDFData();
        }

        return $proformaInvoice->getGeneratedPDFOrStream('proforma_invoice');
    }
}
