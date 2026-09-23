<?php

namespace App\Services\Demo;

use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Facturas de la demo: los últimos once meses hasta hoy, con recurrentes,
 * rectificativas y cobros.
 *
 * Reparto de estados, como en un negocio real:
 *  - lo antiguo casi todo cobrado, con alguna factura vencida y alguna a medias
 *  - lo reciente, parte cobrado y parte pendiente sin vencer
 *  - dos borradores sin número, los más recientes
 */
class FacturasDemo
{
    /** Facturas planificadas antes de insertarlas */
    private array $plan = [];

    public function __construct(private ContextoDemo $c, private array $m)
    {
    }

    /** @return array facturas emitidas: ['id','cliente','fecha','numero'] */
    public function crear(): array
    {
        $this->planificarNormales();
        $this->crearRecurrentes();
        $this->ajustarEstados();
        $emitidas = $this->insertar();
        $this->crearRectificativas();
        $this->crearCobros();

        return $emitidas;
    }

    // ── Planificación ──────────────────────────────────────────────────────

    private function planificarNormales(): void
    {
        for ($m = 10; $m >= 0; $m--) {
            $ini = $this->c->hoy->copy()->startOfMonth()->subMonths($m);
            $fin = $m === 0 ? $this->c->hoy->copy() : $ini->copy()->endOfMonth()->startOfDay();
            $n = $m === 0 ? 3 : $this->c->azar(3, 5);

            for ($i = 0; $i < $n; $i++) {
                $cliente = $this->clienteAlAzar();
                [$lineas, $soloServicios] = $this->lineasPara($cliente);
                $this->planificar($cliente, $this->c->fechaEntre($ini, $fin), $lineas,
                    $this->c->impuestosPara($cliente['tipo'], $soloServicios),
                    $this->c->probable(0.12) ? 10 : 0);
            }
        }

        // Un borrador más, de hoy
        $cliente = $this->clienteAlAzar();
        [$lineas, $soloServicios] = $this->lineasPara($cliente);
        $this->planificar($cliente, $this->c->hoy->copy(), $lineas,
            $this->c->impuestosPara($cliente['tipo'], $soloServicios), 0);
    }

    private function planificar(array $cliente, Carbon $fecha, array $lineas, array $claves, float $dto, ?int $recurrente = null): void
    {
        $this->plan[] = [
            'cliente' => $cliente, 'fecha' => $fecha, 'vence' => $fecha->copy()->addDays(30),
            'lineas' => $lineas, 'claves' => $claves, 'dto' => $dto,
            'calc' => $this->c->calcular($lineas, $claves, $dto),
            'recurrente' => $recurrente, 'estado' => null, 'pago' => null, 'cobros' => [],
            'notas' => $recurrente ? 'Cuota mensual del servicio.' : null, 'orden' => count($this->plan),
        ];
    }

    private function clienteAlAzar(): array
    {
        $r = $this->c->azar(1, 100);
        $tipo = $r <= 60 ? 'empresa' : ($r <= 90 ? 'particular' : 'ue');
        $candidatos = array_values(array_filter($this->m['clientes'], fn ($x) => $x['tipo'] === $tipo));

        return $this->c->elegir($candidatos);
    }

    /** @return array [lineas, soloServicios] */
    private function lineasPara(array $cliente): array
    {
        // A empresas y a la UE solo servicios: la retención no se aplica a productos
        $fuente = array_values(array_filter($this->m['articulos'], fn ($a) => $a['unidad'] !== 'mes'
            && ($cliente['tipo'] === 'particular' || $a['tipo'] === 'servicio')));
        shuffle($fuente);

        $lineas = [];
        $soloServicios = true;
        foreach (array_slice($fuente, 0, $this->c->azar(1, 3)) as $a) {
            $lineas[] = $this->linea($a, match (true) {
                $a['unidad'] === 'h' => $this->c->azar(2, 12),
                $a['precio'] >= 50000 => 1,
                default => $this->c->azar(1, 3),
            });
            $soloServicios = $soloServicios && $a['tipo'] === 'servicio';
        }

        return [$lineas, $soloServicios];
    }

