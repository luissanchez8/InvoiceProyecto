<?php

namespace App\Http\Controllers\V1\Admin\Invoice;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\AprobarFactura;
use Illuminate\Http\Request;

/**
 * Onfactu v.1.13.3 — Qué pasaría al aprobar una factura, sin aprobarla.
 *
 * GET /api/v1/invoices/{invoice}/approve-preview
 *   → { numero, error: null | { codigo, mensaje, puede_usar_hoy }, numero_hoy }
 *
 * El diálogo de aprobar lo pide al abrirse, para enseñar el número exacto que
 * recibirá la factura y avisar antes de pulsar si la fecha no encaja.
 */
class ApprovalPreviewController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        return response()->json(AprobarFactura::previsualizar($invoice));
    }
}
