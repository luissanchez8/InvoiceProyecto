<?php

namespace App\Services;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Onfactu v.1.15.0 — IVA de un periodo: el de las ventas, el de los gastos y
 * el resultado. Lo usan el informe de impuestos en PDF y en Excel, para que
 * los dos den siempre lo mismo.
 *
 * Ventas: las facturas aprobadas del periodo por su fecha, cobradas o no. Las
 * rectificativas restan. Los impuestos de porcentaje negativo son retenciones
 * de IRPF y van aparte: no son IVA.
 *
 * Gastos: los desglosados del periodo, por tipo de IVA. Lo no deducible se
 * enseña pero no resta. Los gastos sin desglose (los de antes de la v.1.15.0,
 * o sin IVA indicado) se cuentan aparte, porque no se sabe su IVA.
 *
 * Autoliquidación (compras intracomunitarias): su IVA suma en lo devengado y,
 * si es deducible, también en lo deducible, así que normalmente se compensa.
 *
 * Todo en céntimos y en la moneda de la empresa (importe x tipo de cambio).
 */
class ResumenIva
{
    public static function calcular(int $companyId, Carbon $desde, Carbon $hasta): array
    {
        $ventas = self::ventas($companyId, $desde, $hasta);
        $gastos = self::gastos($companyId, $desde, $hasta);

        $devengado = array_sum(array_column($ventas['iva'], 'cuota')) + array_sum(array_column($gastos['autoliquidacion'], 'cuota'));
        $deducible = array_sum(array_column($gastos['iva'], 'deducible')) + array_sum(array_column($gastos['autoliquidacion'], 'deducible'));

        return [
            'ventas' => $ventas['iva'],
            'ventas_base' => array_sum(array_column($ventas['iva'], 'base')),
            'ventas_cuota' => array_sum(array_column($ventas['iva'], 'cuota')),
            'retenciones_ventas' => $ventas['retenciones'],
            'gastos' => $gastos['iva'],
            'gastos_base' => array_sum(array_column($gastos['iva'], 'base')),
            'gastos_cuota' => array_sum(array_column($gastos['iva'], 'cuota')),
            'gastos_deducible' => array_sum(array_column($gastos['iva'], 'deducible')),
            'gastos_no_deducible' => array_sum(array_column($gastos['iva'], 'no_deducible')),
            'autoliquidacion' => $gastos['autoliquidacion'],
            'autoliquidacion_cuota' => array_sum(array_column($gastos['autoliquidacion'], 'cuota')),
            'sin_desglose' => $gastos['sin_desglose'],
            'retenciones_gastos' => $gastos['retenciones'],
            'iva_devengado' => $devengado,
            'iva_deducible' => $deducible,
            'resultado' => $devengado - $deducible,
        ];
    }

    private static function ventas(int $companyId, Carbon $desde, Carbon $hasta): array
    {
        $periodo = [$desde->format('Y-m-d'), $hasta->format('Y-m-d')];

        // Impuesto de la factura entera: la base es el subtotal menos el descuento
        $deFactura = DB::table('taxes as t')
            ->join('invoices as i', 'i.id', '=', 't.invoice_id')
            ->where('i.company_id', $companyId)
            ->where('i.status', Invoice::STATUS_APPROVED)
            ->whereBetween(DB::raw('i.invoice_date::date'), $periodo)
            ->select([
                't.name', 't.percent',
                DB::raw('SUM(ROUND((i.sub_total - COALESCE(i.discount_val, 0)) * COALESCE(i.exchange_rate, 1))) as base'),
                DB::raw('SUM(COALESCE(t.base_amount, t.amount)) as cuota'),
            ])
            ->groupBy('t.name', 't.percent');

        // Impuesto de cada línea: la base es el total de la línea
        $deLinea = DB::table('taxes as t')
            ->join('invoice_items as it', 'it.id', '=', 't.invoice_item_id')
            ->join('invoices as i', 'i.id', '=', 'it.invoice_id')
            ->where('i.company_id', $companyId)
            ->where('i.status', Invoice::STATUS_APPROVED)
            ->whereBetween(DB::raw('i.invoice_date::date'), $periodo)
            ->select([
                't.name', 't.percent',
                DB::raw('SUM(ROUND(it.total * COALESCE(i.exchange_rate, 1))) as base'),
                DB::raw('SUM(COALESCE(t.base_amount, t.amount)) as cuota'),
            ])
            ->groupBy('t.name', 't.percent');

        $grupos = [];
        foreach ($deFactura->get()->concat($deLinea->get()) as $f) {
            $clave = $f->name.'|'.$f->percent;
            $grupos[$clave] ??= ['nombre' => $f->name, 'porcentaje' => (float) $f->percent, 'base' => 0, 'cuota' => 0];
            $grupos[$clave]['base'] += (int) $f->base;
            $grupos[$clave]['cuota'] += (int) $f->cuota;
        }

        $iva = $retenciones = [];
        foreach ($grupos as $g) {
            if ($g['base'] == 0 && $g['cuota'] == 0) {
                continue;
            }
            if ($g['porcentaje'] < 0) {
                $retenciones[] = $g;
            } else {
                $iva[] = $g;
            }
        }
        usort($iva, fn ($a, $b) => $b['porcentaje'] <=> $a['porcentaje'] ?: strcmp($a['nombre'], $b['nombre']));

        return ['iva' => $iva, 'retenciones' => $retenciones];
    }

