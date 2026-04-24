<?php

namespace App\Http\Controllers\V1\Admin\Invoice;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

/**
 * Onfactu: devuelve información de consistencia para el formulario de creación
 * de facturas. El frontend lo usa para avisar al usuario si:
 *
 *   - El invoice_number escrito deja un hueco respecto al siguiente de la serie.
 *   - La fecha escrita es anterior a la de la última factura con número.
 *
 * GET /api/v1/invoices/next-number-info
 *   → {
 *       next_expected_number: "FAC-000002",  // el que tocaría ahora mismo
 *       last_number: "FAC-000001",           // el último asignado
 *       last_numbered_date: "2026-04-20"     // fecha de la última factura con número
 *     }
 */
class NextNumberController extends Controller
{
    public function __invoke(Request $request)
    {
        $companyId = $request->header('company');

        $last = Invoice::where('company_id', $companyId)
            ->whereNotNull('invoice_number')
            ->orderByDesc('sequence_number')
            ->first();

        return response()->json([
            'last_number'        => $last?->invoice_number,
            'last_sequence'      => $last?->sequence_number,
            'last_numbered_date' => $last?->invoice_date
                ? $last->invoice_date->toDateString()
                : null,
        ]);
    }
}
