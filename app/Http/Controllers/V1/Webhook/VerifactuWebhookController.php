<?php

namespace App\Http\Controllers\V1\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifactuWebhookController extends Controller
{
    /**
     * Webhook llamado por el worker-verifactu.js cuando termina de procesar una factura.
     *
     * Payload esperado:
     * {
     *   "invoice_id": 123,
     *   "success": true,
     *   "qr_code": "base64_encoded_image...",
     *   "signature": "hash_de_firma...",
     *   "signed_at": "2026-04-09T12:00:00Z",
     *   "error": null
     * }
     */
    public function __invoke(Request $request)
    {
        $token = $request->header('X-Verifactu-Token');
        $expectedToken = env('VERIFACTU_WEBHOOK_TOKEN', '');

        if (! $expectedToken || $token !== $expectedToken) {
            Log::warning('VeriFactu webhook: token inválido');

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $invoiceId = $request->input('invoice_id');
        $success = $request->input('success', false);
        $qrCode = $request->input('qr_code');
        $signature = $request->input('signature');
        $signedAt = $request->input('signed_at');
        $error = $request->input('error');

        $invoice = Invoice::find($invoiceId);

        if (! $invoice) {
            Log::error('VeriFactu webhook: factura no encontrada', ['invoice_id' => $invoiceId]);

            return response()->json(['error' => 'Invoice not found'], 404);
        }

        if ($success) {
            $invoice->verifactu_status = Invoice::VERIFACTU_SIGNED;
            $invoice->verifactu_qr = $qrCode;
            $invoice->verifactu_signature = $signature;
            $invoice->verifactu_signed_at = $signedAt ?? now();
            $invoice->verifactu_error = null;
            $invoice->status = Invoice::STATUS_APPROVED;
            $invoice->sent = true;
            $invoice->save();

            Log::info('VeriFactu webhook: factura aprobada', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
            ]);
        } else {
            $invoice->verifactu_status = Invoice::VERIFACTU_ERROR;
            $invoice->verifactu_error = $error ?? 'Error desconocido';
            $invoice->save();

            Log::error('VeriFactu webhook: error al firmar factura', [
                'invoice_id' => $invoice->id,
                'error' => $error,
            ]);
        }

        return response()->json(['success' => true]);
    }
}