    private static function gastos(int $companyId, Carbon $desde, Carbon $hasta): array
    {
        $periodo = [$desde->format('Y-m-d'), $hasta->format('Y-m-d')];
        $cambio = 'COALESCE(e.exchange_rate, 1)';

        $lineas = DB::table('expense_iva_lineas as l')
            ->join('expenses as e', 'e.id', '=', 'l.expense_id')
            ->where('e.company_id', $companyId)
            ->where('e.con_desglose', true)
            ->whereBetween('e.expense_date', $periodo)
            ->select([
                'l.tipo', 'l.porcentaje', 'l.autoliquidacion',
                DB::raw("SUM(ROUND(l.base * $cambio)) as base"),
                DB::raw("SUM(ROUND(l.cuota * $cambio)) as cuota"),
                DB::raw("SUM(CASE WHEN l.deducible THEN ROUND(l.cuota * $cambio) ELSE 0 END) as deducible"),
            ])
            ->groupBy('l.tipo', 'l.porcentaje', 'l.autoliquidacion')
            ->orderByDesc('l.porcentaje')
            ->get();

        $iva = $autoliquidacion = [];
        foreach ($lineas as $l) {
            $fila = [
                'nombre' => \App\Support\IvaGastos::nombre($l->tipo),
                'porcentaje' => (float) $l->porcentaje,
                'base' => (int) $l->base,
                'cuota' => (int) $l->cuota,
                'deducible' => (int) $l->deducible,
                'no_deducible' => (int) $l->cuota - (int) $l->deducible,
            ];
            if ($l->autoliquidacion) {
                $autoliquidacion[] = $fila;
            } else {
                $iva[] = $fila;
            }
        }

        $sinDesglose = DB::table('expenses')
            ->where('company_id', $companyId)
            ->where(fn ($q) => $q->where('con_desglose', false)->orWhereNull('con_desglose'))
            ->whereBetween('expense_date', $periodo)
            ->selectRaw('COUNT(*) as numero, COALESCE(SUM(base_amount), 0) as importe')
            ->first();

        $retenciones = DB::table('expenses as e')
            ->where('e.company_id', $companyId)
            ->where('e.con_desglose', true)
            ->where('e.retencion', '>', 0)
            ->whereBetween('e.expense_date', $periodo)
            ->selectRaw("e.retencion_porcentaje as porcentaje, SUM(ROUND(e.base_imponible * $cambio)) as base, SUM(ROUND(e.retencion * $cambio)) as importe")
            ->groupBy('e.retencion_porcentaje')
            ->orderBy('e.retencion_porcentaje')
            ->get()
            ->map(fn ($r) => ['porcentaje' => (float) $r->porcentaje, 'base' => (int) $r->base, 'importe' => (int) $r->importe])
            ->all();

        return [
            'iva' => $iva,
            'autoliquidacion' => $autoliquidacion,
            'sin_desglose' => ['numero' => (int) $sinDesglose->numero, 'importe' => (int) $sinDesglose->importe],
            'retenciones' => $retenciones,
        ];
    }
}