    public function linea(array $a, int $cantidad): array
    {
        return ['nombre' => $a['nombre'], 'descripcion' => $a['descripcion'], 'precio' => $a['precio'],
                'cantidad' => $cantidad, 'unidad' => $a['unidad'], 'item_id' => $a['id']];
    }

    private function crearRecurrentes(): void
    {
        $config = [
            ['Clínica Dental Sonrisa Plus S.L.', 'Mantenimiento web mensual', 8],
            ['Hotel Mirador de la Bahía S.L.', 'Gestión de redes sociales', 6],
            ['Floristería Las Camelias S.L.', 'Mantenimiento web mensual', 4],
        ];

        foreach ($config as [$nombreCliente, $nombreArticulo, $mesesAtras]) {
            $cliente = collect($this->m['clientes'])->firstWhere('nombre', $nombreCliente);
            $articulo = collect($this->m['articulos'])->firstWhere('nombre', $nombreArticulo);
            $lineas = [$this->linea($articulo, 1)];
            $claves = $this->c->impuestosPara($cliente['tipo'], true);
            $calc = $this->c->calcular($lineas, $claves);
            $inicio = $this->c->hoy->copy()->startOfMonth()->subMonths($mesesAtras);
            $f = $inicio->toDateTimeString();

            $id = (int) DB::table('recurring_invoices')->insertGetId([
                'starts_at' => $f, 'send_automatically' => false, 'customer_id' => $cliente['id'],
                'company_id' => $this->c->empresa, 'status' => 'ACTIVE',
                'next_invoice_at' => $this->c->hoy->copy()->startOfMonth()->addMonth()->toDateTimeString(),
                'creator_id' => $this->c->usuario, 'frequency' => '0 0 1 * *', 'limit_by' => 'NONE',
                'currency_id' => $this->c->moneda, 'exchange_rate' => 1, 'tax_per_item' => 'NO',
                'discount_per_item' => 'NO', 'notes' => 'Cuota mensual del servicio.', 'discount_type' => 'fixed',
                'discount' => 0, 'discount_val' => 0, 'sub_total' => $calc['sub_total'], 'total' => $calc['total'],
                'tax' => $calc['tax'], 'template_name' => $this->c->plantillaFactura, 'due_amount' => $calc['total'],
                'payment_method_id' => end($this->c->formasPago), 'created_at' => $f, 'updated_at' => $f,
            ]);
            $this->c->insertarLineas('invoice_items', 'recurring_invoice_id', $id, $calc['lineas'], $f);
            $this->c->insertarImpuestos('recurring_invoice_id', $id, $calc['impuestos'], $f);

            // Las facturas que ya ha ido generando, el día 1 de cada mes
            for ($mes = $inicio->copy(); $mes->lte($this->c->hoy); $mes->addMonth()) {
                $this->planificar($cliente, $mes->copy(), $lineas, $claves, 0, $id);
            }
        }
    }

