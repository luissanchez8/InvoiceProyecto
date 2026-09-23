<?php

namespace App\Services\Demo;

use App\Models\DeliveryNote;
use App\Models\Estimate;
use App\Models\ProformaInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Presupuestos, proformas y albaranes de la demo.
 *
 * Los estados siguen la antigüedad: lo reciente está enviado o en borrador, y
 * lo antiguo aceptado, rechazado o caducado. Los albaranes son de productos
 * (impresión), que es lo que se entrega físicamente.
 */
class ComercialDemo
{
    private array $secuenciaCliente = [];

    public function __construct(private ContextoDemo $c, private array $m, private array $facturas)
    {
    }

    public function crear(): void
    {
        $this->presupuestos();
        $this->proformas();
        $this->albaranes();
    }

    private function presupuestos(): void
    {
        $antiguos = ['ACCEPTED', 'ACCEPTED', 'ACCEPTED', 'ACCEPTED', 'ACCEPTED', 'ACCEPTED',
                     'REJECTED', 'REJECTED', 'EXPIRED', 'EXPIRED'];
        $recientes = ['SENT', 'SENT', 'SENT', 'DRAFT', 'DRAFT'];
        shuffle($antiguos);
        shuffle($recientes);

        $fechas = $this->fechas(count($antiguos), 200, 25);
        $fechas = array_merge($fechas, $this->fechas(count($recientes), 20, 0));
        $estados = array_merge($antiguos, $recientes);

        foreach ($fechas as $n => $fecha) {
            $cliente = $this->cliente(['empresa', 'empresa', 'particular', 'ue']);
            [$lineas, $claves] = $this->lineas($cliente, false);
            $calc = $this->c->calcular($lineas, $claves, $this->c->probable(0.2) ? 5 : 0);
            $estado = $estados[$n];
            $f = $fecha->toDateString() . ' 09:00:00';

            $id = (int) DB::table('estimates')->insertGetId($this->c->importes($calc) + [
                'estimate_date' => $fecha->toDateString(),
                'expiry_date' => $fecha->copy()->addDays(15)->toDateString(),
                'estimate_number' => ContextoDemo::numero('PRE', $n + 1), 'status' => $estado,
                'notes' => $estado === 'REJECTED' ? 'El cliente ha decidido aplazar el proyecto.' : null,
                'template_name' => $this->c->plantillaPresupuesto, 'customer_id' => $cliente['id'],
                'sequence_number' => $n + 1,
                'customer_sequence_number' => $this->siguiente('PRE', $cliente['id']),
                'payment_method_id' => end($this->c->formasPago), 'created_at' => $f, 'updated_at' => $f,
            ]);
            $this->c->insertarLineas('estimate_items', 'estimate_id', $id, $calc['lineas'], $f);
            $this->c->insertarImpuestos('estimate_id', $id, $calc['impuestos'], $f);
            $this->c->hash(Estimate::class, 'estimates', $id);
        }
    }

    private function proformas(): void
    {
        $plan = [['ACCEPTED', 120, 60], ['ACCEPTED', 90, 40], ['REJECTED', 75, 30],
                 ['SENT', 20, 5], ['SENT', 14, 2], ['DRAFT', 3, 0]];

        foreach ($plan as $n => [$estado, $desde, $hasta]) {
            $fecha = $this->c->fechaEntre($this->c->hoy->copy()->subDays($desde), $this->c->hoy->copy()->subDays($hasta));
            $cliente = $this->cliente(['empresa', 'empresa', 'particular']);
            [$lineas, $claves] = $this->lineas($cliente, false);
            $calc = $this->c->calcular($lineas, $claves);
            $f = $fecha->toDateString() . ' 09:00:00';

            // Una proforma aceptada acaba convertida en factura del mismo cliente
            $convertida = null;
            if ($estado === 'ACCEPTED') {
                $factura = collect($this->facturas)->first(fn ($x) => $x['cliente']['id'] === $cliente['id'] && $x['fecha']->gte($fecha));
                $convertida = $factura['id'] ?? null;
            }

            $id = (int) DB::table('proforma_invoices')->insertGetId($this->c->importes($calc) + [
                'proforma_invoice_date' => $fecha->toDateString(),
                'expiry_date' => $fecha->copy()->addDays(15)->toDateString(),
                'proforma_invoice_number' => ContextoDemo::numero('PRO', $n + 1), 'sequence_number' => $n + 1,
                'customer_sequence_number' => $this->siguiente('PRO', $cliente['id']), 'status' => $estado,
                'notes' => $estado === 'ACCEPTED' ? 'Pago por adelantado del 50 % para iniciar el proyecto.' : null,
                'template_name' => $this->c->plantillaFactura, 'customer_id' => $cliente['id'],
                'sent' => $estado !== 'DRAFT', 'viewed' => in_array($estado, ['ACCEPTED', 'REJECTED'], true),
                'converted_invoice_id' => $convertida, 'payment_method_id' => end($this->c->formasPago),
                'created_at' => $f, 'updated_at' => $f,
            ]);
            $this->c->insertarLineas('proforma_invoice_items', 'proforma_invoice_id', $id, $calc['lineas'], $f);
            $this->c->insertarImpuestos('proforma_invoice_id', $id, $calc['impuestos'], $f);
            $this->c->hash(ProformaInvoice::class, 'proforma_invoices', $id);
        }
    }

