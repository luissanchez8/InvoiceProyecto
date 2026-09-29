{{-- Onfactu v.1.15.2: diseño común en layout.blade.php --}}
@extends('app.pdf.reports.layout')

@section('titulo', __('pdf_item_sales_label'))

@section('contenido')
    <table class="tabla">
        <tr><th>{{ __('pdf_items_label') }}</th><th class="num">Importe</th></tr>
        @foreach ($items as $item)
        <tr>
            <td>{{ $item->name }}</td>
            <td class="num">{!! format_money_pdf($item->total_amount, $currency) !!}</td>
        </tr>
        @endforeach
        <tr class="total">
            <td>Total</td>
            <td class="num">{!! format_money_pdf($totalAmount, $currency) !!}</td>
        </tr>
    </table>
@endsection

@section('pie_etiqueta', __('pdf_total_sales_label'))
@section('pie_valor'){!! format_money_pdf($totalAmount, $currency) !!}@endsection
