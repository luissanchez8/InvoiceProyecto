@php
    /**
     * Devuelve la option_key esperada para un nombre normalizado.
     */
    function option_key_for($name) {
        $n = strtolower($name);
        // acepta español/inglés y variantes
        if (str_contains($n, 'recurr'))       return 'OPCION_MENU_FRA_RECURRENTE';
        if (str_contains($n, 'presupuesto') || str_contains($n, 'estimate')) return 'OPCION_MENU_PRESUPUESTOS';
        if (str_contains($n, 'factura')      || str_contains($n, 'invoice'))  return 'OPCION_MENU_FACTURAS';
        if (str_contains($n, 'pago')         || str_contains($n, 'payment'))  return 'OPCION_MENU_PAGOS';
        if (str_contains($n, 'gasto')        || str_contains($n, 'expense'))  return 'OPCION_MENU_GASTOS';
        return null;
    }
@endphp

@foreach($items as $item)
    @php
        $rawName   = $item->data('name') ?? ($item->title ?? '');
        $namePlain = strtolower(trim(strip_tags((string) $rawName)));

        // 1) prioridad: option_key adjunta al item (si la has puesto en el builder)
        $optionKey = $item->data('option_key') ?? null;

        // 2) fallback: deducir por nombre si no venía en data()
        if (!$optionKey) {
            $optionKey = option_key_for($namePlain);
        }

        // 3) si hay optionKey y la config vale '0', no mostramos
        $hide = false;
        if ($optionKey !== null) {
            $hide = (int) app_cfg($optionKey, 1) !== 1; // por defecto visible (1)
        }
    @endphp

    @if($hide)
        @continue
    @endif

    <li @lm_attrs($item) @if($item->hasChildren()) class="nav-item dropdown" @endif @lm_endattrs>
        @if($item->link)
            <a @lm_attrs($item->link)
               @if($item->hasChildren()) class="nav-link dropdown-toggle" role="button" @data_toggle_attribute="dropdown" aria-haspopup="true" aria-expanded="false"
               @else class="nav-link"
               @endif
               @lm_endattrs
               href="{!! $item->url() !!}">
                {!! $item->title !!}
                @if($item->hasChildren()) <b class="caret"></b> @endif
            </a>
        @else
            <span class="navbar-text">{!! $item->title !!}</span>
        @endif

        @if($item->hasChildren())
            <ul class="dropdown-menu">
                @include(config('laravel-menu.views.bootstrap-items'), ['items' => $item->children()])
            </ul>
        @endif
    </li>

    @if($item->divider)
        <li{!! Lavary\Menu\Builder::attributes($item->divider) !!}></li>
    @endif
@endforeach
