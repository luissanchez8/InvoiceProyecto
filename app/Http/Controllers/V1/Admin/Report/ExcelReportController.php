<?php

namespace App\Http\Controllers\V1\Admin\Report;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\TaxType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelReportController extends Controller
{
    public function __invoke(Request $request, string $type, string $hash)
    {
        $company = Company::where('unique_hash', $hash)->first();
        if (!$company) {
            abort(404);
        }

        $this->authorize('view report', $company);

        $from = Carbon::createFromFormat('Y-m-d', $request->from_date);
        $to = Carbon::createFromFormat('Y-m-d', $request->to_date);
        $currency = Currency::findOrFail(CompanySetting::getSetting('currency', $company->id));
        $symbol = $currency->symbol ?? '€';

        return match ($type) {
            'sales-customers' => $this->salesByCustomer($company, $from, $to, $symbol),
            'sales-items'     => $this->salesByItem($company, $from, $to, $symbol),
            'expenses'        => $this->expenses($company, $from, $to, $symbol),
            'profit-loss'     => $this->profitLoss($company, $from, $to, $symbol),
            'tax-summary'     => $this->taxSummary($company, $from, $to, $symbol),
            default           => abort(404),
        };
    }

    private function streamCsv(string $filename, callable $writer): StreamedResponse
    {
        return new StreamedResponse(function () use ($writer) {
            $handle = fopen('php://output', 'w');
            // BOM para que Excel interprete UTF-8 correctamente
            fwrite($handle, "\xEF\xBB\xBF");
            $writer($handle);
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function fmt($amount): string
    {
        return number_format($amount / 100, 2, ',', '.');
    }

    // ── Ventas por cliente ──────────────────────────────────────────────
    private function salesByCustomer(Company $company, Carbon $from, Carbon $to, string $symbol)
    {
        // Onfactu v.1.14.5: solo facturas aprobadas
        $customers = Customer::with(['invoices' => function ($q) use ($from, $to) {
            $q->where('status', Invoice::STATUS_APPROVED)
                ->whereBetween('invoice_date', [$from->format('Y-m-d'), $to->format('Y-m-d')]);
        }])
            ->where('company_id', $company->id)
            ->get();

        return $this->streamCsv('ventas-por-cliente.csv', function ($h) use ($customers, $company, $from, $to, $symbol) {
            fputcsv($h, [$company->name], ';');
            fputcsv($h, ['Informe de ventas por cliente'], ';');
            fputcsv($h, [$from->format('d/m/Y') . ' - ' . $to->format('d/m/Y')], ';');
            fputcsv($h, [], ';');
            fputcsv($h, ['Cliente', 'Fecha', 'Número', 'Importe (' . $symbol . ')'], ';');

            $total = 0;
            foreach ($customers as $customer) {
                foreach ($customer->invoices as $inv) {
                    $amt = $inv->base_total;
                    fputcsv($h, [
                        $customer->name,
                        Carbon::parse($inv->invoice_date)->format('d/m/Y'),
                        $inv->invoice_number,
                        $this->fmt($amt),
                    ], ';');
                    $total += $amt;
                }
            }
            fputcsv($h, [], ';');
            fputcsv($h, ['', '', 'TOTAL', $this->fmt($total)], ';');
        });
    }

    // ── Ventas por artículo ─────────────────────────────────────────────
    private function salesByItem(Company $company, Carbon $from, Carbon $to, string $symbol)
    {
        $invoices = Invoice::with('items')
            ->where('company_id', $company->id)
            ->where('status', Invoice::STATUS_APPROVED)
            ->whereBetween('invoice_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->get();

        $itemTotals = [];
        foreach ($invoices as $inv) {
            foreach ($inv->items as $item) {
                $name = $item->name;
                if (!isset($itemTotals[$name])) {
                    $itemTotals[$name] = ['qty' => 0, 'amount' => 0];
                }
                // Onfactu v.1.18.0: con el signo del importe (ver InvoiceItem::scopeItemAttributes)
                $itemTotals[$name]['qty'] += $item->total < 0 ? -abs($item->quantity) : ($item->total > 0 ? abs($item->quantity) : $item->quantity);
                $itemTotals[$name]['amount'] += $item->total;
            }
        }

        return $this->streamCsv('ventas-por-articulo.csv', function ($h) use ($itemTotals, $company, $from, $to, $symbol) {
            fputcsv($h, [$company->name], ';');
            fputcsv($h, ['Informe de ventas por artículo'], ';');
            fputcsv($h, [$from->format('d/m/Y') . ' - ' . $to->format('d/m/Y')], ';');
            fputcsv($h, [], ';');
            fputcsv($h, ['Artículo', 'Cantidad', 'Importe (' . $symbol . ')'], ';');

            $total = 0;
            foreach ($itemTotals as $name => $data) {
                fputcsv($h, [$name, $data['qty'], $this->fmt($data['amount'])], ';');
                $total += $data['amount'];
            }
            fputcsv($h, [], ';');
            fputcsv($h, ['', 'TOTAL', $this->fmt($total)], ';');
        });
    }

    // ── Gastos ──────────────────────────────────────────────────────────
    // Onfactu v.1.15.0: con proveedor, base, IVA y retención. Los gastos sin
    // desglose solo tienen el total, y lo dicen.
    private function expenses(Company $company, Carbon $from, Carbon $to, string $symbol)
    {
        $expenses = Expense::with('category')
            ->where('company_id', $company->id)
            ->whereBetween('expense_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->orderBy('expense_date')->orderBy('id')
            ->get();

        return $this->streamCsv('gastos.csv', function ($h) use ($expenses, $company, $from, $to, $symbol) {
            fputcsv($h, [$company->name], ';');
            fputcsv($h, ['Informe de gastos'], ';');
            fputcsv($h, [$from->format('d/m/Y') . ' - ' . $to->format('d/m/Y')], ';');
            fputcsv($h, [], ';');
            fputcsv($h, ['Fecha', 'Categoría', 'Proveedor', 'NIF', 'Nº factura', 'Descripción',
                'Base (' . $symbol . ')', 'IVA (' . $symbol . ')', 'Retención (' . $symbol . ')', 'Total (' . $symbol . ')', 'Desglose'], ';');

            $t = ['base' => 0, 'iva' => 0, 'ret' => 0, 'total' => 0];
            foreach ($expenses as $exp) {
                $cambio = (float) ($exp->exchange_rate ?: 1);
                $conv = fn ($v) => (int) round(((int) $v) * $cambio);
                $desglose = (bool) $exp->con_desglose;
                fputcsv($h, [
                    Carbon::parse($exp->expense_date)->format('d/m/Y'),
                    $exp->category->name ?? 'Sin categoría',
                    $exp->proveedor_nombre ?? '',
                    $exp->proveedor_nif ?? '',
                    $exp->numero_factura ?? '',
                    $exp->notes ?? '',
                    $desglose ? $this->fmt($conv($exp->base_imponible)) : '',
                    $desglose ? $this->fmt($conv($exp->cuota_iva)) : '',
                    $desglose ? $this->fmt($conv($exp->retencion)) : '',
                    $this->fmt($exp->base_amount),
                    $desglose ? 'Sí' : 'Sin desglose',
                ], ';');
                if ($desglose) {
                    $t['base'] += $conv($exp->base_imponible);
                    $t['iva'] += $conv($exp->cuota_iva);
                    $t['ret'] += $conv($exp->retencion);
                }
                $t['total'] += $exp->base_amount;
            }
            fputcsv($h, [], ';');
            fputcsv($h, ['', '', '', '', '', 'TOTAL', $this->fmt($t['base']), $this->fmt($t['iva']), $this->fmt($t['ret']), $this->fmt($t['total']), ''], ';');
        });
    }

    // ── Pérdidas y ganancias ────────────────────────────────────────────
    private function profitLoss(Company $company, Carbon $from, Carbon $to, string $symbol)
    {
        $income = Payment::where('company_id', $company->id)
            ->applyFilters(['from_date' => $from->format('Y-m-d'), 'to_date' => $to->format('Y-m-d')])
            ->sum('base_amount');

        $expenseCategories = Expense::with('category')
            ->where('company_id', $company->id)
            ->applyFilters(['from_date' => $from->format('Y-m-d'), 'to_date' => $to->format('Y-m-d')])
            ->expensesAttributes()
            ->get();

        return $this->streamCsv('perdidas-y-ganancias.csv', function ($h) use ($income, $expenseCategories, $company, $from, $to, $symbol) {
            fputcsv($h, [$company->name], ';');
            fputcsv($h, ['Informe de pérdidas y ganancias'], ';');
            fputcsv($h, [$from->format('d/m/Y') . ' - ' . $to->format('d/m/Y')], ';');
            fputcsv($h, [], ';');

            fputcsv($h, ['Concepto', 'Importe (' . $symbol . ')'], ';');
            fputcsv($h, ['Ingresos', $this->fmt($income)], ';');
            fputcsv($h, [], ';');

            fputcsv($h, ['Gastos por categoría', ''], ';');
            $totalExpense = 0;
            foreach ($expenseCategories as $cat) {
                fputcsv($h, ['  ' . ($cat->category_name ?? 'Sin categoría'), $this->fmt($cat->total_amount)], ';');
                $totalExpense += $cat->total_amount;
            }
            fputcsv($h, [], ';');
            fputcsv($h, ['Total gastos', $this->fmt($totalExpense)], ';');
            fputcsv($h, ['Beneficio neto', $this->fmt($income - $totalExpense)], ';');
        });
    }

    // ── Resumen de impuestos ────────────────────────────────────────────
    // Onfactu v.1.15.0: lo mismo que el PDF (App\Services\ResumenIva): IVA de
    // las ventas, de los gastos, autoliquidación, resultado y retenciones.
    private function taxSummary(Company $company, Carbon $from, Carbon $to, string $symbol)
    {
        $r = \App\Services\ResumenIva::calcular($company->id, $from, $to);

        return $this->streamCsv('resumen-impuestos.csv', function ($h) use ($r, $company, $from, $to, $symbol) {
            $f = fn ($v) => $this->fmt($v);
            fputcsv($h, [$company->name], ';');
            fputcsv($h, ['Resumen de impuestos'], ';');
            fputcsv($h, [$from->format('d/m/Y') . ' - ' . $to->format('d/m/Y')], ';');

            fputcsv($h, [], ';');
            fputcsv($h, ['IVA de las ventas'], ';');
            fputcsv($h, ['Impuesto', 'Porcentaje', 'Base (' . $symbol . ')', 'Cuota (' . $symbol . ')'], ';');
            foreach ($r['ventas'] as $v) {
                fputcsv($h, [$v['nombre'], $this->pct($v['porcentaje']), $f($v['base']), $f($v['cuota'])], ';');
            }
            fputcsv($h, ['Total', '', $f($r['ventas_base']), $f($r['ventas_cuota'])], ';');

            fputcsv($h, [], ';');
            fputcsv($h, ['IVA de los gastos'], ';');
            fputcsv($h, ['Tipo', 'Porcentaje', 'Base (' . $symbol . ')', 'Cuota (' . $symbol . ')', 'Deducible (' . $symbol . ')', 'No deducible (' . $symbol . ')'], ';');
            foreach ($r['gastos'] as $g) {
                fputcsv($h, [$g['nombre'], $this->pct($g['porcentaje']), $f($g['base']), $f($g['cuota']), $f($g['deducible']), $f($g['no_deducible'])], ';');
            }
            fputcsv($h, ['Total', '', $f($r['gastos_base']), $f($r['gastos_cuota']), $f($r['gastos_deducible']), $f($r['gastos_no_deducible'])], ';');
            if ($r['sin_desglose']['numero'] > 0) {
                fputcsv($h, ['Gastos sin IVA desglosado (no incluidos)', $r['sin_desglose']['numero'], '', '', '', $f($r['sin_desglose']['importe'])], ';');
            }

            if (count($r['autoliquidacion'])) {
                fputcsv($h, [], ';');
                fputcsv($h, ['Compras intracomunitarias (autoliquidación)'], ';');
                fputcsv($h, ['Tipo', 'Porcentaje', 'Base (' . $symbol . ')', 'Cuota (' . $symbol . ')', 'Deducible (' . $symbol . ')'], ';');
                foreach ($r['autoliquidacion'] as $a) {
                    fputcsv($h, [$a['nombre'], $this->pct($a['porcentaje']), $f($a['base']), $f($a['cuota']), $f($a['deducible'])], ';');
                }
            }

            fputcsv($h, [], ';');
            fputcsv($h, ['Resultado del IVA'], ';');
            fputcsv($h, ['IVA de las ventas', $f($r['ventas_cuota'])], ';');
            if ($r['autoliquidacion_cuota']) {
                fputcsv($h, ['IVA autoliquidado', $f($r['autoliquidacion_cuota'])], ';');
            }
            fputcsv($h, ['IVA deducible de los gastos', $f(-$r['iva_deducible'])], ';');
            fputcsv($h, [$r['resultado'] >= 0 ? 'A pagar' : 'A compensar o devolver', $f(abs($r['resultado']))], ';');

            if (count($r['retenciones_ventas']) || count($r['retenciones_gastos'])) {
                fputcsv($h, [], ';');
                fputcsv($h, ['Retenciones de IRPF (no entran en el IVA)'], ';');
                fputcsv($h, ['Concepto', 'Base (' . $symbol . ')', 'Importe (' . $symbol . ')'], ';');
                foreach ($r['retenciones_ventas'] as $rv) {
                    fputcsv($h, ['Te han retenido tus clientes (' . $rv['nombre'] . ')', $f($rv['base']), $f(abs($rv['cuota']))], ';');
                }
                foreach ($r['retenciones_gastos'] as $rg) {
                    fputcsv($h, ['Has retenido a tus proveedores (' . $this->pct($rg['porcentaje']) . ')', $f($rg['base']), $f($rg['importe'])], ';');
                }
            }
        });
    }

    private function pct($p): string
    {
        return rtrim(rtrim(number_format((float) $p, 2, ',', ''), '0'), ',') . ' %';
    }
}
