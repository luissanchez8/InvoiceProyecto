<?php

namespace App\Http\Controllers\V1\Admin\Invoice;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

/**
 * Onfactu: devuelve información de consistencia para el formulario de creación
 * de facturas. El frontend lo usa para avisar al usuario si:
 *
 *   - El invoice_number escrito deja un hueco respecto al siguiente propuesto.
 *   - La fecha del documento es anterior a la fecha de la ÚLTIMA factura
 *     numerada CREADA (la de mayor id, no la de mayor sequence_number).
 *     Si coincide la fecha no se avisa; solo si es estrictamente anterior.
 *
 * GET /api/v1/invoices/next-number-info
 *   → {
 *       last_number:            "FAC-000005",  // última creada
 *       last_sequence:          5,
 *       last_numbered_date:     "2026-04-21",  // fecha de la última creada
 *       highest_sequence:       99,            // mayor sequence_number usado
 *       next_expected_sequence: 6              // primer hueco libre desde 1
 *     }
 */
class NextNumberController extends Controller
{
    public function __invoke(Request $request)
    {
        $companyId = $request->header('company');

        // La "última" factura es la más recientemente creada (mayor id), no la
        // de mayor sequence_number. Ejemplo: si el usuario creó FAC-000004,
        // luego FAC-000099 y por último FAC-000003, la "última" es la tercera.
        $last = Invoice::where('company_id', $companyId)
            ->whereNotNull('invoice_number')
            ->orderByDesc('id')
            ->first();

        $lastDate = null;
        if ($last && $last->invoice_date) {
            $raw = $last->invoice_date;
            if (is_string($raw)) {
                $lastDate = substr($raw, 0, 10);
            } elseif (method_exists($raw, 'toDateString')) {
                $lastDate = $raw->toDateString();
            } else {
                $lastDate = (string) $raw;
            }
        }

        // Onfactu: siguiente sequence libre (primer hueco desde 1).
        $used = Invoice::where('company_id', $companyId)
            ->whereNotNull('sequence_number')
            ->orderBy('sequence_number', 'asc')
            ->pluck('sequence_number')
            ->map(fn ($n) => (int) $n)
            ->unique()
            ->values()
            ->all();

        $next = 1;
        foreach ($used as $n) {
            if ($n === $next) {
                $next++;
            } elseif ($n > $next) {
                break;
            }
        }

        $highestSequence = empty($used) ? null : end($used);

        return response()->json([
            'last_number'            => $last?->invoice_number,
            'last_sequence'          => $last?->sequence_number,
            'last_numbered_date'     => $lastDate,
            'highest_sequence'       => $highestSequence,
            'next_expected_sequence' => $next,
        ]);
    }
}
