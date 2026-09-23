{{-- Portada de la demo pública (demo.onfactu.com). La sirve DemoMode en lugar del acceso normal. --}}
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Demo de Onfactu</title>
<meta name="robots" content="noindex">
<link rel="icon" href="/favicon.ico">
<link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,700&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Satoshi',system-ui,sans-serif;background:#f4f5f7;color:#070322;
       min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
  .tarjeta{background:#fff;border-radius:16px;box-shadow:0 10px 40px rgba(7,3,34,.08);
           max-width:460px;width:100%;padding:44px 40px;text-align:center}
  .logo{height:42px;margin-bottom:28px}
  h1{font-size:26px;font-weight:700;line-height:1.25;margin-bottom:12px}
  p{font-size:16px;line-height:1.6;color:#4b5563;margin-bottom:28px}
  .boton{display:block;background:#38d587;color:#070322;font-weight:700;font-size:16px;
         text-decoration:none;padding:15px 20px;border-radius:10px;transition:filter .15s}
  .boton:hover{filter:brightness(.95)}
  .nota{font-size:13px;color:#9ca3af;margin:18px 0 0}
  .cuenta{display:inline-block;margin-top:22px;font-size:14px;color:#070322;font-weight:500}
  @media (max-width:480px){.tarjeta{padding:34px 24px}h1{font-size:22px}}
</style>
</head>
<body>
  <main class="tarjeta">
    <img class="logo" src="{{ app_cfg('URL_LOGOTIPO', '/images/logo-onfactu-margen.png') }}" alt="Onfactu">
    <h1>Prueba Onfactu sin registrarte</h1>
    <p>Una empresa de ejemplo con clientes, facturas, presupuestos, gastos y cobros. Toca lo que quieras: cada noche vuelve a su estado inicial.</p>
    <a class="boton" href="/demo/entrar">Entrar en la demo</a>
    <p class="nota">Datos inventados. En la demo no se envían correos.</p>
    <a class="cuenta" href="https://onfactu.com/precios">¿Te convence? Crea tu cuenta</a>
  </main>
</body>
</html>
