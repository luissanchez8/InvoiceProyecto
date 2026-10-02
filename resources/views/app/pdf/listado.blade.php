{{-- Onfactu v.1.18.0: lista de facturas o gastos exportada (ExportarListadoController), con el diseño de los informes --}}
@extends('app.pdf.reports.layout')

@section('titulo', $titulo)

@section('contenido')
    <table class="tabla">
        <tr>
            @foreach ($cabecera as $i => $col)
            <th class="{{ in_array($i, $importes) ? 'num' : '' }}">{{ $col }}</th>
            @endforeach
        </tr>
        @foreach ($filas as $fila)
        <tr>
            @foreach ($fila as $i => $valor)
            @if (in_array($i, $importes))
            <td class="num">{!! $valor === null ? '-' : format_money_pdf($valor, $currency) !!}</td>
            @else
            <td>{{ $valor }}</td>
            @endif
            @endforeach
        </tr>
        @endforeach
        <tr class="total">
            @foreach ($cabecera as $i => $col)
            @if ($i === 0)
            <td>Total ({{ count($filas) }})</td>
            @elseif (in_array($i, $importes))
            <td class="num">{!! format_money_pdf($totales[$i], $currency) !!}</td>
            @else
            <td></td>
            @endif
            @endforeach
        </tr>
    </table>
    @if ($maximo)
    <p class="nota">Se han exportado las {{ count($filas) }} primeras. Filtra por fechas para exportar el resto.</p>
    @endif
@endsection

@section('pie_etiqueta', 'Total')
@section('pie_valor'){!! format_money_pdf($pieTotal, $currency) !!}@endsection
