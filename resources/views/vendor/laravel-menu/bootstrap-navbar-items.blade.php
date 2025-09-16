@php
    use Illuminate\Support\Str;

    // Lee de la tabla app_config (vía helper). Si no tienes el helper, cambia por \App\Helpers\AppConfig::get(...).
    $recurrEnabled = (int) app_cfg('OPCION_MENU_FRA_RECURRENTE', 0) === 1;
@endphp

@foreach($items as $item)
  @php
      // 1) Si el item trae option_key, úsalo
      $optionKey = $item->data('option_key') ?? null;

      // 2) Si no, detecta por URL/título
      $href = method_exists($item, 'url') ? (string) $item->url() : '';
      $isRecurringByUrl   = Str::contains($href, '/admin/recurring-invoices');
      $isRecurringByTitle = Str::contains(Str::lower((string) ($item->title ?? '')), 'recurring');

      $showItem = true;

      if (
          ($optionKey === 'OPCION_MENU_FRA_RECURRENTE' && !$recurrEnabled) ||
          (!$recurrEnabled && ($isRecurringByUrl || $isRecurringByTitle))
      ) {
          $showItem = false;
      }
  @endphp

  @if($showItem)
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
  @endif
@endforeach
