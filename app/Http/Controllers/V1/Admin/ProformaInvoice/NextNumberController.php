<?php

namespace App\Http\Controllers\V1\Admin\ProformaInvoice;

use App\Http\Controllers\Controller;
use App\Models\ProformaInvoice;
use App\Models\CompanySetting;
use Illuminate\Http\Request;

/**
 * Onfactu: info de consistencia para el formulario de proformas.
 * GET /api/v1/proforma-invoices/next-number-info
 */
class NextNumberController extends Controller
{
    public function __invoke(Request $request)
    {
        $companyId = $request->header('company');

        $last = ProformaInvoice::where('company_id', $companyId)
            ->whereNotNull('proforma_invoice_number')
            ->orderByDesc('id')
            ->first();

        $lastDate = null;
        if ($last && $last->proforma_invoice_date) {
            $raw = $last->proforma_invoice_date;
            if (is_string($raw)) {
                $lastDate = substr($raw, 0, 10);
            } elseif (method_exists($raw, 'toDateString')) {
                $lastDate = $raw->toDateString();
            } else {
                $lastDate = (string) $raw;
            }
        }

        $used = ProformaInvoice::where('company_id', $companyId)
            ->whereNotNull('sequence_number')
            ->orderBy('sequence_number', 'asc')
            ->pluck('sequence_number')
            ->map(fn ($n) => (int) $n)
            ->unique()
            ->values()
            ->all();

        // v.1.9.2 — Leer número inicial configurado (default 1)
        $startNumber = (int) (CompanySetting::getSetting('proformainvoice_start_number', $companyId) ?: 1);
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
            'last_number'            => $last?->proforma_invoice_number,
            'last_sequence'          => $last?->sequence_number,
            'last_numbered_date'     => $lastDate,
            'highest_sequence'       => $highestSequence,
            'next_expected_sequence' => $next,
        ]);
    }
}