    /** Estados y cobros según la antigüedad de cada factura. */
    private function ajustarEstados(): void
    {
        usort($this->plan, fn ($a, $b) => [$a['fecha'], $a['orden']] <=> [$b['fecha'], $b['orden']]);

        // Los dos más recientes que no son recurrentes quedan como borrador
        $borradores = 0;
        for ($i = count($this->plan) - 1; $i >= 0 && $borradores < 2; $i--) {
            if (! $this->plan[$i]['recurrente']) {
                $this->plan[$i]['estado'] = 'DRAFT';
                $this->plan[$i]['pago'] = 'UNPAID';
                $borradores++;
            }
        }

        foreach ($this->plan as &$f) {
            if ($f['estado'] === 'DRAFT') {
                continue;
            }
            $vencida = $f['vence']->lt($this->c->hoy);
            $esteMes = $f['fecha']->gte($this->c->hoy->copy()->startOfMonth());
            $f['pago'] = ($f['recurrente'] && $esteMes) ? 'UNPAID'
                : ($vencida || $this->c->probable(0.5) ? 'PAID' : 'UNPAID');
        }
        unset($f);

        // Casos que tienen que verse siempre: dos vencidas sin cobrar y una a medias
        $antiguas = array_keys(array_filter($this->plan, fn ($f) => $f['estado'] !== 'DRAFT'
            && ! $f['recurrente'] && $f['vence']->lt($this->c->hoy->copy()->subDays(15))));
        $antiguas = array_slice(array_reverse($antiguas), 0, 3);
        foreach ($antiguas as $n => $k) {
            $this->plan[$k]['pago'] = $n < 2 ? 'UNPAID' : 'PARTIALLY_PAID';
        }

        foreach ($this->plan as &$f) {
            if ($f['estado'] === 'DRAFT') {
                continue;
            }
            $total = $f['calc']['total'];
            // El cobro nunca puede quedar en el futuro
            $d = max(0, (int) $f['fecha']->diffInDays($this->c->hoy));
            $cobro = $d === 0 ? $f['fecha']->copy()
                : $f['fecha']->copy()->addDays($this->c->azar(min(3, $d), min(40, $d)));

            if ($f['pago'] === 'PAID') {
                $f['cobros'][] = [$cobro, $total];
                $f['estado'] = 'COMPLETED';
            } elseif ($f['pago'] === 'PARTIALLY_PAID') {
                $f['cobros'][] = [$cobro, (int) round($total / 2)];
                $f['estado'] = 'SENT';
            } else {
                $f['estado'] = 'SENT';
            }
        }
        unset($f);
    }

    // ── Inserción ──────────────────────────────────────────────────────────

    private array $secuenciaCliente = [];

    private function siguienteDelCliente(string $serie, int $cliente): int
    {
        return $this->secuenciaCliente[$serie][$cliente] = ($this->secuenciaCliente[$serie][$cliente] ?? 0) + 1;
    }

    /** @return array facturas emitidas */
    private function insertar(): array
    {
        $n = 0;
        $emitidas = [];

        foreach ($this->plan as &$f) {
            $borrador = $f['estado'] === 'DRAFT';
            $numero = $borrador ? null : ContextoDemo::numero('FAC', ++$n);
            $pagado = array_sum(array_column($f['cobros'], 1));
            $pendiente = $f['calc']['total'] - $pagado;
            $fecha = $f['fecha']->toDateString() . ' 09:00:00';

            $f['id'] = (int) DB::table('invoices')->insertGetId($this->c->importes($f['calc']) + [
                'invoice_date' => $fecha, 'due_date' => $f['vence']->toDateString(),
                'invoice_number' => $numero, 'status' => $f['estado'], 'paid_status' => $f['pago'],
                'notes' => $f['notas'], 'due_amount' => $pendiente, 'base_due_amount' => $pendiente,
                'sent' => ! $borrador, 'viewed' => ! $borrador && $this->c->probable(0.6),
                'template_name' => $this->c->plantillaFactura, 'customer_id' => $f['cliente']['id'],
                'recurring_invoice_id' => $f['recurrente'],
                'sequence_number' => $borrador ? null : $n,
                'customer_sequence_number' => $borrador ? null : $this->siguienteDelCliente('FAC', $f['cliente']['id']),
                'overdue' => ! $borrador && $pendiente > 0 && $f['vence']->lt($this->c->hoy),
                'payment_method_id' => $this->c->formaPago(),
                'created_at' => $fecha, 'updated_at' => $fecha,
            ]);
            $f['numero'] = $numero;

            $this->c->insertarLineas('invoice_items', 'invoice_id', $f['id'], $f['calc']['lineas'], $fecha);
            $this->c->insertarImpuestos('invoice_id', $f['id'], $f['calc']['impuestos'], $fecha);
            $this->c->hash(Invoice::class, 'invoices', $f['id']);

            if (! $borrador) {
                $emitidas[] = ['id' => $f['id'], 'cliente' => $f['cliente'], 'fecha' => $f['fecha'], 'numero' => $numero];
            }
        }
        unset($f);

        return $emitidas;
    }

