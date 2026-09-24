<?php

namespace App\Services;

use App\Models\ClosedMonth;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Onfactu — Qué se entrega a la gestoría al cerrar un mes.
 *
 * Todo lo que decide qué documentos cuentan vive aquí, para que el resumen del
 * cierre, el CSV del cliente y el recálculo de cierres antiguos usen la misma
 * regla. El portal de gestorías aplica la misma en su lib/detalle.php.
 *
 * LA REGLA: se entregan todas las facturas EMITIDAS del mes (cualquier estado
 * salvo borrador) y todos los gastos. Una factura enviada y pendiente de cobro
 * ya está emitida: su IVA se declara en el periodo de su fecha, se cobre o no.
 * Hasta la v.1.12.0 solo se entregaban las "Completadas" (cobradas), y la
 * gestoría no veía las pendientes de cobro.
 */
class CierreMes
{
    private const MESES = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    public static function nombreMes(int $m, bool $mayuscula = false): string
    {
        $n = self::MESES[$m] ?? (string) $m;

        return $mayuscula ? mb_strtoupper(mb_substr($n, 0, 1)).mb_substr($n, 1) : $n;
    }

    /** @return array{0: Carbon, 1: Carbon} primer y último instante del mes */
    public static function periodo(int $year, int $month): array
    {
        $ini = Carbon::create($year, $month, 1)->startOfDay();

        return [$ini, $ini->copy()->endOfMonth()->endOfDay()];
    }

    /**
     * Resumen de lo que se entrega y de lo que queda fuera. Alimenta el modal
     * de confirmación y se congela en el cierre.
     */
    public static function resumen(int $companyId, int $year, int $month): array
    {
        [$ini, $fin] = self::periodo($year, $month);

        $facturas = Invoice::where('company_id', $companyId)
            ->whereBetween('invoice_date', [$ini, $fin])
            ->where('status', '!=', Invoice::STATUS_DRAFT)
            ->get(['sub_total', 'tax', 'total', 'rectifies_invoice_id']);

        $rectificativas = $facturas->whereNotNull('rectifies_invoice_id');

        $gastos = DB::table('expenses')
            ->where('company_id', $companyId)
            ->whereBetween('expense_date', [$ini, $fin])
            ->get(['amount']);

        // Lo único que queda fuera son las facturas en borrador: sin número y
        // sin emitir. Presupuestos, proformas y albaranes no son documentos
        // fiscales y no se entregan nunca, así que no se avisa de ellos.
        $borradores = Invoice::where('company_id', $companyId)
            ->whereBetween('invoice_date', [$ini, $fin])
            ->where('status', Invoice::STATUS_DRAFT)
            ->count();

        return [
            'year'   => $year,
            'month'  => $month,
            'nombre' => self::nombreMes($month).' de '.$year,
            'totales' => [
                'facturas'               => $facturas->count(),
                'neto'                   => (int) $facturas->sum('sub_total'),
                'iva'                    => (int) $facturas->sum('tax'),
                'bruto'                  => (int) $facturas->sum('total'),
                'rectificativas'         => $rectificativas->count(),
                'importe_rectificativas' => (int) $rectificativas->sum('total'),
                'gastos'                 => $gastos->count(),
                'importe_gastos'         => (int) $gastos->sum('amount'),
            ],
            'borradores'       => $borradores ? [['tipo' => $borradores === 1 ? 'factura' : 'facturas', 'total' => $borradores]] : [],
            'tiene_borradores' => $borradores > 0,
        ];
    }

