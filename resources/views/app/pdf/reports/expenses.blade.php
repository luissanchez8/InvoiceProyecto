{{-- Onfactu v.1.15.2: diseño común en layout.blade.php. Base e IVA desde la v.1.15.0 --}}
@extends('app.pdf.reports.layout')

@section('titulo', __('pdf_expense_report_label'))

@section('contenido')
    <table class="tabla">
        <tr><th>Categoría</th><th class="num">Base</th><th class="num">IVA</th><th class="num">Total</th></tr>
        @foreach ($expenseCategories as $expenseCategory)
        @php $d = $desgloseCategorias[$expenseCategory->expense_category_id] ?? null; @endphp
        <tr>
            <td>{{ $expenseCategory->category->name }}</td>
            <td class="num">{!! $d ? format_money_pdf($d->base, $currency) : '-' !!}</td>
            <td class="num">{!! $d ? format_money_pdf($d->iva, $currency) : '-' !!}</td>
            <td class="num">{!! format_money_pdf($expenseCategory->total_amount, $currency) !!}</td>
        </tr>
        @endforeach
        <tr class="total">
            <td>Total</td>
            <td class="num">{!! format_money_pdf($totalBase, $currency) !!}</td>
            <td class="num">{!! format_money_pdf($totalIva, $currency) !!}</td>
            <td class="num">{!! format_money_pdf($totalExpense, $currency) !!}</td>
        </tr>
    </table>
    <p class="nota">
        Base e IVA de los gastos con el IVA desglosado. El total es lo pagado, con la retención ya descontada.
        @if ($sinDesglose->numero > 0)
        Hay {{ $sinDesglose->numero }} {{ $sinDesglose->numero == 1 ? 'gasto' : 'gastos' }} sin desglose por {!! format_money_pdf($sinDesglose->importe, $currency) !!}, que solo cuentan en el total.
        @endif
    </p>
@endsection

@section('pie_etiqueta', __('pdf_total_expenses_label'))
@section('pie_valor'){!! format_money_pdf($totalExpense, $currency) !!}@endsection
