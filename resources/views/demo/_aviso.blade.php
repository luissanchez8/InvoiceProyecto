{{-- Aviso fijo dentro de la aplicación, solo en la demo pública --}}
<div id="onf-aviso-demo" style="position:fixed;top:14px;left:50%;transform:translateX(-50%);z-index:60;
     background:#070322;color:#fff;font:500 13px/1 'Satoshi',system-ui,sans-serif;padding:9px 16px;
     border-radius:999px;box-shadow:0 4px 16px rgba(7,3,34,.18);white-space:nowrap">
  Demo: los datos se reinician cada noche
  <a href="https://onfactu.com/precios" target="_blank" rel="noopener"
     style="color:#38d587;text-decoration:none;margin-left:10px;font-weight:700">Crear mi cuenta</a>
</div>

{{--
  Pantallas bloqueadas en la demo que se enseñan con candado en vez de ocultarlas:
  son funciones que venden, y así se ve que existen. El bloqueo real está en el
  servidor (DemoMode); esto solo tapa la pantalla y lo explica.
--}}
<div id="onf-demo-candado" hidden>
  <div class="caja">
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#070322" stroke-width="1.8"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>
    </svg>
    <h2 id="onf-demo-candado-titulo"></h2>
    <p id="onf-demo-candado-texto"></p>
    <a class="principal" href="https://onfactu.com/precios" target="_blank" rel="noopener">Crear mi cuenta</a>
    <a class="secundario" href="/admin/dashboard">Volver al panel</a>
  </div>
</div>

<style>
  {{-- En móvil, abajo a la izquierda: arriba está la cabecera y abajo a la derecha el chat --}}
  @media (max-width:640px){#onf-aviso-demo{top:auto;bottom:14px;left:14px;transform:none;font-size:12px}
  #onf-aviso-demo a{display:none}}

  #onf-demo-candado{position:fixed;top:64px;left:0;right:0;bottom:0;z-index:50;display:flex;
    align-items:center;justify-content:center;padding:24px;background:rgba(244,245,247,.88);
    backdrop-filter:blur(3px);font-family:'Satoshi',system-ui,sans-serif}
  #onf-demo-candado[hidden]{display:none}
  #onf-demo-candado .caja{background:#fff;border-radius:16px;box-shadow:0 10px 40px rgba(7,3,34,.12);
    max-width:400px;width:100%;padding:36px 32px;text-align:center}
  #onf-demo-candado h2{font-size:21px;font-weight:700;color:#070322;margin:16px 0 8px}
  #onf-demo-candado p{font-size:15px;line-height:1.55;color:#4b5563;margin:0 0 24px}
  #onf-demo-candado .principal{display:block;background:#38d587;color:#070322;font-weight:700;
    text-decoration:none;padding:13px 18px;border-radius:10px}
  #onf-demo-candado .secundario{display:inline-block;margin-top:16px;font-size:14px;color:#070322;
    font-weight:500}
</style>

<script>
(function () {
  // Ruta de la aplicación => título y texto del candado
  var BLOQUEADAS = [
    { ruta: /^\/admin\/users(\/|$)/, titulo: 'Usuarios y permisos',
      texto: 'Da acceso a tu equipo y decide qué puede hacer cada persona. En la demo está bloqueado.' }
  ];

  function revisar() {
    var capa = document.getElementById('onf-demo-candado');
    if (!capa) return;
    var b = BLOQUEADAS.filter(function (x) { return x.ruta.test(location.pathname); })[0];
    if (b) {
      document.getElementById('onf-demo-candado-titulo').textContent = b.titulo;
      document.getElementById('onf-demo-candado-texto').textContent = b.texto;
    }
    capa.hidden = !b;
  }

  // La aplicación cambia de pantalla sin recargar: se vigilan sus cambios de ruta
  ['pushState', 'replaceState'].forEach(function (m) {
    var original = history[m];
    history[m] = function () {
      var r = original.apply(this, arguments);
      revisar();
      return r;
    };
  });
  window.addEventListener('popstate', revisar);
  revisar();
})();
</script>
