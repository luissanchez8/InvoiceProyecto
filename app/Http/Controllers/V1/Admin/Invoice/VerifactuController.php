<?php

namespace App\Http\Controllers\V1\Admin\Invoice;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class VerifactuController extends Controller
{
    public function send(Request $request, Invoice $invoice)
    {
        // 1) Comprobar que Verifactu está activo
        if (!config('services.verifactu.enabled')) {
            return response()->json([
                'ok'    => false,
                'error' => 'Verifactu no está habilitado',
            ], 400);
        }

        $endpoint = config('services.verifactu.endpoint');

        if (!$endpoint) {
            return response()->json([
                'ok'    => false,
                'error' => 'VERIFACTU_ENDPOINT no está configurado',
            ], 500);
        }

        $user   = $request->user();
        $appUrl = config('app.url');

        // 2) Payload mínimo que mandamos a lanzarWeb (/verifactu)
        $payload = [
            'invoice_id'   => $invoice->id,
            'invoice_no'   => $invoice->invoice_number,
            'user_id'      => optional($user)->id,
            'instance_url' => $appUrl,
            'test'         => true,
        ];

        try {
            $response = Http::timeout(5)->post($endpoint, $payload);

            if (!$response->ok()) {
                return response()->json([
                    'ok'    => false,
                    'error' => 'Error HTTP al llamar a Verifactu',
                    'code'  => $response->status(),
                    'body'  => $response->body(),
                ], 502);
            }

            return response()->json([
                'ok'   => true,
                'data' => $response->json(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'    => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
