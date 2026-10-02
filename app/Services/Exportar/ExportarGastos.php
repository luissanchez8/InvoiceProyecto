<?php

namespace App\Services\Exportar;

use App\Models\Expense;
use Carbon\Carbon;

/**
 * Onfactu v.1.18.0 — Los gastos de la lista, con los mismos filtros que la
 * pantalla (categoría, proveedor y fechas), para exportarlos a Excel o PDF.
 * Mismas columnas y cálculo que el Excel de gastos de Informes: los gastos
 * sin desglose solo tienen el total.
 */
class ExportarGastos
{
    public const MAXIMO = 5000;

    public static function titulo(): string
    {
        return 'Gastos';
    }

    public static function cabecera(): array
    {
        return ['Fecha', 'Categoría', 'Proveedor', 'NIF', 'Nº factura', 'Base', 'IVA', 'Retención', 'Total'];
    }

    public static function importes(): array
    {
        return [5, 6, 7, 8];
    }

    public static function filas(array $filtros): array
    {
        $gastos = Expense::with('category')
            ->whereCompany()
            ->leftJoin('customers', 'customers.id', '=', 'expenses.customer_id')
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->applyFilters($filtros)
            ->when(empty($filtros['orderByField']), fn ($q) => $q->orderBy('expenses.expense_date', 'desc')->orderBy('expenses.id', 'desc'))
            ->select('expenses.*')
            ->limit(self::MAXIMO)
            ->get();

        return $gastos->map(function (Expense $g) {
            $cambio = (float) ($g->exchange_rate ?: 1);
            $c = fn ($v) => (int) round(((int) $v) * $cambio);
            $desglose = (bool) $g->con_desglose;

            return [
                Carbon::parse($g->expense_date)->format('d/m/Y'),
                $g->category->name ?? 'Sin categoría',
                $g->proveedor_nombre ?? '',
                $g->proveedor_nif ?? '',
                $g->numero_factura ?? '',
                $desglose ? $c($g->base_imponible) : null,
                $desglose ? $c($g->cuota_iva) : null,
                $desglose ? $c($g->retencion) : null,
                (int) $g->base_amount,
            ];
        })->all();
    }
}
