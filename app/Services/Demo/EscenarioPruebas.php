<?php

namespace App\Services\Demo;

use App\Models\ClosedMonth;
use App\Models\DeliveryNote;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Services\AprobarFactura;
use App\Services\CierreMes;
use App\Services\GestoriaService;
use App\Services\SerialNumberFormatter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Onfactu v.1.14.6 — Datos de prueba de la instancia demos.
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
 * Gastos: alquiler de 500 cada mes y una licencia de 121 en M-1.
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
    /** Tablas que se vacían enteras. Clientes y direcciones van aparte. */
    public const TABLAS = [
        'taxes', 'invoice_items', 'estimate_items', 'proforma_invoice_items', 'delivery_note_items',
        'payments', 'transactions', 'invoices', 'estimates', 'proforma_invoices', 'delivery_notes',
        'recurring_invoices', 'expenses', 'expense_categories', 'items', 'custom_field_values',
        'email_logs', 'closed_months', 'notifications', 'exchange_rate_logs',
    ];

    /**
     * Tablas de configuración que no se tocan nunca. Si alguna dependiera de
     * las que se vacían, se para sin borrar nada.
     */
    private const PROTEGIDAS = [
        'users', 'companies', 'company_settings', 'settings', 'app_config', 'tax_types',
        'payment_methods', 'units', 'currencies', 'countries', 'custom_fields', 'addresses',
        'customers', 'user_company', 'abilities', 'permissions', 'roles', 'assigned_roles',
    ];

    private ContextoDemo $c;

    private array $clientes = [];
    private array $articulos = [];
    private array $categorias = [];
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

    private function planGastos(): array
    {
        // [meses atrás, día, concepto, céntimos, categoría]
        return [
            [3, 1, 'Alquiler de la oficina', 50000, 'Alquiler'],
            [2, 1, 'Alquiler de la oficina', 50000, 'Alquiler'],
            [1, 1, 'Alquiler de la oficina', 50000, 'Alquiler'],
            [1, 12, 'Licencia anual del programa de diseño', 12100, 'Software'],
            [0, 1, 'Alquiler de la oficina', 50000, 'Alquiler'],
        ];
    }

    // ── Ensayo ─────────────────────────────────────────────────────────────

    /** Lo que se borraría, sin tocar nada. */
    public function recuento(): array
    {
        $filas = [];
        foreach (array_merge(self::TABLAS, self::dependientes()) as $t) {
            $filas[] = [$t, DB::table($t)->count()];
        }
        $filas[] = ['customers', DB::table('customers')->count()];
        $filas[] = ['addresses (de clientes)', DB::table('addresses')->whereNotNull('customer_id')->count()];
        $filas[] = ['media (sin logo ni avatares)', $this->mediaQuery()->count()];
        $filas[] = ['tax_types "Impuesto N" (basura)', $this->impuestosBasura()->count()];

        return $filas;
    }

    /**
     * Tablas que no están en la lista pero dependen de alguna que sí (por
     * ejemplo, registros de VeriFactu de las facturas). Se vacían con ellas.
     */
    public static function dependientes(): array
    {
        $todas = self::TABLAS;
        do {
            $nuevas = collect(DB::select(
                "SELECT DISTINCT hija.relname AS tabla
                   FROM pg_constraint c
                   JOIN pg_class hija  ON hija.oid  = c.conrelid
                   JOIN pg_class madre ON madre.oid = c.confrelid
                  WHERE c.contype = 'f' AND madre.relname = ANY(?::text[])",
                ['{'.implode(',', $todas).'}']
            ))->pluck('tabla')->diff($todas)->values()->all();
            $todas = array_merge($todas, $nuevas);
        } while ($nuevas);

        $extra = array_values(array_diff($todas, self::TABLAS));
        $prohibidas = array_intersect($extra, self::PROTEGIDAS);
        if ($prohibidas) {
            throw new RuntimeException('Estas tablas de configuración dependen de las que se vacían: '
                .implode(', ', $prohibidas).'. No se ha borrado nada.');
        }

        return $extra;
    }

    // ── Preparar ───────────────────────────────────────────────────────────

    /** Borra y crea todo en una transacción: si algo falla, demos se queda como estaba. */
    public function preparar(): void
    {
        DB::transaction(function () {
            $this->vaciar();

            $this->c = new ContextoDemo($this->hoy);
            $this->maestros();
            $this->crearFacturas();
            $this->crearRectificativa();
            $this->crearRecurrente();
            $this->crearComerciales();
            $this->crearGastos();
            $this->cerrarMeses();
        });
    }

    /**
     * Lo que va fuera de la transacción: la central de gestorías (otra base
     * de datos) y los impuestos basura. Devuelve avisos para enseñar.
     */
    public function despues(): array
    {
        $avisos = [];

        // Los cierres antiguos de demos en la central: se sustituyen por los nuevos
        $central = fn () => DB::connection('gestorias')->table('gestoria_cierres')
            ->where('subdominio', GestoriaService::subdominio());
        try {
            $central()->delete();
        } catch (\Throwable $e) {
            // El usuario de la instancia puede no tener permiso para borrar:
            // solo se avisa si quedan meses que no se van a sustituir.
            try {
                $nuevos = ClosedMonth::periodsFor(1);
                $quedan = $central()->get(['year', 'month'])
                    ->reject(fn ($r) => in_array(sprintf('%04d-%02d', $r->year, $r->month), $nuevos, true))
                    ->count();
            } catch (\Throwable $e2) {
                $quedan = 1;
            }
            if ($quedan) {
                $avisos[] = 'No se pudieron borrar los cierres antiguos de demos en la central (permisos): '
                    .'pueden seguir viéndose en el portal junto a los nuevos.';
            }
        }

        $entregados = CierreMes::reenviarPendientes(1);
        $pendientes = ClosedMonth::pending()->count();
        if ($pendientes) {
            $avisos[] = "{$pendientes} mes(es) cerrado(s) sin entregar a la gestoría: ¿sigue vinculada y aceptada?";
        } else {
            $avisos[] = "Meses entregados a la gestoría: {$entregados}.";
        }

        try {
            $n = $this->impuestosBasura()->delete();
            if ($n) {
                $avisos[] = "Borrados {$n} tipos de impuesto de prueba (\"Impuesto N\").";
            }
        } catch (\Throwable $e) {
            $avisos[] = 'No se pudieron borrar los tipos de impuesto "Impuesto N": '.$e->getMessage();
        }

        return $avisos;
    }

    private function vaciar(): void
    {
        $tablas = array_merge(self::TABLAS, self::dependientes());

        // Sin CASCADE: si otra tabla dependiera de estas, PostgreSQL se niega
        // en vez de vaciarla a escondidas.
        DB::statement('TRUNCATE TABLE '.implode(', ', array_map(fn ($t) => '"'.$t.'"', $tablas)).' RESTART IDENTITY');

        // La dirección de la empresa no tiene cliente: se queda
        DB::table('addresses')->whereNotNull('customer_id')->delete();
        DB::table('customers')->delete();
        $this->mediaQuery()->delete();

        ClosedMonth::forgetCache();
    }

    private function mediaQuery()
    {
        return DB::table('media')->whereNotIn('model_type', ['App\\Models\\Company', 'App\\Models\\User']);
    }

    private function impuestosBasura()
    {
        return DB::table('tax_types')->where('name', '~', '^Impuesto [0-9]+$')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('taxes')->whereColumn('taxes.tax_type_id', 'tax_types.id'));
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

        foreach (['Alquiler', 'Software'] as $nombre) {
            $this->categorias[$nombre] = (int) DB::table('expense_categories')->insertGetId([
                'name' => $nombre, 'company_id' => $this->c->empresa, 'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
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

    private function lineas(array $pares): array
    {
        return array_map(fn ($p) => $this->articulos[$p[0]] + ['cantidad' => $p[1]], $pares);
    }

    private function formaPago(): ?int
    {
        return $this->c->formasPago ? (int) end($this->c->formasPago) : null;
    }

    /**
     * Número con la serie que tenga configurada la instancia, como lo daría
     * Onfactu. Si el modelo no la tiene, SERIE-000001.
     *
     * @return array{0: string, 1: int, 2: int} número, secuencia y secuencia del cliente
     */
    private function numero(string $modelo, string $tabla, string $serie, int $cliente): array
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

    private function hora(Carbon $dia, string $hora = '09:00:00'): string
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

    /** Mantenimiento mensual a Beta. Empieza el mes que viene: no genera nada hasta entonces. */
    private function crearRecurrente(): void
    {
        $calc = $this->c->calcular($this->lineas([['mantenimiento', 1]]), ['iva21']);
        $inicio = $this->hoy->copy()->startOfMonth()->addMonthNoOverflow();
        $f = $this->hora($this->hoy);

        $id = (int) DB::table('recurring_invoices')->insertGetId([
            'starts_at' => $inicio->toDateTimeString(), 'send_automatically' => false, 'auto_approve' => false,
            'customer_id' => $this->clientes['beta'], 'company_id' => $this->c->empresa, 'status' => 'ACTIVE',
            'next_invoice_at' => $inicio->toDateTimeString(), 'creator_id' => $this->c->usuario,
            'frequency' => '0 0 1 * *', 'limit_by' => 'NONE', 'currency_id' => $this->c->moneda,
            'exchange_rate' => 1, 'tax_per_item' => 'NO', 'discount_per_item' => 'NO',
            'notes' => 'Cuota mensual del mantenimiento.', 'discount_type' => 'fixed', 'discount' => 0,
            'discount_val' => 0, 'sub_total' => $calc['sub_total'], 'total' => $calc['total'], 'tax' => $calc['tax'],
            'template_name' => $this->c->plantillaFactura, 'due_amount' => $calc['total'],
            'payment_method_id' => $this->formaPago(), 'created_at' => $f, 'updated_at' => $f,
        ]);
        $this->c->insertarLineas('invoice_items', 'recurring_invoice_id', $id, $calc['lineas'], $f);
        $this->c->insertarImpuestos('recurring_invoice_id', $id, $calc['impuestos'], $f);
    }

    /** Un presupuesto enviado, una proforma en borrador y un albarán entregado, de este mes. */
    private function crearComerciales(): void
    {
        $dia = $this->hoy->copy();
        $f = $this->hora($dia);

        // Presupuesto a Beta: 10 horas
        $calc = $this->c->calcular($this->lineas([['consultoria', 10]]), ['iva21']);
        $cliente = $this->clientes['beta'];
        [$numero, $seq, $seqCliente] = $this->numero(Estimate::class, 'estimates', 'PRE', $cliente);
        $id = (int) DB::table('estimates')->insertGetId($this->c->importes($calc) + [
            'estimate_date' => $dia->toDateString(), 'expiry_date' => $dia->copy()->addDays(15)->toDateString(),
            'estimate_number' => $numero, 'sequence_number' => $seq, 'customer_sequence_number' => $seqCliente,
            'status' => Estimate::STATUS_SENT, 'template_name' => $this->c->plantillaPresupuesto,
            'customer_id' => $cliente, 'payment_method_id' => $this->formaPago(), 'created_at' => $f, 'updated_at' => $f,
        ]);
        $this->c->insertarLineas('estimate_items', 'estimate_id', $id, $calc['lineas'], $f);
        $this->c->insertarImpuestos('estimate_id', $id, $calc['impuestos'], $f);
        $this->c->hash(Estimate::class, 'estimates', $id);

        // Proforma a Carmen, en borrador: sin número, como las de pantalla
        $calc = $this->c->calcular($this->lineas([['web', 1]]), ['iva21']);
        $id = (int) DB::table('proforma_invoices')->insertGetId($this->c->importes($calc) + [
            'proforma_invoice_date' => $dia->toDateString(), 'expiry_date' => $dia->copy()->addDays(15)->toDateString(),
            'proforma_invoice_number' => null, 'sequence_number' => null, 'customer_sequence_number' => null,
            'status' => ProformaInvoice::STATUS_DRAFT, 'template_name' => $this->c->plantillaFactura,
            'customer_id' => $this->clientes['carmen'], 'sent' => false, 'viewed' => false,
            'payment_method_id' => $this->formaPago(), 'created_at' => $f, 'updated_at' => $f,
        ]);
        $this->c->insertarLineas('proforma_invoice_items', 'proforma_invoice_id', $id, $calc['lineas'], $f);
        $this->c->insertarImpuestos('proforma_invoice_id', $id, $calc['impuestos'], $f);
        $this->c->hash(ProformaInvoice::class, 'proforma_invoices', $id);

        // Albarán a Alfa, entregado: 3 horas
        $calc = $this->c->calcular($this->lineas([['consultoria', 3]]), ['iva21']);
        $cliente = $this->clientes['alfa'];
        [$numero, $seq, $seqCliente] = $this->numero(DeliveryNote::class, 'delivery_notes', 'ALB', $cliente);
        $id = (int) DB::table('delivery_notes')->insertGetId($this->c->importes($calc) + [
            'delivery_note_date' => $dia->toDateString(), 'delivery_date' => $dia->toDateString(),
            'delivery_note_number' => $numero, 'sequence_number' => $seq, 'customer_sequence_number' => $seqCliente,
            'status' => DeliveryNote::STATUS_DELIVERED, 'show_prices' => true,
            'template_name' => $this->c->plantillaFactura, 'customer_id' => $cliente, 'sent' => true, 'viewed' => false,
            'payment_method_id' => $this->formaPago(), 'created_at' => $f, 'updated_at' => $f,
        ]);
        $this->c->insertarLineas('delivery_note_items', 'delivery_note_id', $id, $calc['lineas'], $f);
        $this->c->insertarImpuestos('delivery_note_id', $id, $calc['impuestos'], $f);
        $this->c->hash(DeliveryNote::class, 'delivery_notes', $id);
    }

    private function crearGastos(): void
    {
        $filas = [];
        foreach ($this->planGastos() as [$atras, $d, $concepto, $importe, $categoria]) {
            $dia = $this->dia($atras, $d);
            $f = $this->hora($dia, '10:00:00');
            $filas[] = [
                'expense_date' => $dia->toDateString(), 'amount' => $importe, 'base_amount' => $importe,
                'notes' => $concepto, 'expense_category_id' => $this->categorias[$categoria],
                'company_id' => $this->c->empresa, 'creator_id' => $this->c->usuario, 'customer_id' => null,
                'currency_id' => $this->c->moneda, 'exchange_rate' => 1,
                'payment_method_id' => $this->formaPago(), 'created_at' => $f, 'updated_at' => $f,
            ];
        }
        DB::table('expenses')->insert($filas);
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
        foreach ($this->planGastos() as [$atras, $d, , $importe]) {
            $e['gastos'] += $importe;
            $m = $this->dia($atras, $d)->format('Y-m');
            $e['meses'][$m]['gastos'] = ($e['meses'][$m]['gastos'] ?? 0) + $importe;
        }
        ksort($e['meses']);

        return $e;
    }
}
