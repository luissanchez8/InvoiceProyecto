<?php

namespace App\Http\Controllers\V1\Admin\Invoice;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class ApproveInvoiceController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice)
    {
        $this->authorize('send invoice', $invoice);

        // Verificar que VeriFactu está habilitado para esta empresa
        $verifactuEnabled = CompanySetting::getSetting(
            'verifactu_enabled',
            $invoice->company_id
        );

        if ($verifactuEnabled !== 'YES') {
            return response()->json([
                'success' => false,
                'error' => 'VeriFactu no está habilitado',
            ], 400);
        }

        // Marcar como pendiente de aprobación VeriFactu
        $invoice->verifactu_status = Invoice::VERIFACTU_PENDING;
        $invoice->save();

        // Publicar en cola RabbitMQ
        try {
            $this->publishToVerifactu($invoice);
        } catch (\Throwable $e) {
            Log::error('Error al publicar en cola VeriFactu', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            $invoice->verifactu_status = Invoice::VERIFACTU_ERROR;
            $invoice->verifactu_error = $e->getMessage();
            $invoice->save();

            return response()->json([
                'success' => false,
                'error' => 'Error al enviar a VeriFactu: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $invoice->fresh(),
        ]);
    }

    private function publishToVerifactu(Invoice $invoice): void
    {
        $invoice->load(['customer', 'company', 'items.taxes']);

        $company = $invoice->company;
        $customer = $invoice->customer;

        // Construir items de impuestos desde los items de la factura
        $taxItems = [];
        foreach ($invoice->items as $item) {
            foreach ($item->taxes as $tax) {
                $taxItems[] = [
                    'rate' => (float) $tax->percent,
                    'base' => (float) ($item->total / 100),
                    'amount' => (float) ($item->tax / 100),
                ];
            }
        }

        // Si no hay impuestos, usar el total con IVA 0
        if (empty($taxItems)) {
            $taxItems[] = [
                'rate' => 0,
                'base' => (float) ($invoice->sub_total / 100),
                'amount' => 0.0,
            ];
        }

        $invoiceDate = $invoice->invoice_date instanceof \Carbon\Carbon
            ? $invoice->invoice_date->format('Y-m-d')
            : $invoice->invoice_date;

        $payload = [
            'factura' => [
                'invoiceId' => $invoice->invoice_number ?? (string) $invoice->id,
                'invoiceDate' => $invoiceDate,
                'invoiceType' => 'F1',
                'text' => $invoice->notes ?? ('Factura ' . $invoice->invoice_number),
                'seller' => [
                    'nif' => CompanySetting::getSetting('vat_id', $invoice->company_id) ?: ($company->vat_id ?? ''),
                    'name' => $company->name ?? '',
                ],
                'buyer' => [
                    'nif' => $customer->tax_number ?? '',
                    'name' => $customer->name ?? '',
                    'countryId' => 'ES',
                ],
                'taxItems' => $taxItems,
            ],
            // Datos para el webhook de vuelta
            'callback' => [
                'invoice_id' => $invoice->id,
                'company_id' => $invoice->company_id,
                'webhook_url' => rtrim(config('app.url'), '/') . '/api/webhooks/verifactu',
                'webhook_token' => config('rabbitmq.verifactu_webhook_token', ''),
            ],
        ];

        $host = config('rabbitmq.host', '127.0.0.1');
        $port = (int) config('rabbitmq.port', 5672);
        $user = config('rabbitmq.user', 'guest');
        $password = config('rabbitmq.password', 'guest');
        $queue = config('rabbitmq.verifactu_queue', 'verifactu.facturas');

        $connection = new AMQPStreamConnection($host, $port, $user, $password);
        $channel = $connection->channel();

        $channel->queue_declare($queue, false, true, false, false);

        $message = new AMQPMessage(
            json_encode($payload),
            ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
        );

        $channel->basic_publish($message, '', $queue);

        Log::info('Factura publicada en cola VeriFactu', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'queue' => $queue,
        ]);

        $channel->close();
        $connection->close();
    }
}
