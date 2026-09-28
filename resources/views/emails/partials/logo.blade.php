{{--
  Onfactu v.1.14.2 — Logo de la empresa en la cabecera de los correos.

  Outlook de escritorio ignora el tamaño puesto con estilos en las imágenes y
  las pinta a su tamaño real: un logo grande salía gigante. Solo respeta los
  atributos width y height, así que se calculan aquí con las medidas reales
  del logo: 50 px de alto y como mucho 200 de ancho, sin deformarlo. Si no se
  pueden leer (un SVG, por ejemplo), se pone solo el alto.
--}}
@php
    $logoAlto = 50;
    $logoAncho = null;
    try {
        $ruta = $company['logo_path'] ?? null;
        $medidas = ($ruta && is_file($ruta)) ? @getimagesize($ruta) : false;
        if ($medidas && $medidas[1] > 0) {
            $logoAncho = (int) round($medidas[0] * $logoAlto / $medidas[1]);
            if ($logoAncho > 200) {
                $logoAlto = max(1, (int) round($logoAlto * 200 / $logoAncho));
                $logoAncho = 200;
            }
        }
    } catch (\Throwable $e) {
        $logoAncho = null;
    }
@endphp
<img
    class="header-logo"
    src="{{ asset($company['logo']) }}"
    alt="{{ $company['name'] }}"
    height="{{ $logoAlto }}"
    @if($logoAncho) width="{{ $logoAncho }}" @endif
    style="height: {{ $logoAlto }}px; @if($logoAncho) width: {{ $logoAncho }}px; @endif max-width: 200px; border: 0; display: inline-block;"
>
