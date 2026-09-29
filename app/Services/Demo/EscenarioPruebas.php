<?php

namespace App\Services\Demo;

use App\Models\ClosedMonth;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AprobarFactura;
use App\Services\CierreMes;
use App\Services\SerialNumberFormatter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Onfactu v.1.14.6 — Datos de prueba de la instancia demos (gastos con IVA
 * desde la v.1.15.0).
 *
 * Borra los datos de negocio y crea un juego pequeño y fijo, con importes
 * redondos, para saber de antemano qué tiene que salir en cada pantalla. El
 * detalle, con las cifras esperadas, está en docs/PRUEBAS_EN_DEMOS.md.
 *
 * Los meses son relativos a hoy: M-3 es hace tres meses, M el mes en curso.
 *
 *   M-3  FAC-1 Alfa   Diseño web x1    1.000 + 210 = 1.210  cobrada      (mes cerrado)
 *        FAC-2 Beta   Consultoría x1     100 +  21 =   121  cobrada
 *   M-2  FAC-3 Carmen Consultoría x2     200 +  42 =   242  cobrada      (mes cerrado)
 *        REC-1 Beta   rectifica FAC-2   -100 -  21 =  -121  devuelta
 *   M-1  FAC-4 Alfa   Diseño web x2    2.000 + 420 = 2.420  cobrada 1.210 (mes abierto)
 *        FAC-5 Beta   Consultoría x7     700 + 147 =   847  vencida
 *   M    FAC-6 Carmen Consultoría x1     100 +  21 =   121  pendiente
 *        Borrador Alfa Diseño web x5   5.000 + 1.050 = 6.050 (no cuenta en nada)
 *
 * Gastos con el IVA desglosado (y uno sin desglose): ver GastosPruebas.
 * La recurrente, el presupuesto, la proforma y el albarán: ComercialPruebas.
 * Lo que se borra antes: LimpiezaPruebas.
 *
 * Las facturas se crean en borrador y se aprueban con AprobarFactura, en
 * orden de fecha: el número lo pone Onfactu, como al pulsar Aprobar. Los
 * meses M-3 y M-2 se cierran y se entregan a la gestoría vinculada.
 *
 * Conserva la empresa, los usuarios, los ajustes, los impuestos, las formas
 * de pago y la vinculación con la gestoría.
 */
class EscenarioPruebas
{
    private ContextoDemo $c;

    private array $clientes = [];
    private array $articulos = [];
    /** clave => ['id', 'numero', 'fecha', 'calc', 'cliente'] */
    private array $facturas = [];

    public function __construct(private Carbon $hoy)
    {
        $this->hoy = $hoy->copy()->startOfDay();
    }

    /** Un día del mes que está $atras meses antes del actual, nunca después de hoy. */
    public function dia(int $atras, int $dia): Carbon
    {
        $mes = $this->hoy->copy()->startOfMonth()->subMonthsNoOverflow($atras);

        return $mes->day(min($dia, $mes->daysInMonth))->min($this->hoy)->copy()->startOfDay();
    }

    // ── Plan fijo ──────────────────────────────────────────────────────────

    private function planFacturas(): array
    {
        // [clave, cliente, meses atrás, día, [[artículo, cantidad]], días de vencimiento, cobros [[meses atrás, día, céntimos|null=total]]]
        return [
            ['FAC-1', 'alfa',   3, 5,  [['web', 1]],         30, [[3, 20, null]]],
            ['FAC-2', 'beta',   3, 15, [['consultoria', 1]], 30, [[3, 25, null]]],
            ['FAC-3', 'carmen', 2, 10, [['consultoria', 2]], 30, [[2, 15, null]]],
            ['FAC-4', 'alfa',   1, 5,  [['web', 2]],         30, [[1, 25, 121000]]],
            ['FAC-5', 'beta',   1, 20, [['consultoria', 7]], 15, []],
            ['FAC-6', 'carmen', 0, 1,  [['consultoria', 1]], 30, []],
        ];
    }

    // ── Preparar ───────────────────────────────────────────────────────────

