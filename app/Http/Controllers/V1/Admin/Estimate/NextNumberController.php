<?php

namespace App\Http\Controllers\V1\Admin\Estimate;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use App\Models\CompanySetting;
use Illuminate\Http\Request;

/**
 * Onfactu: devuelve información de consistencia para el formulario de creación
 * de presupuestos. Paralelo al de Invoice.
 *
 * GET /api/v1/estimates/next-number-info
 *   → {
 *       last_number, last_sequence, last_numbered_date,
 *       highest_sequence, next_expected_sequence
 *     }
 */
class NextNumberController extends Controller
{
    public function __invoke(Request $request)
    {
        $companyId = $request->header('company');

        // Última creada (mayor id, no mayor sequence).
        $last = Estimate::where('company_id', $companyId)
            ->whereNotNull('estimate_number')
            ->orderByDesc('id')
            ->first();

        $lastDate = null;
        if ($last && $last->estimate_date) {
            $raw = $last->estimate_date;
            if (is_string($raw)) {
                $lastDate = substr($raw, 0, 10);
            } elseif (method_exists($raw, 'toDateString')) {
                $lastDate = $raw->toDateString();
            } else {
                $lastDate = (string) $raw;
            }
        }

        $used = Estimate::where('company_id', $companyId)
            ->whereNotNull('sequence_number')
            ->orderBy('sequence_number', 'asc')
            ->pluck('sequence_number')
            ->map(fn ($n) => (int) $n)
            ->unique()
            ->values()
            ->all();

        // v.1.9.2 — Leer número inicial configurado (default 1)
        $startNumber = (int) (CompanySetting::getSetting('estimate_start_number', $companyId) ?: 1);
        if ($startNumber < 1) {
            $startNumber = 1;
        }

        $next = $startNumber;
        foreach ($used as $n) {
            if ($n === $next) {
                $next++;
            } elseif ($n > $next) {
                break;
            }
        }

        $highestSequence = empty($used) ? null : end($used);

        return response()->json([
            'last_number'            => $last?->estimate_number,
            'last_sequence'          => $last?->sequence_number,
            'last_numbered_date'     => $lastDate,
            'highest_sequence'       => $highestSequence,
            'next_expected_sequence' => $next,
        ]);
    }
}
