{{-- Onfactu v.1.15.2: diseño común en layout.blade.php. IVA de ventas, gastos y resultado desde la v.1.15.0 (App\Services\ResumenIva) --}}
@extends('app.pdf.reports.layout')

@section('titulo', __('pdf_tax_report_label'))

@section('contenido')
    @php
        $m = fn ($c) => format_money_pdf($c, $currency);
        $r = $resumen;
    @endphp

    <p class="seccion">IVA de las ventas</p>
    <p class="nota">Facturas aprobadas del periodo, cobradas o no. Las rectificativas restan.</p>
    <table class="tabla">
        <tr><th>Impuesto</th><th class="num">Base</th><th class="num">Cuota</th></tr>
        @forelse ($r['ventas'] as $v)
        <tr><td>{{ $v['nombre'] }}</td><td class="num">{!! $m($v['base']) !!}</td><td class="num">{!! $m($v['cuota']) !!}</td></tr>
        @empty
        <tr><td colspan="3">Sin ventas en el periodo</td></tr>
        @endforelse
        <tr class="total"><td>Total</td><td class="num">{!! $m($r['ventas_base']) !!}</td><td class="num">{!! $m($r['ventas_cuota']) !!}</td></tr>
    </table>

    <p class="seccion">IVA de los gastos</p>
    <p class="nota">Gastos del periodo con el IVA desglosado. El IVA no deducible no resta en el resultado.</p>
    <table class="tabla">
        <tr><th>Tipo</th><th class="num">Base</th><th class="num">Cuota</th><th class="num">Deducible</th><th class="num">No deducible</th></tr>
        @forelse ($r['gastos'] as $g)
        <tr><td>{{ $g['nombre'] }}</td><td class="num">{!! $m($g['base']) !!}</td><td class="num">{!! $m($g['cuota']) !!}</td><td class="num">{!! $m($g['deducible']) !!}</td><td class="num">{!! $m($g['no_deducible']) !!}</td></tr>
        @empty
        <tr><td colspan="5">Sin gastos con el IVA desglosado en el periodo</td></tr>
        @endforelse
        <tr class="total"><td>Total</td><td class="num">{!! $m($r['gastos_base']) !!}</td><td class="num">{!! $m($r['gastos_cuota']) !!}</td><td class="num">{!! $m($r['gastos_deducible']) !!}</td><td class="num">{!! $m($r['gastos_no_deducible']) !!}</td></tr>
    </table>
    @if ($r['sin_desglose']['numero'] > 0)
    <p class="nota">Además hay {{ $r['sin_desglose']['numero'] }} {{ $r['sin_desglose']['numero'] === 1 ? 'gasto' : 'gastos' }} sin el IVA desglosado, por {!! $m($r['sin_desglose']['importe']) !!} en total, que no se incluyen porque no se sabe su IVA.</p>
    @endif

    @if (count($r['autoliquidacion']))
    <p class="seccion">Compras intracomunitarias (autoliquidación)</p>
    <p class="nota">La factura llega sin IVA. Su IVA suma a la vez en lo que se debe y, si es deducible, en lo que se deduce.</p>
    <table class="tabla">
        <tr><th>Tipo</th><th class="num">Base</th><th class="num">Cuota</th><th class="num">Deducible</th></tr>
        @foreach ($r['autoliquidacion'] as $a)
        <tr><td>{{ $a['nombre'] }}</td><td class="num">{!! $m($a['base']) !!}</td><td class="num">{!! $m($a['cuota']) !!}</td><td class="num">{!! $m($a['deducible']) !!}</td></tr>
        @endforeach
    </table>
    @endif

    <p class="seccion">Resultado del IVA</p>
    <table class="tabla">
        <tr><th>Concepto</th><th class="num">Importe</th></tr>
        <tr><td>IVA de las ventas</td><td class="num">{!! $m($r['ventas_cuota']) !!}</td></tr>
        @if ($r['autoliquidacion_cuota'])
        <tr><td>IVA autoliquidado de compras intracomunitarias</td><td class="num">{!! $m($r['autoliquidacion_cuota']) !!}</td></tr>
        @endif
        <tr><td>IVA deducible de los gastos</td><td class="num">- {!! $m($r['iva_deducible']) !!}</td></tr>
        <tr class="total"><td>{{ $r['resultado'] >= 0 ? 'A pagar' : 'A compensar o devolver' }}</td><td class="num">{!! $m(abs($r['resultado'])) !!}</td></tr>
    </table>

    @if (count($r['retenciones_ventas']) || count($r['retenciones_gastos']))
    <p class="seccion">Retenciones de IRPF</p>
    <p class="nota">No entran en el resultado del IVA.</p>
    <table class="tabla">
        <tr><th>Concepto</th><th class="num">Base</th><th class="num">Importe</th></tr>
        @foreach ($r['retenciones_ventas'] as $rv)
        <tr><td>Te han retenido tus clientes ({{ $rv['nombre'] }})</td><td class="num">{!! $m($rv['base']) !!}</td><td class="num">{!! $m(abs($rv['cuota'])) !!}</td></tr>
        @endforeach
        @foreach ($r['retenciones_gastos'] as $rg)
        <tr><td>Has retenido a tus proveedores ({{ rtrim(rtrim(number_format($rg['porcentaje'], 2, ',', ''), '0'), ',') }} %)</td><td class="num">{!! $m($rg['base']) !!}</td><td class="num">{!! $m($rg['importe']) !!}</td></tr>
        @endforeach
    </table>
    @endif
@endsection

@section('pie_etiqueta', $resumen['resultado'] >= 0 ? 'Resultado del IVA: a pagar' : 'Resultado del IVA: a compensar o devolver')
@section('pie_valor'){!! format_money_pdf(abs($resumen['resultado']), $currency) !!}@endsection
