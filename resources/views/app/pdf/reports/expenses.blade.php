<!DOCTYPE html>
<html lang="en">

<head>
    <title>@lang('pdf_expense_report_label')</title>
    <style type="text/css">
        @font-face {
            font-family: 'Satoshi';
            font-style: normal;
            font-weight: normal;
            src: url("{{ resource_path('static/fonts/Satoshi-Regular.otf') }}") format('opentype');
        }
        @font-face {
            font-family: 'Satoshi';
            font-style: normal;
            font-weight: bold;
            src: url("{{ resource_path('static/fonts/Satoshi-Black.otf') }}") format('opentype');
        }
        /* -- Base -- */
        body {
            font-family: 'Satoshi', sans-serif !important;
        }

        table {
            border-collapse: collapse;
        }

        .sub-container {
            padding: 0px 20px;
        }

        .report-header {
            width: 100%;
        }

        .heading-text {

            font-weight: bold;
            font-size: 24px;
            color: #070322;
            width: 100%;
            text-align: left;
            padding: 0px;
            margin: 0px;
        }

        .heading-date-range {
            font-weight: normal;
            font-size: 15px;
            color: #A5ACC1;
            width: 100%;
            text-align: right;
            padding: 0px;
            margin: 0px;
        }

        .sub-heading-text {
            font-weight: normal;
            font-size: 16px;
            color: #595959;
            padding: 0px;
            margin: 0px;
            margin-top: 6px;
        }

        .expenses-title {
            margin-top: 60px;
            padding-left: 3px;
            font-size: 16px;
            line-height: 21px;
            color: #040405;
        }

        .expenses-table-container {
            padding-left: 10px;
        }

        .expenses-table {
            width: 100%;
            padding-bottom: 10px;
        }

        .expense-title {
            padding: 0px;
            margin: 0px;
            font-size: 14px;
            line-height: 21px;
            color: #595959;
        }

        .expense-amount {
            padding: 0px;
            margin: 0px;
            font-size: 14px;
            line-height: 21px;
            text-align: right;
            color: #595959;
        }

        .expense-total-table {
            border-top: 1px solid #e5e7eb;
            width: 100%;
        }

        .expense-total-cell {
            padding-right: 20px;
            padding-top: 10px;
        }

        .expense-total {
            padding-top: 10px;
            padding-right: 30px;
            padding: 0px;
            margin: 0px;
            text-align: right;
            font-weight: bold;
            font-size: 16px;
            line-height: 21px;
            text-align: right;
            color: #040405;
        }

        .report-footer {
            width: 100%;
            margin-top: 40px;
            padding: 15px 20px;
            background: #f0faf4;
            box-sizing: border-box;
        }

        .report-footer-label {
            padding: 0px;
            margin: 0px;
            text-align: left;
            font-weight: bold;
            font-size: 16px;
            line-height: 21px;
            color: #595959;
        }

        .report-footer-value {
            padding: 0px;
            margin: 0px;
            text-align: right;
            font-weight: bold;
            font-size: 20px;
            line-height: 21px;
            color: #38d587;
        }
        /* Onfactu v.1.15.0: base, IVA y total */
        .col-cifra { text-align: right; font-size: 13px; color: #595959; padding: 3px 6px; }
        .col-cab { text-align: right; font-size: 11px; color: #8a8f9c; font-weight: normal; padding: 4px 6px; border-bottom: 1px solid #e5e7eb; }
        .col-cab.izq { text-align: left; }
        .nota-gastos { font-size: 11px; color: #8a8f9c; margin: 8px 0 0 3px; }
    </style>

    @if (App::isLocale('th'))
    @include('app.pdf.locale.th')
    @endif
</head>

<body>
    <div class="sub-container">
        <table class="report-header">
            <tr>
                <td>
                    <p class="heading-text">{{ $company->name }}</p>
                </td>
                <td>
                    <p class="heading-date-range">{{ $from_date }} - {{ $to_date }}</p>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <p class="sub-heading-text">@lang('pdf_expense_report_label')</p>
                </td>
            </tr>
        </table>
        <p class="expenses-title">@lang('pdf_expenses_label')</p>
        <div class="expenses-table-container">
            <table class="expenses-table">
                <tr>
                    <th class="col-cab izq">Categoría</th>
                    <th class="col-cab">Base</th>
                    <th class="col-cab">IVA</th>
                    <th class="col-cab">Total</th>
                </tr>
                @foreach ($expenseCategories as $expenseCategory)
                @php $d = $desgloseCategorias[$expenseCategory->expense_category_id] ?? null; @endphp
                <tr>
                    <td>
                        <p class="expense-title">
                            {{ $expenseCategory->category->name }}
                        </p>
                    </td>
                    <td class="col-cifra">{!! $d ? format_money_pdf($d->base, $currency) : '-' !!}</td>
                    <td class="col-cifra">{!! $d ? format_money_pdf($d->iva, $currency) : '-' !!}</td>
                    <td>
                        <p class="expense-amount">
                            {!! format_money_pdf($expenseCategory->total_amount, $currency) !!}
                        </p>
                    </td>
                </tr>
                @endforeach
                <tr>
                    <td class="col-cab izq"></td>
                    <td class="col-cifra"><strong>{!! format_money_pdf($totalBase, $currency) !!}</strong></td>
                    <td class="col-cifra"><strong>{!! format_money_pdf($totalIva, $currency) !!}</strong></td>
                    <td></td>
                </tr>
            </table>
            <p class="nota-gastos">Base e IVA de los gastos con el IVA desglosado. El total incluye todos, con la retención ya descontada.@if ($sinDesglose->numero > 0) Hay {{ $sinDesglose->numero }} {{ $sinDesglose->numero == 1 ? 'gasto' : 'gastos' }} sin desglose por {!! format_money_pdf($sinDesglose->importe, $currency) !!}, que solo cuentan en el total.@endif</p>
        </div>
    </div>

    <table class="expense-total-table">
        <tr>
            <td class="expense-total-cell">
                <p class="expense-total">{!! format_money_pdf($totalExpense, $currency) !!}</p>
            </td>
        </tr>
    </table>
    <table class="report-footer">
        <tr>
            <td>
                <p class="report-footer-label">@lang('pdf_total_expenses_label')</p>
            </td>
            <td>
                <p class="report-footer-value">{!! format_money_pdf($totalExpense, $currency) !!}</p>
            </td>
        </tr>
    </table>
</body>

</html>