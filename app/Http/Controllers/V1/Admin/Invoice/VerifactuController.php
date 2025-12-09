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

        // --- Payload con la forma que espera el webhook / worker ---

        // Fecha en ISO 8601 (si viene como Carbon)
        $invoiceDate = $invoice->invoice_date instanceof \Carbon\Carbon
            ? $invoice->invoice_date->toIso8601String()
            : $invoice->invoice_date;

        // Empresa emisora (ajusta estos valores a tu caso real)
        $seller = [
            // puedes usar config('app.company_vat') si lo tienes definido en config/app.php
            'nif'  => config('app.company_vat', 'B00000000'),      
            'name' => config('app.company_name', 'Mi Empresa S.L.'), 
        ];

        // Cliente (buyer) – cambia tax_number al campo real del NIF si es otro
        $buyer = [
            'nif'  => $invoice->customer?->tax_number ?? '00000000A',
            'name' => $invoice->customer?->name ?? 'Cliente sin nombre',
        ];

        // Texto descriptivo
        $text = $invoice->notes ?? ('Factura ' . $invoice->invoice_number);

        // De momento: 1 bloque de impuestos “fake” para probar el circuito
        // Luego afinamos base / cuota con las líneas reales de la factura.
        $taxItems = [
            [
                'rate'   => 21,                        // provisional
                'base'   => (float) $invoice->total,   // provisional
                'amount' => 0.0,                       // provisional
            ],
        ];

        // ESTE es el payload que verá /verifactu (Node) y el worker
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
