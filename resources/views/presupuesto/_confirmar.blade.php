{{--
  Onfactu v.1.17.2 — Ventana de confirmación de la página pública del
  presupuesto (presupuesto/publico). Sustituye al confirm() del navegador y
  copia el aspecto de la ventana de Onfactu (BaseDialog): icono en un círculo,
  título, texto y dos botones.

  Sin JavaScript el formulario se envía igual, sin confirmación. Con él, el
  envío se para, se pregunta y, al confirmar, se manda una sola vez (los
  botones se desactivan para que un doble clic no responda dos veces).
--}}
<style>
  .velo { position: fixed; inset: 0; background: rgba(107,114,128,.75); display: none; align-items: center; justify-content: center; padding: 16px; z-index: 20; }
  .velo.abierto { display: flex; }
  .ventana { background: #fff; width: 100%; max-width: 420px; border-radius: 12px; box-shadow: 0 20px 50px rgba(7,3,34,.25); padding: 24px; text-align: center; animation: entra .18s ease-out; }
  @keyframes entra { from { opacity: 0; transform: scale(.96); } to { opacity: 1; transform: none; } }
  .icono { width: 48px; height: 48px; margin: 0 auto 16px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
  .icono svg { width: 24px; height: 24px; }
  .icono.ok { background: #dcfce7; color: #16a34a; }
  .icono.no { background: #fee2e2; color: #dc2626; }
  .ventana h2 { font-size: 18px; font-weight: 500; margin: 0 0 8px; }
  .ventana p { font-size: 14px; color: #6b7280; margin: 0; }
  .ventana .botones { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 24px; }
  .ventana button { min-width: 0; padding: 11px 14px; font-size: 14px; }
  .ventana .confirmar.no { background: #dc2626; color: #fff; }
  button:disabled { opacity: .6; cursor: default; }
  @media (max-width: 420px) { .ventana .botones { grid-template-columns: 1fr; } }
</style>

<div class="velo" id="velo" role="dialog" aria-modal="true" aria-labelledby="ventana-titulo">
  <div class="ventana">
    <div class="icono" id="ventana-icono"></div>
    <h2 id="ventana-titulo"></h2>
    <p id="ventana-texto"></p>
    <div class="botones">
      <button type="button" class="aceptar confirmar" id="ventana-si"></button>
      <button type="button" class="rechazar" id="ventana-no">Cancelar</button>
    </div>
  </div>
</div>

<script>
(function () {
  var form = document.getElementById('respuesta');
  var velo = document.getElementById('velo');
  if (!form || !velo) return;

  var empresa = @json($empresa?->name ?: 'La empresa');
  var textos = {
    aceptar: {
      clase: 'ok', titulo: '¿Aceptar el presupuesto?', boton: 'Aceptar',
      texto: @json('Aceptas el presupuesto '.$p->estimate_number.' por '.$total.'.') + ' ' + empresa + ' recibirá el aviso.',
      icono: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 13l4 4L19 7"/></svg>'
    },
    rechazar: {
      clase: 'no', titulo: '¿Rechazar el presupuesto?', boton: 'Rechazar',
      texto: empresa + ' recibirá tu respuesta.',
      icono: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg>'
    }
  };
  var si = document.getElementById('ventana-si');
  var no = document.getElementById('ventana-no');
  var elegida = null;

  function abrir(accion) {
    var t = textos[accion];
    elegida = accion;
    document.getElementById('ventana-icono').className = 'icono ' + t.clase;
    document.getElementById('ventana-icono').innerHTML = t.icono;
    document.getElementById('ventana-titulo').textContent = t.titulo;
    document.getElementById('ventana-texto').textContent = t.texto;
    si.textContent = t.boton;
    si.className = (accion === 'aceptar' ? 'aceptar' : 'no') + ' confirmar';
    velo.classList.add('abierto');
    si.focus();
  }

  function cerrar() {
    velo.classList.remove('abierto');
    elegida = null;
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var boton = e.submitter || document.activeElement;
    abrir(boton && boton.value === 'rechazar' ? 'rechazar' : 'aceptar');
  });

  si.addEventListener('click', function () {
    if (!elegida) return;
    var campo = document.createElement('input');
    campo.type = 'hidden';
    campo.name = 'accion';
    campo.value = elegida;
    form.appendChild(campo);
    form.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
    si.disabled = true;
    no.disabled = true;
    form.submit();
  });

  no.addEventListener('click', cerrar);
  velo.addEventListener('click', function (e) { if (e.target === velo) cerrar(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && velo.classList.contains('abierto') && !si.disabled) cerrar(); });
})();
</script>
