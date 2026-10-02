<?php

namespace App\Services\Exportar;

use App\Models\Invoice;
use App\Services\CierreMes;
use Carbon\Carbon;

/**
 * Onfactu v.1.18.0 — Las facturas de la lista, con los mismos filtros que la
 * pantalla (estado o pestaña, cliente, fechas y número), para exportarlas a
 * Excel o PDF (ExportarListadoController).
 *
 * Importes en la moneda de la empresa (importe × tipo de cambio), como los
 * informes. IVA y retención por separado, con la misma consulta que el cierre
 * de mes y el portal de gestorías (CierreMes::impuestosPorFactura).
 */
class ExportarFacturas
{
    public const MAXIMO = 5000;

    public static function titulo(): string
    {
        return 'Facturas';
    }

    public static function cabecera(): array
    {
        return ['Fecha', 'Número', 'Cliente', 'NIF', 'Estado', 'Base', 'IVA', 'Retención', 'Total', 'Pendiente'];
    }

    /** Columnas con importes (índices de cabecera()), para alinearlas y sumarlas. */
    public static function importes(): array
    {
        return [5, 6, 7, 8, 9];
    }

    /** @return array<int, array> filas con los importes en céntimos */
    public static function filas(array $filtros): array
    {
        $facturas = Invoice::whereCompany()
            ->applyFilters($filtros)
            ->with('customer')
            ->limit(self::MAXIMO)
            ->get();

        $impuestos = CierreMes::impuestosPorFactura()
            ->whereIn('invoice_id', $facturas->pluck('id'))
            ->get()->keyBy('invoice_id');

        return $facturas->map(function (Invoice $f) use ($impuestos) {
            $cambio = (float) ($f->exchange_rate ?: 1);
            $c = fn ($v) => (int) round(((int) $v) * $cambio);
            $imp = $impuestos[$f->id] ?? null;
            $neto = (int) $f->sub_total - (int) ($f->discount_val ?? 0);

            return [
                Carbon::parse($f->invoice_date)->format('d/m/Y'),
                $f->invoice_number ?: 'Borrador',
                $f->customer?->name ?? '',
                $f->customer?->tax_id ?? '',
                self::estado($f),
                $c($neto),
                $c($imp ? $imp->iva : $f->tax),
                $c($imp ? $imp->retencion : 0),
                $c($f->total),
                $f->status === Invoice::STATUS_DRAFT ? 0 : $c($f->due_amount),
            ];
        })->all();
    }

    private static function estado(Invoice $f): string
    {
        if ($f->status === Invoice::STATUS_DRAFT) {
            return 'Borrador';
        }
        if ($f->rectifies_invoice_id) {
            return 'Rectificativa';
        }

        return match ($f->paid_status) {
            Invoice::STATUS_PAID => 'Cobrada',
            Invoice::STATUS_PARTIALLY_PAID => 'Cobro parcial',
            default => 'Pendiente de cobro',
        };
    }
}
