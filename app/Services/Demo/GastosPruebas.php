<?php

namespace App\Services\Demo;

use App\Support\IvaGastos;
use Illuminate\Support\Facades\DB;

/**
 * Onfactu v.1.15.0 — Gastos de los datos de prueba de demos, con el IVA
 * desglosado. Hay un caso de cada cosa que hay que probar:
 *
 *   M-3  Alquiler      500 + 21 % (105) - retención 19 % (95) = 510
 *        Material      121, sin desglose (como los gastos de antes de la v.1.15.0)
 *   M-2  Alquiler      510
 *   M-1  Alquiler      510
 *        Google Ads    100, intracomunitaria 21 %: autoliquida 21, no se paga
 *        Comida        50 + 10 % (5) no deducible = 55
 *        Papelería     100 + 21 % (21) y 50 + 10 % (5) en el mismo ticket = 176
 *   M    Alquiler      510
 *
 * Los importes los calcula IvaGastos, igual que al guardar desde la pantalla.
 */
class GastosPruebas
{
    public function __construct(private EscenarioPruebas $e, private ContextoDemo $c)
    {
    }

    /**
     * [meses atrás, día, categoría, concepto, proveedor, NIF, nº factura,
     *  líneas [[tipo, base, deducible]] o null si va sin desglose, retención %, importe sin desglose]
     */
    public function plan(): array
    {
        $casero = ['Inmuebles Centro S.L.', CatalogoDemo::cif(4000004)];
        // v.1.16.0: el de M-2 se crea con el número mal escrito (sin el cero)
        // y ReaperturaPruebas lo corrige abriendo el mes, como haría el cliente
        $alquiler = fn (int $atras) => [$atras, 1, 'Alquiler', 'Alquiler de la oficina', $casero[0], $casero[1],
            'ALQ-'.$this->e->dia($atras, 1)->format($atras === 2 ? 'Y-n' : 'Y-m'), [['iva21', 50000, true]], 19, null];

        return [
            $alquiler(3),
            [3, 10, 'Material', 'Material de oficina (anterior al desglose del IVA)', null, null, null, null, 0, 12100],
            $alquiler(2),
            $alquiler(1),
            [1, 8, 'Publicidad', 'Campaña de anuncios', 'Google Ireland Ltd', 'IE6388047V', 'GA-2026-0815',
                [['intra21', 10000, true]], 0, null],
            [1, 15, 'Comidas', 'Comida con un cliente', 'Restaurante El Puerto', CatalogoDemo::cif(5000005), 'T-118',
                [['iva10', 5000, false]], 0, null],
            [1, 22, 'Material', 'Papelería y café para la oficina', 'Suministros Sur S.L.', CatalogoDemo::cif(6000006), 'F-2031',
                [['iva21', 10000, true], ['iva10', 5000, true]], 0, null],
            $alquiler(0),
        ];
    }

    public function crear(): void
    {
        $categorias = [];
        $alta = $this->e->hora($this->e->dia(4, 1));
        foreach (array_unique(array_column($this->plan(), 2)) as $nombre) {
            $categorias[$nombre] = (int) DB::table('expense_categories')->insertGetId([
                'name' => $nombre, 'company_id' => $this->c->empresa, 'created_at' => $alta, 'updated_at' => $alta,
            ]);
        }

        foreach ($this->plan() as [$atras, $d, $categoria, $concepto, $proveedor, $nif, $numero, $lineas, $ret, $sinDesglose]) {
            $dia = $this->e->dia($atras, $d);
            $f = $this->e->hora($dia, '10:00:00');
            $calc = $lineas ? $this->calcular($lineas, $ret) : null;
            $importe = $calc ? $calc['total'] : $sinDesglose;

            $id = (int) DB::table('expenses')->insertGetId([
                'expense_date' => $dia->toDateString(), 'amount' => $importe, 'base_amount' => $importe,
                'notes' => $concepto, 'expense_category_id' => $categorias[$categoria],
                'company_id' => $this->c->empresa, 'creator_id' => $this->c->usuario, 'customer_id' => null,
                'currency_id' => $this->c->moneda, 'exchange_rate' => 1,
                'payment_method_id' => $this->e->formaPago(), 'created_at' => $f, 'updated_at' => $f,
                'proveedor_nombre' => $proveedor, 'proveedor_nif' => $nif, 'numero_factura' => $numero,
                'con_desglose' => (bool) $calc,
                'base_imponible' => $calc['base_imponible'] ?? null, 'cuota_iva' => $calc['cuota_iva'] ?? null,
                'cuota_deducible' => $calc['cuota_deducible'] ?? null,
                'cuota_autoliquidada' => $calc['cuota_autoliquidada'] ?? null,
                'retencion_porcentaje' => $calc ? $calc['retencion_porcentaje'] : null,
                'retencion' => $calc['retencion'] ?? null,
            ]);

            foreach ($calc['lineas'] ?? [] as $l) {
                DB::table('expense_iva_lineas')->insert($l + [
                    'expense_id' => $id, 'company_id' => $this->c->empresa, 'created_at' => $f, 'updated_at' => $f,
                ]);
            }
        }
    }

    /**
     * Lo que tiene que salir, calculado del plan: totales del año, por mes y
     * del IVA de los gastos. Se suma a lo de las facturas en EscenarioPruebas.
     */
    public function esperado(): array
    {
        $e = ['gastos' => 0, 'numero' => 0, 'base' => 0, 'iva' => 0, 'deducible' => 0, 'no_deducible' => 0,
              'autoliquidado' => 0, 'retencion' => 0, 'sin_desglose' => 0, 'meses' => []];

        foreach ($this->plan() as [$atras, $d, , , , , , $lineas, $ret, $sinDesglose]) {
            $m = $this->e->dia($atras, $d)->format('Y-m');
            $e['meses'][$m] ??= ['gastos' => 0, 'gastos_iva' => 0];
            $e['numero']++;

            if (! $lineas) {
                $e['gastos'] += $sinDesglose;
                $e['sin_desglose']++;
                $e['meses'][$m]['gastos'] += $sinDesglose;

                continue;
            }

            $calc = $this->calcular($lineas, $ret);
            $e['gastos'] += $calc['total'];
            $e['base'] += $calc['base_imponible'];
            $e['iva'] += $calc['cuota_iva'];
            $e['autoliquidado'] += $calc['cuota_autoliquidada'];
            $e['retencion'] += $calc['retencion'];
            foreach ($calc['lineas'] as $l) {
                if ($l['autoliquidacion']) {
                    continue;
                }
                $e[$l['deducible'] ? 'deducible' : 'no_deducible'] += $l['cuota'];
            }
            $e['meses'][$m]['gastos'] += $calc['total'];
            $e['meses'][$m]['gastos_iva'] += $calc['cuota_iva'];
        }

        return $e;
    }

    private function calcular(array $lineas, $ret): array
    {
        return IvaGastos::calcular(
            array_map(fn ($l) => ['tipo' => $l[0], 'base' => $l[1], 'deducible' => $l[2]], $lineas),
            $ret
        );
    }
}
