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
        $customers = Customer::with(['invoices' => function ($q) use ($from, $to) {
            $q->whereBetween('invoice_date', [$from->format('Y-m-d'), $to->format('Y-m-d')]);
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
            ->whereBetween('invoice_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->get();

        $itemTotals = [];
        foreach ($invoices as $inv) {
            foreach ($inv->items as $item) {
                $name = $item->name;
                if (!isset($itemTotals[$name])) {
                    $itemTotals[$name] = ['qty' => 0, 'amount' => 0];
                }
                $itemTotals[$name]['qty'] += $item->quantity;
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
    private function expenses(Company $company, Carbon $from, Carbon $to, string $symbol)
    {
        $expenses = Expense::with('category')
            ->where('company_id', $company->id)
            ->whereBetween('expense_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->get();

        return $this->streamCsv('gastos.csv', function ($h) use ($expenses, $company, $from, $to, $symbol) {
            fputcsv($h, [$company->name], ';');
            fputcsv($h, ['Informe de gastos'], ';');
            fputcsv($h, [$from->format('d/m/Y') . ' - ' . $to->format('d/m/Y')], ';');
            fputcsv($h, [], ';');
            fputcsv($h, ['Categoría', 'Fecha', 'Descripción', 'Importe (' . $symbol . ')'], ';');

            $total = 0;
            foreach ($expenses as $exp) {
                $catName = $exp->category->name ?? 'Sin categoría';
                fputcsv($h, [
                    $catName,
                    Carbon::parse($exp->expense_date)->format('d/m/Y'),
                    $exp->notes ?? '',
                    $this->fmt($exp->base_amount),
                ], ';');
                $total += $exp->base_amount;
            }
            fputcsv($h, [], ';');
            fputcsv($h, ['', '', 'TOTAL', $this->fmt($total)], ';');
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
    private function taxSummary(Company $company, Carbon $from, Carbon $to, string $symbol)
    {
        $taxTypes = TaxType::with(['taxes' => function ($q) use ($from, $to, $company) {
            $q->whereHas('invoice', function ($iq) use ($from, $to, $company) {
                $iq->where('company_id', $company->id)
                    ->whereBetween('invoice_date', [$from->format('Y-m-d'), $to->format('Y-m-d')]);
            });
        }])
            ->where('company_id', $company->id)
            ->get();

        return $this->streamCsv('resumen-impuestos.csv', function ($h) use ($taxTypes, $company, $from, $to, $symbol) {
            fputcsv($h, [$company->name], ';');
            fputcsv($h, ['Resumen de impuestos'], ';');
            fputcsv($h, [$from->format('d/m/Y') . ' - ' . $to->format('d/m/Y')], ';');
            fputcsv($h, [], ';');
            fputcsv($h, ['Impuesto', 'Porcentaje', 'Importe recaudado (' . $symbol . ')'], ';');

            foreach ($taxTypes as $tt) {
                $totalTax = 0;
                foreach ($tt->taxes as $tax) {
                    $totalTax += $tax->amount;
                }
                if ($totalTax > 0) {
                    fputcsv($h, [$tt->name, $tt->percent . '%', $this->fmt($totalTax)], ';');
                }
            }
        });
    }
}
