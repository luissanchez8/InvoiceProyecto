<?php

namespace App\Http\Controllers\V1\Admin\Invoice;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VerifactuController extends Controller
{
    public function send(Invoice $invoice, Request $request)
    {
        Log::info('VerifactuController::send', [
            'invoice_id' => $invoice->id,
        ]);

        $endpoint = config('services.verifactu.endpoint');

        if (!$endpoint) {
            Log::error('VERIFACTU_ENDPOINT no está configurado');
            return response()->json([
                'ok'    => false,
                'error' => 'VERIFACTU_ENDPOINT no está configurado',
            ], 500);
        }

        // --- Aquí preparamos el payload con la forma que espera el worker ---

        // Fecha en ISO 8601 (si invoice_date es string, lo dejamos tal cual)
        $invoiceDate = $invoice->invoice_date instanceof \Carbon\Carbon
            ? $invoice->invoice_date->toIso8601String()
            : $invoice->invoice_date;

        // TODO: ajusta estos campos según tu modelo de empresa / emisor
        $seller = [
            'nif'  => config('app.company_vat', 'B00000000'),  // AJUSTAR
            'name' => config('app.company_name', 'Mi Empresa S.L.'), // AJUSTAR
        ];

        // TODO: ajusta estos campos según tu modelo de cliente
        $buyer = [
            'nif'  => $invoice->customer?->tax_number ?? null, // AJUSTAR al campo real del NIF
            'name' => $invoice->customer?->name,
        ];

        // Texto descriptivo (usa notas, o un mensaje genérico)
        $text = $invoice->notes ?? ('Factura ' . $invoice->invoice_number);

        // Por ahora montamos un único bloque de impuestos simple con total como base.
        // Idealmente deberíamos agrupar por tipo de IVA a partir de las líneas de la factura.
        $taxItems = [
            [
                'rate'   => 21,                   // TODO: AJUSTAR, coger del invoice o líneas
                'base'   => (float) $invoice->total, // aquí debería ir la base imponible real
                'amount' => 0.0,                 // TODO: AJUSTAR, cuota de IVA real
            ],
        ];

        // Fecha en ISO 8601 (si viene como Carbon)
$invoiceDate = $invoice->invoice_date instanceof \Carbon\Carbon
    ? $invoice->invoice_date->toIso8601String()
    : $invoice->invoice_date;

// De momento hardcodeamos la empresa emisora para probar
$seller = [
    'nif'  => 'B00000000',          // PON AQUÍ TU NIF EMPRESA
    'name' => 'Mi Empresa S.L.',    // PON AQUÍ EL NOMBRE DE TU EMPRESA
];

// Cliente (buyer)
$buyer = [
    'nif'  => $invoice->customer?->tax_number ?? '00000000A', // cambia tax_number al campo real del NIF si es otro
    'name' => $invoice->customer?->name ?? 'Cliente sin nombre',
];

// Texto de la factura
$text = $invoice->notes ?? ('Factura ' . $invoice->invoice_number);

// De momento 1 solo bloque de impuestos “fake” para probar el circuito
$taxItems = [
    [
        'rate'   => 21,                        // provisional
        'base'   => (float) $invoice->total,   // provisional (sería mejor la base imponible)
        'amount' => 0.0,                       // provisional (luego lo calculamos bien)
    ],
];

// ESTE es el payload que verá el webhook y el worker
$payload = [
    'invoiceId'   => $invoice->invoice_number ?? (string) $invoice->id,
    'invoiceDate' => $invoiceDate,
    'invoiceType' => 'F1', // tipo estándar, ya lo afinaremos
    'seller'      => $seller,
    'buyer'       => $buyer,
    'text'        => $text,
    'taxItems'    => $taxItems,
];

        try {
            $resp = Http::timeout(10)->post($endpoint, $payload);

            Log::info('Respuesta Verifactu (webhook)', [
                'status' => $resp->status(),
                'body'   => $resp->body(),
            ]);

            if (!$resp->ok()) {
                return response()->json([
                    'ok'    => false,
                    'error' => 'Error al enviar a Verifactu (webhook)',
                    'code'  => $resp->status(),
                ], 500);
            }

            return response()->json([
                'ok'       => true,
                'response' => $resp->json(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Excepción Verifactu (webhook)', [
                'msg' => $e->getMessage(),
            ]);

            return response()->json([
                'ok'    => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
