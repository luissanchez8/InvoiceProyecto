{{-- Aviso fijo dentro de la aplicación, solo en la demo pública --}}
<div id="onf-aviso-demo" style="position:fixed;top:14px;left:50%;transform:translateX(-50%);z-index:60;
     background:#070322;color:#fff;font:500 13px/1 'Satoshi',system-ui,sans-serif;padding:9px 16px;
     border-radius:999px;box-shadow:0 4px 16px rgba(7,3,34,.18);white-space:nowrap">
  Demo: los datos se reinician cada noche
  <a href="https://onfactu.com/precios" target="_blank" rel="noopener"
     style="color:#38d587;text-decoration:none;margin-left:10px;font-weight:700">Crear mi cuenta</a>
</div>
{{-- En móvil, abajo a la izquierda: arriba está la cabecera y abajo a la derecha el chat --}}
<style>@media (max-width:640px){#onf-aviso-demo{top:auto;bottom:14px;left:14px;transform:none;font-size:12px}
#onf-aviso-demo a{display:none}}</style>