    /** Dos rectificativas totales de facturas ya cobradas, en la serie REC. */
    private function crearRectificativas(): void
    {
        $candidatas = array_values(array_filter($this->plan, fn ($f) => $f['pago'] === 'PAID'
            && ! $f['recurrente'] && $f['fecha']->lt($this->c->hoy->copy()->subDays(60))));
        if (count($candidatas) < 2) {
            return;
        }
        shuffle($candidatas);

        foreach (array_slice($candidatas, 0, 2) as $k => $orig) {
            $numero = ContextoDemo::numero('REC', $k + 1);
            $fechaC = $orig['fecha']->copy()->addDays($this->c->azar(10, 25))->min($this->c->hoy);
            $fecha = $fechaC->toDateString() . ' 09:00:00';
            $calc = $this->c->calcular($orig['lineas'], $orig['claves'], $orig['dto'], -1);

            $id = (int) DB::table('invoices')->insertGetId($this->c->importes($calc) + [
                'invoice_date' => $fecha, 'due_date' => $fechaC->toDateString(),
                'invoice_number' => $numero, 'status' => 'COMPLETED', 'paid_status' => 'PAID',
                'notes' => "Rectifica la factura {$orig['numero']} por un error en los datos del servicio.",
                'due_amount' => 0, 'base_due_amount' => 0, 'sent' => true, 'viewed' => true,
                'template_name' => $this->c->plantillaFactura, 'customer_id' => $orig['cliente']['id'],
                'sequence_number' => $k + 1,
                'customer_sequence_number' => $this->siguienteDelCliente('REC', $orig['cliente']['id']),
                'overdue' => false, 'payment_method_id' => end($this->c->formasPago),
                'rectifies_invoice_id' => $orig['id'], 'created_at' => $fecha, 'updated_at' => $fecha,
            ]);
            $this->c->insertarLineas('invoice_items', 'invoice_id', $id, $calc['lineas'], $fecha);
            $this->c->insertarImpuestos('invoice_id', $id, $calc['impuestos'], $fecha);
            $this->c->hash(Invoice::class, 'invoices', $id);

            DB::table('invoices')->where('id', $orig['id'])->update(['notes' => "Rectificada por {$numero}."]);
        }
    }

    private function crearCobros(): void
    {
        $cobros = [];
        foreach ($this->plan as $f) {
            foreach ($f['cobros'] as [$fecha, $importe]) {
                $cobros[] = ['fecha' => $fecha, 'importe' => $importe, 'factura' => $f['id'], 'cliente' => $f['cliente']['id']];
            }
        }
        usort($cobros, fn ($a, $b) => $a['fecha'] <=> $b['fecha']);

        foreach ($cobros as $n => $p) {
            $fecha = $p['fecha']->toDateString() . ' 10:00:00';
            $id = (int) DB::table('payments')->insertGetId([
                'payment_number' => ContextoDemo::numero('PAG', $n + 1), 'payment_date' => $p['fecha']->toDateString(),
                'amount' => $p['importe'], 'base_amount' => $p['importe'], 'invoice_id' => $p['factura'],
                'customer_id' => $p['cliente'], 'company_id' => $this->c->empresa,
                'payment_method_id' => $this->c->formaPago(), 'creator_id' => $this->c->usuario,
                'currency_id' => $this->c->moneda, 'exchange_rate' => 1, 'sequence_number' => $n + 1,
                'customer_sequence_number' => $this->siguienteDelCliente('PAG', $p['cliente']),
                'created_at' => $fecha, 'updated_at' => $fecha,
            ]);
            $this->c->hash(Payment::class, 'payments', $id);
        }
    }
}