    /** Borra y crea todo en una transacción: si algo falla, demos se queda como estaba. */
    public function preparar(): void
    {
        DB::transaction(function () {
            LimpiezaPruebas::vaciar();

            $this->c = new ContextoDemo($this->hoy);
            $this->maestros();
            $this->crearFacturas();
            $this->crearRectificativa();
            $comercial = new ComercialPruebas($this, $this->c);
            $comercial->crearRecurrente();
            $comercial->crearComerciales();
            (new GastosPruebas($this, $this->c))->crear();
            $this->cerrarMeses();
        });
    }

    /**
     * Lo que va fuera de la transacción: la central de gestorías (otra base
     * de datos) y los impuestos basura. Devuelve avisos para enseñar.
     */
    public function despues(): array
    {
        $avisos = array_filter([LimpiezaPruebas::limpiarCentral()]);

        $entregados = CierreMes::reenviarPendientes(1);
        $pendientes = ClosedMonth::pending()->count();
        $avisos[] = $pendientes
            ? "{$pendientes} mes(es) cerrado(s) sin entregar a la gestoría: ¿sigue vinculada y aceptada?"
            : "Meses entregados a la gestoría: {$entregados}.";

        $avisos[] = LimpiezaPruebas::borrarImpuestosBasura();

        return array_values(array_filter($avisos));
    }

    // ── Maestros ───────────────────────────────────────────────────────────

