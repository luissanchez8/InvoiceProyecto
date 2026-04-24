<?php

namespace App\Http\Controllers\V1\Admin\DeliveryNote;

use App\Http\Controllers\Controller;
use App\Models\DeliveryNote;
use Illuminate\Http\Request;

/**
 * Onfactu: info de consistencia para el formulario de albaranes.
 * GET /api/v1/delivery-notes/next-number-info
 */
class NextNumberController extends Controller
{
    public function __invoke(Request $request)
    {
        $companyId = $request->header('company');

        $last = DeliveryNote::where('company_id', $companyId)
            ->whereNotNull('delivery_note_number')
            ->orderByDesc('id')
            ->first();

        $lastDate = null;
        if ($last && $last->delivery_note_date) {
            $raw = $last->delivery_note_date;
            if (is_string($raw)) {
                $lastDate = substr($raw, 0, 10);
            } elseif (method_exists($raw, 'toDateString')) {
                $lastDate = $raw->toDateString();
            } else {
                $lastDate = (string) $raw;
            }
        }

        $used = DeliveryNote::where('company_id', $companyId)
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
            'last_number'            => $last?->delivery_note_number,
            'last_sequence'          => $last?->sequence_number,
            'last_numbered_date'     => $lastDate,
            'highest_sequence'       => $highestSequence,
            'next_expected_sequence' => $next,
        ]);
    }
}
