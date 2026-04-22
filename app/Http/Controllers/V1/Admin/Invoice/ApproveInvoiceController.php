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

        // ── Numeración diferida (Onfactu) ───────────────────────────────────
        // Asignar invoice_number ANTES de publicar a VeriFactu. Dos casos:
        //  1. El usuario ya puso un número manualmente → se valida unicidad y
        //     se respeta tal cual.
        //  2. No hay número → se genera automáticamente (transacción + lock).
        //     Si colisiona con otro borrador manual, NO se salta: se devuelve
        //     error 409 con los datos del conflicto para que el usuario pueda
        //     resolverlo (editando o eliminando la factura conflictiva).
        try {
            $invoice = $invoice->assignNumber();
        } catch (\App\Exceptions\NumberCollisionException $e) {
            Log::warning('Colisión al asignar número de factura', [
                'invoice_id' => $invoice->id,
                'details' => $e->getDetails(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => 'number_collision',
                'details' => $e->getDetails(),
            ], 409);
        } catch (\Throwable $e) {
            Log::error('Error al asignar número de factura al aprobar', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
        // ────────────────────────────────────────────────────────────────────

        // Marcar como pendiente de aprobación VeriFactu
        $invoice->verifactu_status = Invoice::VERIFACTU_PENDING;
        $invoice->save();

        // Onfactu — approve asíncrono vía fastcgi_finish_request():
        // Responder al navegador YA (sin esperar a RabbitMQ) y luego publicar
        // el mensaje con el script ya "desconectado" del cliente HTTP.
        //
        // Esto evita el toast "Please check your internet connection" cuando
        // Rabbit tarda en abrir el socket, sin necesidad de montar un worker
        // de Laravel (queue:work) ni supervisor. El trade-off es que si el
        // publish falla, no hay reintentos automáticos: la factura queda en
        // VERIFACTU_ERROR con el mensaje y hay que reaprobarla desde la UI.
        //
        // Para esto preparamos la respuesta, llamamos a flush() + finish() y
        // seguimos ejecutando el publish. Si el SAPI no soporta
        // fastcgi_finish_request (p.ej. en CLI durante tests), caemos a
        // publicar síncronamente (comportamiento legacy).
        $response = response()->json([
            'success' => true,
            'data' => $invoice->fresh(),
        ]);

        // En entornos PHP-FPM, flush + finish envían la respuesta al cliente
        // y cierran la conexión TCP con el navegador, pero el script PHP
        // sigue vivo ejecutando lo que venga después.
        if (function_exists('fastcgi_finish_request')) {
            // Enviamos los headers y el body al cliente
            $response->send();

            // Cerramos la conexión con el cliente. A partir de aquí el
            // navegador ya tiene la respuesta y nos da igual lo que tardemos.
            fastcgi_finish_request();

            // Ahora sí, publicamos a Rabbit sin presión de timeout HTTP.
            try {
                $this->publishToVerifactu($invoice);
            } catch (\Throwable $e) {
                Log::error('Error al publicar en cola VeriFactu (post-response)', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);

                // Como ya respondimos 200 al cliente, no podemos devolver
                // error HTTP. Marcamos el estado para que lo vea el frontend
                // vía polling de verifactu_status.
                $invoice->verifactu_status = Invoice::VERIFACTU_ERROR;
                $invoice->verifactu_error = $e->getMessage();
                $invoice->save();
            }

            // El return aquí no tiene efecto (la respuesta ya se envió),
            // pero lo dejamos por claridad de flujo.
            return $response;
        }

        // Fallback (CLI, tests, SAPI sin FastCGI): comportamiento síncrono.
        try {
            $this->publishToVerifactu($invoice);
        } catch (\Throwable $e) {
            Log::error('Error al publicar en cola VeriFactu (sync fallback)', [
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

        return $response;
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