    private function maestros(): void
    {
        $ahora = $this->dia(4, 1)->toDateTimeString();

        foreach (['un', 'h', 'mes'] as $u) {
            if (! isset($this->c->unidades[$u])) {
                $this->c->unidades[$u] = (int) DB::table('units')->insertGetId([
                    'name' => $u, 'company_id' => $this->c->empresa, 'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
            }
        }

        $articulos = [
            'web'           => ['Diseño web', 'Web corporativa de hasta 6 secciones', 100000, 'un'],
            'consultoria'   => ['Consultoría', 'Hora de consultoría', 10000, 'h'],
            'mantenimiento' => ['Mantenimiento mensual', 'Actualizaciones y copias de seguridad', 5000, 'mes'],
        ];
        foreach ($articulos as $clave => [$nombre, $desc, $precio, $unidad]) {
            $id = (int) DB::table('items')->insertGetId([
                'name' => $nombre, 'description' => $desc, 'price' => $precio,
                'company_id' => $this->c->empresa, 'unit_id' => $this->c->unidades[$unidad],
                'creator_id' => $this->c->usuario, 'currency_id' => $this->c->moneda, 'tax_per_item' => false,
                'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
            $this->articulos[$clave] = ['nombre' => $nombre, 'descripcion' => $desc, 'precio' => $precio,
                                        'unidad' => $unidad, 'item_id' => $id];
        }

        $clientes = [
            'alfa'   => ['Alfa Servicios S.L.', true, CatalogoDemo::cif(1000001), 'Ana Alfa', 'Calle Mayor, 1', 'Madrid', 'Madrid', '28013'],
            'beta'   => ['Beta Comercio S.L.', true, CatalogoDemo::cif(2000002), 'Bruno Beta', 'Calle Colón, 2', 'Valencia', 'Valencia', '46004'],
            'carmen' => ['Carmen Prueba Particular', false, CatalogoDemo::dni(30000003), null, 'Calle Larios, 3', 'Málaga', 'Málaga', '29005'],
        ];
        $pais = $this->c->paises['ES'] ?? null;
        foreach ($clientes as $clave => [$nombre, $esEmpresa, $nif, $contacto, $dir, $ciudad, $prov, $cp]) {
            $id = (int) DB::table('customers')->insertGetId([
                'name' => $nombre, 'email' => $clave.'@'.CatalogoDemo::DOMINIO_CORREO, 'phone' => '600 000 00'.count($this->clientes),
                'contact_name' => $contacto, 'company_name' => $esEmpresa ? $nombre : null,
                'enable_portal' => false, 'currency_id' => $this->c->moneda, 'company_id' => $this->c->empresa,
                'creator_id' => $this->c->usuario, 'tax_id' => $nif, 'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
            DB::table('addresses')->insert([
                'name' => $nombre, 'address_street_1' => $dir, 'city' => $ciudad, 'state' => $prov, 'zip' => $cp,
                'country_id' => $pais, 'type' => 'billing', 'customer_id' => $id,
                'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
            $this->clientes[$clave] = $id;
        }
    }

    public function hoy(): Carbon
    {
        return $this->hoy->copy();
    }

    public function cliente(string $clave): int
    {
        return $this->clientes[$clave];
    }

    public function lineas(array $pares): array
    {
        return array_map(fn ($p) => $this->articulos[$p[0]] + ['cantidad' => $p[1]], $pares);
    }

    public function formaPago(): ?int
    {
        return $this->c->formasPago ? (int) end($this->c->formasPago) : null;
    }

    /**
     * Número con la serie que tenga configurada la instancia, como lo daría
     * Onfactu. Si el modelo no la tiene, SERIE-000001.
     *
     * @return array{0: string, 1: int, 2: int} número, secuencia y secuencia del cliente
     */
    public function numero(string $modelo, string $tabla, string $serie, int $cliente): array
    {
        try {
            $s = (new SerialNumberFormatter)->setModel(new $modelo)->setCompany($this->c->empresa)
                ->setCustomer($cliente)->setNextNumbers();
            $numero = $s->getNextNumber();
            if ($numero !== '') {
                return [$numero, (int) $s->nextSequenceNumber, (int) $s->nextCustomerSequenceNumber];
            }
        } catch (\Error|\InvalidArgumentException $e) {
            // Sin formato configurado: se usa el de por defecto
        }
        $q = DB::table($tabla)->where('company_id', $this->c->empresa);
        if ($serie === 'REC') {
            $q->whereNotNull('rectifies_invoice_id');
        }
        $n = (int) $q->max('sequence_number') + 1;

        return [ContextoDemo::numero($serie, $n), $n, 1];
    }

    public function hora(Carbon $dia, string $hora = '09:00:00'): string
    {
        return $dia->toDateString().' '.$hora;
    }

    // ── Facturas ───────────────────────────────────────────────────────────

    private function crearFacturas(): void
    {
        // Todas nacen en borrador, como en pantalla
        foreach ($this->planFacturas() as [$clave, $cliente, $atras, $d, $pares, $plazo, $cobros]) {
            $fecha = $this->dia($atras, $d);
            $calc = $this->c->calcular($this->lineas($pares), ['iva21']);
            $id = $this->insertarBorrador($cliente, $fecha, $fecha->copy()->addDays($plazo), $calc);
            $this->facturas[$clave] = ['id' => $id, 'fecha' => $fecha, 'calc' => $calc, 'cliente' => $cliente, 'cobros' => $cobros];
        }

        // Y se aprueban por orden de fecha: el número lo pone Onfactu
        foreach ($this->facturas as $clave => &$f) {
            $aprobada = AprobarFactura::aprobar(Invoice::findOrFail($f['id']));
            $f['numero'] = $aprobada->invoice_number;

            $cobrado = 0;
            foreach ($f['cobros'] as [$atras, $d, $importe]) {
                $importe ??= $f['calc']['total'];
                $this->crearCobro($f, $this->dia($atras, $d), $importe);
                $cobrado += $importe;
            }
            $pendiente = $f['calc']['total'] - $cobrado;
            $vence = Carbon::parse($aprobada->due_date);

            DB::table('invoices')->where('id', $f['id'])->update([
                'approved_at' => $this->hora($f['fecha']), 'sent' => true, 'sent_at' => $this->hora($f['fecha'], '10:00:00'),
                'paid_status' => $pendiente === 0 ? 'PAID' : ($cobrado > 0 ? 'PARTIALLY_PAID' : 'UNPAID'),
                'due_amount' => $pendiente, 'base_due_amount' => $pendiente,
                'overdue' => $pendiente > 0 && $vence->lt($this->hoy),
            ]);
        }
        unset($f);

        // El borrador: grande a propósito, para que se note si algo lo suma
        $calc = $this->c->calcular($this->lineas([['web', 5]]), ['iva21']);
        $this->insertarBorrador('alfa', $this->hoy->copy(), $this->hoy->copy()->addDays(30), $calc,
            'Borrador de prueba: no debe sumar en el panel, los informes ni el cierre.');
    }

    private function insertarBorrador(string $cliente, Carbon $fecha, Carbon $vence, array $calc, ?string $notas = null): int
    {
        $f = $this->hora($fecha);
        $id = (int) DB::table('invoices')->insertGetId($this->c->importes($calc) + [
            'invoice_date' => $f, 'due_date' => $vence->toDateString(),
            'invoice_number' => null, 'sequence_number' => null, 'customer_sequence_number' => null,
            'status' => Invoice::STATUS_DRAFT, 'paid_status' => 'UNPAID',
            'notes' => $notas, 'due_amount' => $calc['total'], 'base_due_amount' => $calc['total'],
            'sent' => false, 'viewed' => false, 'template_name' => $this->c->plantillaFactura,
            'customer_id' => $this->clientes[$cliente], 'overdue' => false,
            'payment_method_id' => $this->formaPago(), 'created_at' => $f, 'updated_at' => $f,
        ]);
        $this->c->insertarLineas('invoice_items', 'invoice_id', $id, $calc['lineas'], $f);
        $this->c->insertarImpuestos('invoice_id', $id, $calc['impuestos'], $f);
        $this->c->hash(Invoice::class, 'invoices', $id);

        return $id;
    }

    private function crearCobro(array $factura, Carbon $dia, int $importe): void
    {
        $cliente = $this->clientes[$factura['cliente']];
        [$numero, $seq, $seqCliente] = $this->numero(Payment::class, 'payments', 'PAG', $cliente);
        $f = $this->hora($dia, '10:00:00');

        $id = (int) DB::table('payments')->insertGetId([
            'payment_number' => $numero, 'payment_date' => $dia->toDateString(),
            'amount' => $importe, 'base_amount' => $importe, 'invoice_id' => $factura['id'],
            'customer_id' => $cliente, 'company_id' => $this->c->empresa,
            'payment_method_id' => $this->formaPago(), 'creator_id' => $this->c->usuario,
            'currency_id' => $this->c->moneda, 'exchange_rate' => 1, 'sequence_number' => $seq,
            'customer_sequence_number' => $seqCliente, 'created_at' => $f, 'updated_at' => $f,
        ]);
        $this->c->hash(Payment::class, 'payments', $id);
    }

    /** Rectificativa total de FAC-2, en M-2, ya devuelta. Como la genera Onfactu. */
    private function crearRectificativa(): void
    {
        $orig = $this->facturas['FAC-2'];
        $dia = $this->dia(2, 20);
        $f = $this->hora($dia);
        $cliente = $this->clientes[$orig['cliente']];
        $calc = $this->c->calcular($orig['calc']['lineas'], ['iva21'], 0, -1);

        $modelo = class_exists(\App\Models\Rectificative::class) ? \App\Models\Rectificative::class : Invoice::class;
        [$numero, $seq, $seqCliente] = $modelo === Invoice::class
            ? [ContextoDemo::numero('REC', 1), 1, 1]
            : $this->numero($modelo, 'invoices', 'REC', $cliente);

        $id = (int) DB::table('invoices')->insertGetId($this->c->importes($calc) + [
            'invoice_date' => $f, 'due_date' => $dia->toDateString(),
            'invoice_number' => $numero, 'sequence_number' => $seq, 'customer_sequence_number' => $seqCliente,
            'status' => Invoice::STATUS_APPROVED, 'paid_status' => 'PAID', 'approved_at' => $f,
            'notes' => "Esta factura rectifica a la factura {$orig['numero']} de fecha {$orig['fecha']->format('d/m/Y')}.",
            'due_amount' => 0, 'base_due_amount' => 0, 'sent' => true, 'sent_at' => $f, 'viewed' => false,
            'template_name' => $this->c->plantillaFactura, 'customer_id' => $cliente, 'overdue' => false,
            'payment_method_id' => $this->formaPago(), 'rectifies_invoice_id' => $orig['id'],
            'created_at' => $f, 'updated_at' => $f,
        ]);
        $this->c->insertarLineas('invoice_items', 'invoice_id', $id, $calc['lineas'], $f);
        $this->c->insertarImpuestos('invoice_id', $id, $calc['impuestos'], $f);
        $this->c->hash(Invoice::class, 'invoices', $id);

        DB::table('invoices')->where('id', $orig['id'])->update([
            'notes' => "Esta factura ha sido rectificada por la factura rectificativa {$numero} de fecha {$dia->format('d/m/Y')}.",
        ]);

        $this->facturas['REC-1'] = ['id' => $id, 'numero' => $numero, 'fecha' => $dia, 'calc' => $calc,
                                    'cliente' => $orig['cliente'], 'cobros' => []];
    }

    /**
     * Cierra M-3 y M-2 como lo haría el cliente, con el resumen de CierreMes.
     * La entrega a la central se hace después, fuera de la transacción.
     */
    private function cerrarMeses(): void
    {
        foreach ([3, 2] as $atras) {
            $mes = $this->dia($atras, 1);
            ClosedMonth::create([
                'company_id' => $this->c->empresa, 'year' => $mes->year, 'month' => $mes->month,
                'closed_by' => $this->c->usuario,
                'closed_at' => $mes->copy()->addMonthNoOverflow()->day(3)->setTime(10, 0),
                'totals' => CierreMes::resumen($this->c->empresa, $mes->year, $mes->month)['totales'],
                'sent_status' => 'pending',
            ]);
        }
        ClosedMonth::forgetCache();
    }

    // ── Lo que tiene que salir ─────────────────────────────────────────────

    /**
     * Cifras esperadas, calculadas del plan (no de lo que dice Onfactu), para
     * compararlas con las pantallas.
     */
    public function esperado(): array
    {
        $e = ['ventas' => 0, 'base' => 0, 'iva' => 0, 'pendiente' => 0, 'cobrado' => 0, 'gastos' => 0,
              'facturas' => 0, 'clientes' => [], 'meses' => []];

        foreach ($this->facturas as $clave => $f) {
            $cobrado = 0;
            foreach ($f['cobros'] as [, , $imp]) {
                $cobrado += $imp ?? $f['calc']['total'];
            }
            $pend = $clave === 'REC-1' ? 0 : $f['calc']['total'] - $cobrado;
            $base = $f['calc']['sub_total'];
            $e['facturas']++;
            $e['ventas'] += $f['calc']['total'];
            $e['base'] += $base;
            $e['iva'] += $f['calc']['tax'];
            $e['pendiente'] += $pend;
            $e['cobrado'] += $cobrado;
            $e['clientes'][$f['cliente']] = ($e['clientes'][$f['cliente']] ?? 0) + $f['calc']['total'];
            $m = $f['fecha']->format('Y-m');
            $e['meses'][$m]['base'] = ($e['meses'][$m]['base'] ?? 0) + $base;
            $e['meses'][$m]['iva'] = ($e['meses'][$m]['iva'] ?? 0) + $f['calc']['tax'];
            $e['meses'][$m]['total'] = ($e['meses'][$m]['total'] ?? 0) + $f['calc']['total'];
        }
        // Onfactu v.1.15.0: gastos con su IVA, y el resultado del IVA del año
        $g = (new GastosPruebas($this, $this->c ?? new ContextoDemo($this->hoy)))->esperado();
        $e['gastos'] = $g['gastos'];
        $e['gastos_detalle'] = $g;
        foreach ($g['meses'] as $m => $v) {
            $e['meses'][$m]['gastos'] = $v['gastos'];
            $e['meses'][$m]['gastos_iva'] = $v['gastos_iva'];
        }
        $e['iva_resultado'] = $e['iva'] + $g['autoliquidado'] - $g['deducible'] - $g['autoliquidado'];
        ksort($e['meses']);

        return $e;
    }
}