    private function albaranes(): void
    {
        $plan = ['DELIVERED', 'DELIVERED', 'DELIVERED', 'DELIVERED', 'DELIVERED', 'SENT', 'SENT', 'DRAFT'];
        $fechas = array_merge($this->fechas(5, 120, 12), $this->fechas(3, 10, 0));

        foreach ($fechas as $n => $fecha) {
            $estado = $plan[$n];
            $cliente = $this->cliente(['empresa', 'particular']);
            [$lineas, $claves] = $this->lineas($cliente, true);
            $calc = $this->c->calcular($lineas, $claves);
            $f = $fecha->toDateString() . ' 09:00:00';

            $id = (int) DB::table('delivery_notes')->insertGetId($this->c->importes($calc) + [
                'delivery_note_date' => $fecha->toDateString(),
                'delivery_date' => $fecha->copy()->addDays($this->c->azar(2, 5))->toDateString(),
                'delivery_note_number' => ContextoDemo::numero('ALB', $n + 1), 'sequence_number' => $n + 1,
                'customer_sequence_number' => $this->siguiente('ALB', $cliente['id']), 'status' => $estado,
                'show_prices' => $n % 3 !== 2, 'template_name' => $this->c->plantillaFactura,
                'customer_id' => $cliente['id'], 'sent' => $estado !== 'DRAFT', 'viewed' => $estado === 'DELIVERED',
                'payment_method_id' => end($this->c->formasPago), 'created_at' => $f, 'updated_at' => $f,
            ]);
            $this->c->insertarLineas('delivery_note_items', 'delivery_note_id', $id, $calc['lineas'], $f);
            $this->c->insertarImpuestos('delivery_note_id', $id, $calc['impuestos'], $f);
            $this->c->hash(DeliveryNote::class, 'delivery_notes', $id);
        }
    }

    // ── Utilidades ─────────────────────────────────────────────────────────

    /** Fechas ordenadas entre hace $desde y hace $hasta días. */
    private function fechas(int $n, int $desde, int $hasta): array
    {
        $f = [];
        for ($i = 0; $i < $n; $i++) {
            $f[] = $this->c->fechaEntre($this->c->hoy->copy()->subDays($desde), $this->c->hoy->copy()->subDays($hasta));
        }
        usort($f, fn (Carbon $a, Carbon $b) => $a <=> $b);

        return $f;
    }

    private function cliente(array $tipos): array
    {
        $tipo = $this->c->elegir($tipos);

        return $this->c->elegir(array_values(array_filter($this->m['clientes'], fn ($x) => $x['tipo'] === $tipo)));
    }

    /** @return array [lineas, claves de impuestos] */
    private function lineas(array $cliente, bool $productos): array
    {
        $fuente = array_values(array_filter($this->m['articulos'], fn ($a) => $productos
            ? $a['tipo'] === 'producto'
            : ($a['tipo'] === 'servicio' && $a['unidad'] !== 'mes')));
        shuffle($fuente);

        $lineas = [];
        foreach (array_slice($fuente, 0, $this->c->azar(1, 3)) as $a) {
            $cantidad = match (true) {
                $a['unidad'] === 'h' => $this->c->azar(4, 20),
                $a['precio'] >= 50000 => 1,
                default => $this->c->azar(1, 4),
            };
            $lineas[] = ['nombre' => $a['nombre'], 'descripcion' => $a['descripcion'], 'precio' => $a['precio'],
                         'cantidad' => $cantidad, 'unidad' => $a['unidad'], 'item_id' => $a['id']];
        }

        return [$lineas, $this->c->impuestosPara($cliente['tipo'], ! $productos)];
    }

    private function siguiente(string $serie, int $cliente): int
    {
        return $this->secuenciaCliente[$serie][$cliente] = ($this->secuenciaCliente[$serie][$cliente] ?? 0) + 1;
    }
}