    /**
     * Los documentos entregados, uno por fila, en el mismo formato que el CSV
     * que descarga la gestoría desde su portal.
     */
    public static function csv(int $companyId, int $year, int $month): string
    {
        [$ini, $fin] = self::periodo($year, $month);
        $filas = [];

        $facturas = DB::table('invoices as i')
            ->leftJoin('customers as c', 'c.id', '=', 'i.customer_id')
            ->leftJoin('invoices as o', 'o.id', '=', 'i.rectifies_invoice_id')
            ->where('i.company_id', $companyId)
            ->whereBetween('i.invoice_date', [$ini, $fin])
            ->where('i.status', '!=', Invoice::STATUS_DRAFT)
            ->orderBy('i.invoice_date')->orderBy('i.invoice_number')
            ->get(['i.invoice_date as fecha', 'i.invoice_number as numero', 'i.sub_total as neto',
                   'i.tax as iva', 'i.total as bruto', 'i.rectifies_invoice_id',
                   'c.name as cliente', 'c.tax_id as nif', 'o.invoice_number as rectifica_a']);

        foreach ($facturas as $f) {
            $filas[] = [
                'fecha' => $f->fecha,
                'tipo' => $f->rectifies_invoice_id ? 'Rectificativa' : 'Factura',
                'numero' => $f->numero,
                'texto' => trim(($f->cliente ?? '').($f->rectifica_a ? ' Rectifica a '.$f->rectifica_a : '')),
                'nif' => $f->nif ?? '',
                'neto' => (int) $f->neto, 'iva' => (int) $f->iva, 'bruto' => (int) $f->bruto,
            ];
        }

        $gastos = DB::table('expenses as e')
            ->leftJoin('expense_categories as cat', 'cat.id', '=', 'e.expense_category_id')
            ->leftJoin('customers as c', 'c.id', '=', 'e.customer_id')
            ->where('e.company_id', $companyId)
            ->whereBetween('e.expense_date', [$ini, $fin])
            ->orderBy('e.expense_date')
            ->get(['e.expense_date as fecha', 'e.amount as bruto', 'e.notes', 'cat.name as categoria', 'c.name as proveedor']);

        foreach ($gastos as $g) {
            $filas[] = [
                'fecha' => $g->fecha, 'tipo' => 'Gasto', 'numero' => '—',
                'texto' => trim(($g->proveedor ?: ($g->categoria ?? '')).' '.($g->notes ?? '')),
                'nif' => '',
                // Los gastos no tienen desglose de IVA en Onfactu: solo el total
                'neto' => null, 'iva' => null, 'bruto' => (int) $g->bruto,
            ];
        }

        usort($filas, fn ($a, $b) => strcmp((string) $a['fecha'], (string) $b['fecha']));

        // Separador punto y coma, BOM UTF-8 y coma decimal: lo que espera
        // Excel en español y lo que importan los programas de contabilidad.
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Fecha', 'Tipo', 'Numero', 'Cliente o concepto', 'NIF', 'Neto', 'IVA', 'Bruto'], ';');
        foreach ($filas as $f) {
            fputcsv($out, [
                Carbon::parse($f['fecha'])->format('d/m/Y'), $f['tipo'], $f['numero'], $f['texto'], $f['nif'],
                self::importe($f['neto']), self::importe($f['iva']), self::importe($f['bruto']),
            ], ';');
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    public static function nombreFichero(int $companyId, int $year, int $month): string
    {
        $empresa = (string) DB::table('companies')->where('id', $companyId)->value('name');

        return sprintf('%s_%d_%s.csv', preg_replace('/[^a-z0-9\-]/i', '-', $empresa ?: 'onfactu'), $year, self::nombreMes($month));
    }

    /**
     * Reintenta la entrega a la central de los cierres que no llegaron.
     * Devuelve cuántos se han entregado ahora.
     */
    public static function reenviarPendientes(?int $companyId = null): int
    {
        try {
            $v = GestoriaService::vinculacion();
        } catch (\Throwable $e) {
            return 0; // la central no responde: se reintentará la próxima vez
        }
        if (! $v || $v->estado !== 'aceptada') {
            return 0; // sin gestoría aceptada no hay a quién entregar
        }

        $q = ClosedMonth::pending();
        if ($companyId) {
            $q->where('company_id', $companyId);
        }

        $entregados = 0;
        foreach ($q->orderBy('year')->orderBy('month')->get() as $c) {
            $ok = GestoriaService::registrarCierre($c->year, $c->month, $c->totals ?? [], $c->closed_at);
            $c->update([
                'sent_status'   => $ok ? 'sent' : 'failed',
                'sent_at'       => $ok ? now() : null,
                'sent_error'    => $ok ? null : 'No se pudo registrar en la BD central',
                'sent_attempts' => (int) $c->sent_attempts + 1,
            ]);
            $entregados += $ok ? 1 : 0;
        }

        return $entregados;
    }

    private static function importe(?int $centimos): string
    {
        return $centimos === null ? '' : number_format($centimos / 100, 2, ',', '');
    }
}
