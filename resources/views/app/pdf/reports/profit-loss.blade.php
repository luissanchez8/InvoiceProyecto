{{-- Onfactu v.1.15.2: diseño común en layout.blade.php --}}
@extends('app.pdf.reports.layout')

@section('titulo', __('pdf_profit_loss_label'))

@section('contenido')
    <p class="seccion">{{ __('pdf_income_label') }}</p>
    <table class="tabla">
        <tr><th>Concepto</th><th class="num">Importe</th></tr>
        <tr>
            <td>Cobros recibidos</td>
            <td class="num">{!! format_money_pdf($income, $currency) !!}</td>
        </tr>
    </table>

    <p class="seccion">{{ __('pdf_expenses_label') }}</p>
    <table class="tabla">
        <tr><th>Categoría</th><th class="num">Importe</th></tr>
        @foreach ($expenseCategories as $expenseCategory)
        <tr>
            <td>{{ $expenseCategory->category->name }}</td>
            <td class="num">{!! format_money_pdf($expenseCategory->total_amount, $currency) !!}</td>
        </tr>
        @endforeach
        <tr class="total">
            <td>Total</td>
            <td class="num">{!! format_money_pdf($totalExpense, $currency) !!}</td>
        </tr>
    </table>
@endsection

@section('pie_etiqueta', __('pdf_net_profit_label'))
@section('pie_valor'){!! format_money_pdf($income - $totalExpense, $currency) !!}@endsection
