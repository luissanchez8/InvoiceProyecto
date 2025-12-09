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

        // ==== VALIDACIÓN: El cliente debe tener NIF/CIF ====
        $customerNif = $invoice->customer?->tax_number;
        if (!$customerNif) {
            return response()->json([
                'ok' => false,
                'error' => 'El cliente no tiene NIF/CIF definido. Edítalo antes de enviar a Verifactu.',
            ], 422);
        }

        // ==== Fecha en ISO ====
        $invoiceDate = $invoice->invoice_date instanceof \Carbon\Carbon
            ? $invoice->invoice_date->toIso8601String()
            : $invoice->invoice_date;

        // ==== Empresa emisora ====
        $seller = [
            'nif'  => config('app.company_vat', 'B00000000'),
            'name' => config('app.company_name', 'Mi Empresa S.L.'),
        ];

        // ==== Cliente (buyer) ====
        $buyer = [
            'nif'       => $customerNif,
            'name'      => $invoice->customer?->name ?? 'Cliente sin nombre',
            'countryId' => 'ES',    // 👈 IMPORTANTE: Añadir siempre ES
        ];

        // ==== Texto ====
        $text = $invoice->notes ?? ('Factura ' . $invoice->invoice_number);

        // ==== Impuestos dummy por ahora ====
        $taxItems = [
            [
                'rate'   => 21,
                'base'   => (float) $invoice->total,
                'amount' => 0.0,
            ],
        ];

        // ==== Payload final para el webhook ====
        $payload = [
            'invoiceId'      => $invoice->invoice_number ?? (string) $invoice->id,
            'invoiceDate'    => $invoiceDate,
            'invoiceType'    => 'F1',
            'seller'         => $seller,
            'buyer'          => $buyer,
            'text'           => $text,
            'taxItems'       => $taxItems,
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
