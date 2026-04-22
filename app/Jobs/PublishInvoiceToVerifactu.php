<?php

namespace App\Jobs;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Job que publica una factura en la cola RabbitMQ para su procesamiento
 * VeriFactu (firma AEAT + envío SII).
 *
 * Por qué es un Job asíncrono:
 * La apertura del socket TCP contra RabbitMQ desde PHP (AMQPStreamConnection)
 * puede tardar varios segundos si hay latencia de red o si el broker está
 * saturado. Hacerlo síncrono dentro del ApproveInvoiceController bloqueaba
 * la petición HTTP y el navegador mostraba "Please check your internet
 * connection" por timeout, aunque la factura ya estuviera creada en BD.
 *
 * Flujo con Job:
 *  1. Controller asigna invoice_number, marca verifactu_status = PENDING
 *     y despacha este Job (operación instantánea: solo inserta una fila en
 *     la tabla `jobs`).
 *  2. Controller responde 200 OK inmediatamente.
 *  3. Un worker de Laravel (`php artisan queue:work`) toma el Job de la
 *     cola y ejecuta handle(), que abre la conexión a RabbitMQ sin
 *     presión de timeout HTTP.
 *  4. El frontend hace polling contra /api/v1/invoices/{id} para detectar
 *     cuándo verifactu_status cambia a SIGNED / APPROVED / ERROR.
 *
 * Reintentos: hasta 3 intentos con backoff exponencial (5s, 15s, 45s).
 * Si todos fallan, el Job se marca como failed y la factura queda en
 * verifactu_status = ERROR con el mensaje en verifactu_error.
 */
class PublishInvoiceToVerifactu implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número máximo de reintentos.
     */
    public int $tries = 3;

    /**
     * Backoff en segundos para cada reintento.
     *
     * @return array<int>
     */
    public function backoff(): array
    {
        return [5, 15, 45];
    }

    /**
     * Timeout del Job en segundos (si handle() tarda más, se cancela).
     */
    public int $timeout = 60;

    public function __construct(public Invoice $invoice)
    {
        // SerializesModels se encarga de serializar la referencia al modelo
        // (en realidad solo guarda el id + class). Al ejecutar, Laravel
        // recupera la instancia fresh desde BD.
    }

    public function handle(): void
    {
        $invoice = $this->invoice->fresh(['customer', 'company', 'items.taxes']);

        if (! $invoice) {
            Log::warning('PublishInvoiceToVerifactu: factura no encontrada', [
                'invoice_id' => $this->invoice->id,
            ]);

            return;
        }

        // Construir payload idéntico al del antiguo publishToVerifactu síncrono
        $taxItems = [];
        foreach ($invoice->items as $item) {
            foreach ($item->taxes as $tax) {
                $taxItems[] = [
                    'name' => $tax->name,
                    'percent' => $tax->percent,
                    'amount' => $tax->amount,
                    'compound_tax' => $tax->compound_tax,
                ];
            }
        }

        $payload = [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date,
                'due_date' => $invoice->due_date,
                'total' => $invoice->total,
                'sub_total' => $invoice->sub_total,
                'tax' => $invoice->tax,
                'discount' => $invoice->discount,
                'discount_type' => $invoice->discount_type,
                'discount_val' => $invoice->discount_val,
                'notes' => $invoice->notes,
                'reference_number' => $invoice->reference_number,
                'status' => $invoice->status,
                'paid_status' => $invoice->paid_status,
                'currency_id' => $invoice->currency_id,
                'exchange_rate' => $invoice->exchange_rate,
                'base_total' => $invoice->base_total,
                'base_sub_total' => $invoice->base_sub_total,
                'base_tax' => $invoice->base_tax,
                'base_discount_val' => $invoice->base_discount_val,
                'base_due_amount' => $invoice->base_due_amount,
                'due_amount' => $invoice->due_amount,
                'sent' => $invoice->sent,
                'viewed' => $invoice->viewed,
                'customer' => $invoice->customer ? [
                    'id' => $invoice->customer->id,
                    'name' => $invoice->customer->name,
                    'email' => $invoice->customer->email,
                    'vat_id' => $invoice->customer->vat_id ?? null,
                    'vat_number' => $invoice->customer->vat_number ?? null,
                    'address_street_1' => $invoice->customer->billing?->address_street_1 ?? null,
                    'address_street_2' => $invoice->customer->billing?->address_street_2 ?? null,
                    'city' => $invoice->customer->billing?->city ?? null,
                    'state' => $invoice->customer->billing?->state ?? null,
                    'country_id' => $invoice->customer->billing?->country_id ?? null,
                    'zip' => $invoice->customer->billing?->zip ?? null,
                    'phone' => $invoice->customer->billing?->phone ?? null,
                ] : null,
                'company' => $invoice->company ? [
                    'id' => $invoice->company->id,
                    'name' => $invoice->company->name,
                    'unique_hash' => $invoice->company->unique_hash,
                    'slug' => $invoice->company->slug,
                ] : null,
                'items' => $invoice->items->map(function ($item) {
                    return [
                        'name' => $item->name,
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'discount' => $item->discount,
                        'tax' => $item->tax,
                        'total' => $item->total,
                        'unit_name' => $item->unit_name ?? null,
                    ];
                })->toArray(),
                'taxItems' => $taxItems,
            ],
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

        try {
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
        } catch (\Throwable $e) {
            Log::error('PublishInvoiceToVerifactu: fallo publicando a RabbitMQ', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Re-lanzamos para que Laravel reintente. Si agota los tries,
            // ejecutará failed() abajo.
            throw $e;
        }
    }

    /**
     * Se ejecuta cuando el Job falla definitivamente (tras agotar todos
     * los reintentos). Marca la factura como ERROR para que el usuario
     * vea el problema en la UI.
     */
    public function failed(\Throwable $exception): void
    {
        $invoice = $this->invoice->fresh();
        if (! $invoice) {
            return;
        }

        $invoice->verifactu_status = Invoice::VERIFACTU_ERROR;
        $invoice->verifactu_error = 'No se pudo enviar a VeriFactu tras ' . $this->tries
            . ' intentos: ' . $exception->getMessage();
        $invoice->save();

        Log::error('PublishInvoiceToVerifactu: agotados todos los reintentos', [
            'invoice_id' => $invoice->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
