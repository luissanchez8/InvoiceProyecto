{{--
  Elementos de la demo pública dentro de la aplicación: el aviso fijo de "Demo"
  y el candado de las pantallas bloqueadas.

  Se crean desde JavaScript y no como HTML en la página porque la aplicación
  (Vue) ocupa todo el <body> al arrancar y borra lo que hubiera dentro. Por eso
  se insertan después y se vuelven a poner si algo los quita.

  El candado tapa pantallas que se enseñan en vez de ocultarse: son funciones
  que venden, y así se ve que existen. El bloqueo real está en el servidor
  (DemoMode); esto solo tapa la pantalla y lo explica.
--}}
<script>
(function () {
  // Ruta de la aplicación => título y texto del candado
  var BLOQUEADAS = [
    { ruta: /^\/admin\/users(\/|$)/, titulo: 'Usuarios y permisos',
      texto: 'Da acceso a tu equipo y decide qué puede hacer cada persona. En la demo está bloqueado.' }
  ];

  var CSS =
    '#onf-aviso-demo{position:fixed;top:14px;left:50%;transform:translateX(-50%);z-index:60;background:#070322;' +
      'color:#fff;font:500 13px/1 Satoshi,system-ui,sans-serif;padding:9px 16px;border-radius:999px;' +
      'box-shadow:0 4px 16px rgba(7,3,34,.18);white-space:nowrap}' +
    '#onf-aviso-demo a{color:#38d587;text-decoration:none;margin-left:10px;font-weight:700}' +
    // En móvil, abajo a la izquierda: arriba está la cabecera y abajo a la derecha el chat
    '@media (max-width:640px){#onf-aviso-demo{top:auto;bottom:14px;left:14px;transform:none;font-size:12px}' +
      '#onf-aviso-demo a{display:none}}' +
    '#onf-demo-candado{position:fixed;top:64px;left:0;right:0;bottom:0;z-index:50;display:flex;align-items:center;' +
      'justify-content:center;padding:24px;background:rgba(244,245,247,.88);backdrop-filter:blur(3px);' +
      'font-family:Satoshi,system-ui,sans-serif}' +
    '#onf-demo-candado[hidden]{display:none}' +
    '#onf-demo-candado .caja{background:#fff;border-radius:16px;box-shadow:0 10px 40px rgba(7,3,34,.12);' +
      'max-width:400px;width:100%;padding:36px 32px;text-align:center}' +
    // Los estilos base de la aplicación ponen los SVG como bloque: se centra con márgenes
    '#onf-demo-candado svg{display:block;margin:0 auto}' +
    '#onf-demo-candado h2{font-size:21px;font-weight:700;color:#070322;margin:16px 0 8px}' +
    '#onf-demo-candado p{font-size:15px;line-height:1.55;color:#4b5563;margin:0 0 24px}' +
    '#onf-demo-candado .principal{display:block;background:#38d587;color:#070322;font-weight:700;' +
      'text-decoration:none;padding:13px 18px;border-radius:10px}' +
    '#onf-demo-candado .secundario{display:inline-block;margin-top:16px;font-size:14px;color:#070322;font-weight:500}';

  var AVISO =
    'Demo: los datos se reinician cada noche' +
    '<a href="https://onfactu.com/precios" target="_blank" rel="noopener">Crear mi cuenta</a>';

  var CANDADO =
    '<div class="caja">' +
      '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#070322" stroke-width="1.8" ' +
        'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
        '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>' +
      '<h2></h2><p></p>' +
      '<a class="principal" href="https://onfactu.com/precios" target="_blank" rel="noopener">Crear mi cuenta</a>' +
      '<a class="secundario" href="/admin/dashboard">Volver al panel</a>' +
    '</div>';

  function crear(tag, id, html) {
    var el = document.createElement(tag);
    el.id = id;
    el.innerHTML = html;
    document.body.appendChild(el);
    return el;
  }

  // Pone los elementos si no están (al arrancar la aplicación se borran)
  function asegurar() {
    if (!document.body) return;
    if (!document.getElementById('onf-demo-estilos')) crear('style', 'onf-demo-estilos', CSS);
    if (!document.getElementById('onf-aviso-demo')) crear('div', 'onf-aviso-demo', AVISO);
    if (!document.getElementById('onf-demo-candado')) crear('div', 'onf-demo-candado', CANDADO).hidden = true;
  }

  function revisar() {
    asegurar();
    var capa = document.getElementById('onf-demo-candado');
    if (!capa) return;
    var b = BLOQUEADAS.filter(function (x) { return x.ruta.test(location.pathname); })[0];
    if (b) {
      capa.querySelector('h2').textContent = b.titulo;
      capa.querySelector('p').textContent = b.texto;
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
  document.addEventListener('DOMContentLoaded', revisar);

  // Red de seguridad por si la aplicación vuelve a borrar el <body>: una comprobación por segundo
  setInterval(revisar, 1000);
})();
</script>
