@php
    // Lee el flag de BD; si algo falla, $recurrEnabled será false/true según tu default
    $recurrEnabled = (int) app_cfg('OPCION_MENU_FRA_RECURRENTE', 0) === 1;
@endphp

@foreach($items as $item)
    @php
        // Detecta por data('name') o title (sin tocar URL fuera de <a>)
        $rawName = $item->data('name') ?? ($item->title ?? '');
        $name    = strtolower(trim(strip_tags((string) $rawName)));
        $isRecurring = (strpos($name, 'recurr') !== false); // “recurr”, “recurring”, “recurrentes”, ...
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
