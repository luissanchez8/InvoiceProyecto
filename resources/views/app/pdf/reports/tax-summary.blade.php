<!DOCTYPE html>
<html lang="en">

<head>
    <title>@lang('pdf_tax_summery_label')</title>
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
            margin-bottom: 60px
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
            font-weight: bold;
            font-size: 16px;
            color: #595959;
            padding: 0px;
            margin: 0px;
            margin-top: 6px;
        }

        .tax-types-title {
            margin-top: 20px;
            padding-left: 3px;
            font-size: 16px;
            line-height: 21px;
            color: #040405;
        }

        .tax-table-container {
            padding-left: 10px;
        }

        .tax-table {
            width: 100%;
            padding-bottom: 10px;
        }

        .tax-title {
            padding: 0px;
            margin: 0px;
            font-size: 14px;
            line-height: 21px;
            color: #595959;
        }

        .tax-amount {
            padding: 0px;
            margin: 0px;
            font-size: 14px;
            line-height: 21px;
            text-align: right;
            color: #595959;
        }

        .tax-total-table {
            border-top: 1px solid #e5e7eb;
            width: 100%;
        }

        .tax-total-cell {
            padding-right: 20px;
            padding-top: 10px;
        }

        .tax-total {
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

        /* Onfactu v.1.15.0: IVA de ventas, de gastos y resultado */
        .iva-seccion { margin-top: 26px; font-size: 15px; font-weight: bold; color: #040405; padding-left: 3px; }
        .iva-nota { font-size: 11px; color: #8a8f9c; padding-left: 3px; margin: 2px 0 0 0; }
        .iva-tabla { width: 100%; margin-top: 8px; }
        .iva-tabla th { font-size: 11px; color: #8a8f9c; text-align: right; font-weight: normal; padding: 4px 6px; border-bottom: 1px solid #e5e7eb; }
        .iva-tabla th.izq, .iva-tabla td.izq { text-align: left; }
        .iva-tabla td { font-size: 13px; color: #595959; text-align: right; padding: 5px 6px; }
        .iva-tabla tr.total td { font-weight: bold; color: #040405; border-top: 1px solid #e5e7eb; }
        .iva-resultado { width: 100%; margin-top: 26px; }
        .iva-resultado td { font-size: 14px; color: #595959; padding: 4px 6px; }
        .iva-resultado td.cifra { text-align: right; }
    </style>

    @if (App::isLocale('th'))
    @include('app.pdf.locale.th')
    @endif
</head>

<body>
    @php
        $m = fn ($c) => format_money_pdf($c, $currency);
        $r = $resumen;
    @endphp
    <div class="sub-container">
        <table class="report-header">
            <tr>
                <td><p class="heading-text">{{ $company->name }}</p></td>
                <td><p class="heading-date-range">{{ $from_date }} - {{ $to_date }}</p></td>
            </tr>
            <tr>
                <td colspan="2"><p class="sub-heading-text">@lang('pdf_tax_report_label')</p></td>
            </tr>
        </table>

        {{-- IVA de las ventas --}}
        <p class="iva-seccion">IVA de las ventas</p>
        <p class="iva-nota">Facturas aprobadas del periodo, cobradas o no. Las rectificativas restan.</p>
        <table class="iva-tabla">
            <tr><th class="izq">Impuesto</th><th>Base</th><th>Cuota</th></tr>
            @forelse ($r['ventas'] as $v)
            <tr><td class="izq">{{ $v['nombre'] }}</td><td>{!! $m($v['base']) !!}</td><td>{!! $m($v['cuota']) !!}</td></tr>
            @empty
            <tr><td class="izq" colspan="3">Sin ventas en el periodo</td></tr>
            @endforelse
            <tr class="total"><td class="izq">Total</td><td>{!! $m($r['ventas_base']) !!}</td><td>{!! $m($r['ventas_cuota']) !!}</td></tr>
        </table>

        {{-- IVA de los gastos --}}
        <p class="iva-seccion">IVA de los gastos</p>
        <p class="iva-nota">Gastos del periodo con el IVA desglosado. El IVA no deducible no resta en el resultado.</p>
        <table class="iva-tabla">
            <tr><th class="izq">Tipo</th><th>Base</th><th>Cuota</th><th>Deducible</th><th>No deducible</th></tr>
            @forelse ($r['gastos'] as $g)
            <tr><td class="izq">{{ $g['nombre'] }}</td><td>{!! $m($g['base']) !!}</td><td>{!! $m($g['cuota']) !!}</td><td>{!! $m($g['deducible']) !!}</td><td>{!! $m($g['no_deducible']) !!}</td></tr>
            @empty
            <tr><td class="izq" colspan="5">Sin gastos con IVA desglosado en el periodo</td></tr>
            @endforelse
            <tr class="total"><td class="izq">Total</td><td>{!! $m($r['gastos_base']) !!}</td><td>{!! $m($r['gastos_cuota']) !!}</td><td>{!! $m($r['gastos_deducible']) !!}</td><td>{!! $m($r['gastos_no_deducible']) !!}</td></tr>
        </table>
        @if ($r['sin_desglose']['numero'] > 0)
        <p class="iva-nota">Además hay {{ $r['sin_desglose']['numero'] }} {{ $r['sin_desglose']['numero'] === 1 ? 'gasto' : 'gastos' }} sin el IVA desglosado, por {!! $m($r['sin_desglose']['importe']) !!} en total, que no se incluyen porque no se sabe su IVA.</p>
        @endif

        @if (count($r['autoliquidacion']))
        {{-- Compras intracomunitarias --}}
        <p class="iva-seccion">Compras intracomunitarias (autoliquidación)</p>
        <p class="iva-nota">La factura llega sin IVA. Su IVA suma a la vez en lo que se debe y, si es deducible, en lo que se deduce.</p>
        <table class="iva-tabla">
            <tr><th class="izq">Tipo</th><th>Base</th><th>Cuota</th><th>Deducible</th></tr>
            @foreach ($r['autoliquidacion'] as $a)
            <tr><td class="izq">{{ $a['nombre'] }}</td><td>{!! $m($a['base']) !!}</td><td>{!! $m($a['cuota']) !!}</td><td>{!! $m($a['deducible']) !!}</td></tr>
            @endforeach
        </table>
        @endif

        {{-- Resultado --}}
        <table class="iva-resultado">
            <tr><td>IVA de las ventas</td><td class="cifra">{!! $m($r['ventas_cuota']) !!}</td></tr>
            @if ($r['autoliquidacion_cuota'])
            <tr><td>IVA autoliquidado de compras intracomunitarias</td><td class="cifra">{!! $m($r['autoliquidacion_cuota']) !!}</td></tr>
            @endif
            <tr><td>IVA deducible de los gastos</td><td class="cifra">- {!! $m($r['iva_deducible']) !!}</td></tr>
        </table>

        @if (count($r['retenciones_ventas']) || count($r['retenciones_gastos']))
        {{-- Retenciones de IRPF: no son IVA --}}
        <p class="iva-seccion">Retenciones de IRPF</p>
        <p class="iva-nota">No entran en el resultado del IVA.</p>
        <table class="iva-tabla">
            <tr><th class="izq">Concepto</th><th>Base</th><th>Importe</th></tr>
            @foreach ($r['retenciones_ventas'] as $rv)
            <tr><td class="izq">Te han retenido tus clientes ({{ $rv['nombre'] }})</td><td>{!! $m($rv['base']) !!}</td><td>{!! $m(abs($rv['cuota'])) !!}</td></tr>
            @endforeach
            @foreach ($r['retenciones_gastos'] as $rg)
            <tr><td class="izq">Has retenido a tus proveedores ({{ rtrim(rtrim(number_format($rg['porcentaje'], 2, ',', ''), '0'), ',') }} %)</td><td>{!! $m($rg['base']) !!}</td><td>{!! $m($rg['importe']) !!}</td></tr>
            @endforeach
        </table>
        @endif
    </div>

    <table class="report-footer">
        <tr>
            <td><p class="report-footer-label">{{ $r['resultado'] >= 0 ? 'Resultado del IVA: a pagar' : 'Resultado del IVA: a compensar o devolver' }}</p></td>
            <td><p class="report-footer-value">{!! $m(abs($r['resultado'])) !!}</p></td>
        </tr>
    </table>
</body>

</html>