{{--
    Onfactu v.1.15.2 — Diseño común de todos los informes en PDF.

    Cada informe (ventas por cliente, por artículo, gastos, pérdidas y
    ganancias e impuestos) extiende esta plantilla y solo pone su contenido:
    así todos tienen la misma cabecera, los mismos tamaños de letra, las
    mismas tablas y el mismo pie. Para cambiar el aspecto de los informes se
    cambia aquí, no en cada uno.

    Secciones que rellena cada informe:
      titulo       título de la pestaña y de la cabecera
      contenido    tablas del informe (clases: seccion, nota, tabla, num,
                   grupo, total)
      pie_etiqueta texto del total final
      pie_valor    importe del total final
--}}
<!DOCTYPE html>
<html lang="es">

<head>
    <title>@yield('titulo')</title>
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

        body { font-family: 'Satoshi', sans-serif !important; font-size: 13px; color: #595959; margin: 0; }
        table { border-collapse: collapse; }
        .contenedor { padding: 0 20px; }

        /* Cabecera */
        .cabecera { width: 100%; margin-bottom: 28px; }
        .empresa { font-weight: bold; font-size: 22px; color: #070322; margin: 0; }
        .fechas { font-size: 13px; color: #A5ACC1; text-align: right; margin: 0; }
        .titulo-informe { font-weight: bold; font-size: 14px; color: #595959; text-transform: uppercase; letter-spacing: 1px; margin: 6px 0 0 0; }

        /* Secciones y notas */
        .seccion { font-weight: bold; font-size: 14px; color: #040405; margin: 24px 0 0 0; }
        .nota { font-size: 11px; color: #8a8f9c; margin: 3px 0 0 0; }

        /* Tablas */
        .tabla { width: 100%; margin-top: 8px; }
        .tabla th { font-size: 11px; font-weight: normal; color: #8a8f9c; text-align: left; padding: 5px 6px; border-bottom: 1px solid #e5e7eb; }
        .tabla td { font-size: 13px; color: #595959; text-align: left; padding: 5px 6px; }
        .tabla .num { text-align: right; white-space: nowrap; }
        .tabla tr.grupo td { font-weight: bold; color: #040405; padding-top: 14px; }
        .tabla tr.total td { font-weight: bold; color: #040405; border-top: 1px solid #e5e7eb; }

        /* Pie con el total final */
        .pie { width: 100%; margin-top: 32px; padding: 14px 20px; background: #f0faf4; }
        .pie-etiqueta { font-weight: bold; font-size: 14px; color: #595959; text-transform: uppercase; margin: 0; }
        .pie-valor { font-weight: bold; font-size: 18px; color: #38d587; text-align: right; margin: 0; }
    </style>

    @if (App::isLocale('th'))
    @include('app.pdf.locale.th')
    @endif
</head>

<body>
    <div class="contenedor">
        <table class="cabecera">
            <tr>
                <td><p class="empresa">{{ $company->name }}</p></td>
                <td><p class="fechas">{{ $periodo ?? ($from_date.' - '.$to_date) }}</p></td>
            </tr>
            <tr>
                <td colspan="2"><p class="titulo-informe">@yield('titulo')</p></td>
            </tr>
        </table>

        @yield('contenido')
    </div>

    <table class="pie">
        <tr>
            <td><p class="pie-etiqueta">@yield('pie_etiqueta')</p></td>
            <td><p class="pie-valor">@yield('pie_valor')</p></td>
        </tr>
    </table>
</body>

</html>
