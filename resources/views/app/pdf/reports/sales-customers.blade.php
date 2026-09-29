{{-- Onfactu v.1.15.2: diseño común en layout.blade.php --}}
@extends('app.pdf.reports.layout')

@section('titulo', __('pdf_customer_sales_report'))

@section('contenido')
    <table class="tabla">
        <tr><th>Cliente y factura</th><th class="num">Importe</th></tr>
        @foreach ($customers as $customer)
        <tr class="grupo"><td colspan="2">{{ $customer->name }}</td></tr>
        @foreach ($customer->invoices as $invoice)
        <tr>
            <td>{{ $invoice->formattedInvoiceDate }} ({{ $invoice->invoice_number }})</td>
            <td class="num">{!! format_money_pdf($invoice->base_total, $currency) !!}</td>
        </tr>
        @endforeach
        <tr class="total">
            <td>Total {{ $customer->name }}</td>
            <td class="num">{!! format_money_pdf($customer->totalAmount, $currency) !!}</td>
        </tr>
        @endforeach
    </table>
@endsection

@section('pie_etiqueta', __('pdf_total_sales_label'))
@section('pie_valor'){!! format_money_pdf($totalAmount, $currency) !!}@endsection
