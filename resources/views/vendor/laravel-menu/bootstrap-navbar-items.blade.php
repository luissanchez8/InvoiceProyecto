@php
    // lee el flag desde BD (tabla app_config)
    $recurrEnabled = (int) app_cfg('OPCION_MENU_FRA_RECURRENTE', 0) === 1;
@endphp

@foreach($items as $item)
    @php
        // TITULO seguro
        $title = strtolower(trim(strip_tags($item->title ?? '')));

        // URL segura (algunos items pueden romper al construirla)
        $href = '';
        try {
            if (method_exists($item, 'url')) {
                $href = (string) $item->url();
            }
        } catch (\Throwable $e) {
            $href = '';
        }

        // detectar el item "Facturas recurrentes"
        $isRecurring = (strpos($href, '/admin/recurring-invoices') !== false)
                       || (strpos($title, 'recurr') !== false); // por si cambia la URL y queda el texto

        // si está desactivado en BD, saltamos este item
        if ($isRecurring && !$recurrEnabled) {
            // salta al siguiente item sin renderizar este <li>
            // (esto evita desbalancear la lista y no rompe el HTML)
            // Nota: no uses 'use Str' dentro de @php
        }
    @endphp

    @if($isRecurring && !$recurrEnabled)
        @continue
    @endif

    <li @lm_attrs($item) @if($item->hasChildren()) class="nav-item dropdown" @endif @lm_endattrs>
        @if($item->link)
            <a @lm_attrs($item->link)
               @if($item->hasChildren()) class="nav-link dropdown-toggle" role="button" @data_toggle_attribute="dropdown" aria-haspopup="true" aria-expanded="false"
               @else class="nav-link"
               @endif
               @lm_endattrs
               href="{!! $href ?: $item->url() !!}">
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
