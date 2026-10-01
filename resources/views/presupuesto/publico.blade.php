{{--
  Onfactu v.1.17.0 — Página que abre el cliente desde el correo del presupuesto
  (PresupuestoPublicoController). Enseña el presupuesto, el enlace al PDF y los
  botones para aceptarlo o rechazarlo. Página suelta, sin la aplicación Vue.
  v.1.17.2: la confirmación es una ventana como las de Onfactu (_confirmar),
  no la del navegador; ?espera=1 llega del limitador de intentos.
--}}
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Presupuesto {{ $p->estimate_number }} · {{ $empresa?->name }}</title>
  <link rel="preconnect" href="https://api.fontshare.com">
  <link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,700&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; background: #f4f5f7; color: #070322; font: 400 15px/1.55 Satoshi, system-ui, sans-serif; }
    .pagina { min-height: 100vh; display: flex; flex-direction: column; align-items: center; padding: 40px 16px; }
    .caja { background: #fff; width: 100%; max-width: 520px; border-radius: 16px; box-shadow: 0 10px 40px rgba(7,3,34,.08); padding: 32px; }
    .empresa { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; }
    .empresa img { max-height: 48px; max-width: 180px; }
    .empresa span { font-weight: 700; font-size: 17px; }
    h1 { font-size: 22px; margin: 0 0 4px; }
    .para { color: #6b7280; margin: 0 0 20px; }
    dl { margin: 0 0 20px; border-top: 1px solid #eef0f3; }
    dl div { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eef0f3; }
    dt { color: #6b7280; }
    dd { margin: 0; font-weight: 500; }
    .total dd { font-weight: 700; font-size: 18px; }
    .pdf { display: inline-block; color: #070322; font-weight: 500; margin-bottom: 24px; }
    label { display: block; font-size: 13px; color: #6b7280; margin: 0 0 4px; }
    input, textarea { width: 100%; border: 1px solid #d1d5db; border-radius: 8px; padding: 9px 11px; font: inherit; margin-bottom: 14px; }
    textarea { resize: vertical; min-height: 70px; }
    .botones { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 6px; }
    button { flex: 1; min-width: 150px; border: 0; border-radius: 10px; padding: 13px 16px; font: 700 15px Satoshi, system-ui, sans-serif; cursor: pointer; }
    .aceptar { background: #38d587; color: #070322; }
    .rechazar { background: #fff; color: #070322; border: 1px solid #d1d5db; }
    .aviso { border-radius: 10px; padding: 14px 16px; margin: 0; }
    .aviso.ok { background: #ecfdf3; color: #065f46; }
    .aviso.no { background: #fef2f2; color: #991b1b; }
    .aviso.info { background: #f3f4f6; color: #374151; }
    .pie { margin-top: 20px; font-size: 13px; color: #9ca3af; }
    .pie a { color: #6b7280; font-weight: 700; text-decoration: none; }
    @media (max-width: 480px) { .caja { padding: 24px 20px; } }
  </style>
</head>
<body>
<div class="pagina">
  <main class="caja">
    <div class="empresa">
      @if($logo)
        <img src="{{ $logo }}" alt="{{ $empresa?->name }}">
      @else
        <span>{{ $empresa?->name }}</span>
      @endif
    </div>

    <h1>Presupuesto {{ $p->estimate_number }}</h1>
    @if($p->customer)
      <p class="para">Para {{ $p->customer->name }}</p>
    @endif

    <dl>
      <div><dt>Fecha</dt><dd>{{ $fecha }}</dd></div>
      @if($validez)
        <div><dt>Válido hasta</dt><dd>{{ $validez }}</dd></div>
      @endif
      <div class="total"><dt>Total</dt><dd>{{ $total }}</dd></div>
    </dl>

    <a class="pdf" href="{{ $pdf }}" target="_blank" rel="noopener">Ver el presupuesto completo (PDF)</a>

    @if($motivo === null)
      @if(request()->boolean('espera'))
        <p class="aviso info" style="margin-bottom:16px">Has hecho varios intentos seguidos. Espera un minuto y vuelve a probar.</p>
      @endif
      <form method="post" action="{{ $accion }}" id="respuesta">
        @csrf
        <label for="nombre">Tu nombre (opcional)</label>
        <input id="nombre" name="nombre" maxlength="150" autocomplete="name">
        <label for="comentario">Comentario (opcional)</label>
        <textarea id="comentario" name="comentario" maxlength="1000"></textarea>
        <div class="botones">
          <button class="aceptar" type="submit" name="accion" value="aceptar">Aceptar presupuesto</button>
          <button class="rechazar" type="submit" name="accion" value="rechazar">Rechazar</button>
        </div>
      </form>
      @include('presupuesto._confirmar')
    @elseif($motivo === 'respondido')
      @if($p->status === 'REJECTED')
        <p class="aviso no">Presupuesto rechazado{{ $respondidoEl ? ' el '.$respondidoEl : '' }}. Gracias por responder.</p>
      @else
        <p class="aviso ok">Presupuesto aceptado{{ $respondidoEl ? ' el '.$respondidoEl : '' }}. {{ $empresa?->name }} ya lo sabe.</p>
      @endif
    @elseif($motivo === 'enlace_caducado')
      <p class="aviso info">Este enlace ha caducado. Pide a {{ $empresa?->name }} que te envíe el presupuesto de nuevo.</p>
    @else
      <p class="aviso info">Este presupuesto ya no está vigente. Pide a {{ $empresa?->name }} uno nuevo.</p>
    @endif
  </main>
  <p class="pie">Creado con <a href="https://onfactu.com/" target="_blank" rel="noopener">onfactu</a></p>
</div>
</body>
</html>
