# Demo pública de Onfactu

Instancia abierta al público en **demo.onfactu.com**. Cualquiera entra con un botón, sin registrarse, y prueba Onfactu con una empresa de ejemplo. Cada noche a las 00:00 (hora de Madrid) vuelve a su estado inicial con datos nuevos.

## Cómo está montada

- Instancia normal de Onfactu llamada por dentro **demo-publica** (contenedor `onf-demo-publica-invoiceshelf_app`). El script de instancias exige 5 caracteres, y así no se confunde con **demos**, la de pruebas.
- Su `.env` lleva `APP_ENV=demo` y el dominio `demo.onfactu.com`. Todo lo de este documento solo se activa con `APP_ENV=demo`: en el resto de instancias el código está pero no hace nada.
- No está en la base central: no tiene Stripe, no sale en el dashboard y no recibe correos de alta.
- Cookie de sesión propia (`onf_demo_publica_session`) y solo para `demo.onfactu.com`, como todas las instancias desde la v.1.11.2 (ver `BASE_DE_DATOS_Y_SESIONES.md`).
- Se crea con `tools/crear_demo.sh` del repositorio de infraestructura.

## Crearla desde cero

`tools/crear_demo.sh` hace casi todo, pero hay dos cosas a tener en cuenta:

- **El registro DNS se crea antes, a mano, con la API de Cloudflare.** `cfctl.js`, igual que `crearInstancia.sh`, rechaza subdominios de 4 letras. El token y la zona están en el `.env` general (`CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ZONE_ID`). El registro es A `demo` → 51.77.216.46, sin proxy. El script comprueba que existe y se para si no.
- **Toma las variables del proceso de altas** (`worker-altas.service`), leyéndolas del proceso en marcha. Así se crea con la misma configuración que una alta real sin copiar contraseñas.

Pasos del script: crear la instancia con `crearInstancia.sh` (con el nombre provisional `demo-publica.onfactu.com`), pasar el `.env` a `demo.onfactu.com` y `APP_ENV=demo`, renombrar su bloque en el `Caddyfile` y recargar Caddy, comprobar el DNS, recrear el contenedor y generar los datos.

## Entrada

- La portada (`/`, `/login`, recuperar contraseña) muestra una pantalla propia con el botón **Entrar en la demo**.
- El botón lleva a `/demo/entrar`, que inicia sesión con el usuario `demo@onfactu.com`. Fuera del modo demo esa dirección responde 404.
- El formulario de acceso está bloqueado y las contraseñas se cambian por otras aleatorias en cada reinicio: nadie puede entrar con usuario y contraseña.

## Qué está bloqueado

El bloqueo está en el servidor (`app/Http/Middleware/DemoMode.php`), no solo en el menú.

| Nivel | Qué | Por qué |
|---|---|---|
| Todo, también la lectura | Usuarios, roles, copias, discos, configuración del correo, actualizaciones, proveedores de tipo de cambio, VeriFactu | Varias de estas pantallas devuelven credenciales al consultarlas |
| Escritura | Perfil y ajustes del usuario, logotipo, crear o borrar empresas, ajustes globales, módulos, gestoría, Stripe, recuperar contraseña, formulario de acceso | El usuario es compartido: un cambio lo verían todos los visitantes |
| Ajustes concretos | Idioma, moneda, zona horaria y formatos de fecha | Si alguien pone otro idioma, lo verían todos hasta medianoche |
| Archivos | Cualquier subida | Que nadie use la demo como alojamiento |

Los correos no salen nunca: en modo demo Laravel los simula (`Mail::fake()` en `AppServiceProvider`). Enviar una factura funciona en pantalla, pero no llega a nadie.

El menú oculta esas mismas opciones (`ocultoEnDemo()` en `AppServiceProvider`). Gestoría se oculta mientras `GESTORIA_ACTIVA` no valga 1.

**Usuarios es la excepción: se ve, pero con candado.** Es una función que vende (dar acceso al equipo con permisos por persona), y si no aparece parece que Onfactu no la tiene. Al entrar, un aviso tapa la pantalla, lo explica en una línea y ofrece crear la cuenta o volver al panel. El aviso está en `resources/views/demo/_aviso.blade.php`; para poner otra pantalla con candado basta con añadirla a su lista `BLOQUEADAS`. Por debajo, el servidor sigue bloqueando sus datos.

Módulos se oculta sin candado: es la tienda de extensiones de InvoiceShelf, que Onfactu no usa.

El idioma no se puede cambiar desde la interfaz de Onfactu. Su bloqueo en el servidor queda como defensa por si alguien llama a la API directamente.

## Reinicio diario

Orden `php artisan demo:reiniciar`, programada a las 00:00 de Madrid en `routes/console.php`. Sustituye a `reset:app` de InvoiceShelf, que hace `migrate:fresh` y dejaría la instancia vacía y sin la configuración de Onfactu.

1. **Vacía** los datos de negocio y reinicia su numeración (`LimpiezaDemo`). No toca ajustes, impuestos, formas de pago, unidades ni usuarios.
2. **Restablece** la empresa, su dirección, el usuario y los ajustes que un visitante podría romper (`AjustesDemo`).
3. **Genera** datos nuevos con fechas relativas a ese día.

Todo va en una transacción: si algo falla, la demo conserva los datos del día anterior. Se niega a actuar si `APP_ENV` no es `demo`, para que un despiste no borre la instancia de pruebas.

El azar se siembra con la fecha: el mismo día genera siempre los mismos datos, útil para reproducir un problema.

## Los datos de ejemplo

Empresa: **Estudio Nexo**, un autónomo de diseño y comunicación. Así la demo enseña la retención de IRPF y queda fiscalmente correcta:

- **Empresas**: IVA 21 % y retención del 15 %, solo en servicios
- **Particulares**: IVA 21 %, sin retención; pueden comprar productos (impresión)
- **Clientes de la UE** (Portugal y Francia): IVA intracomunitario de servicios al 0 %

| Qué | Cuántos | Detalle |
|---|---|---|
| Clientes | 18 | 11 empresas, 5 particulares, 2 de la UE, con NIF y CIF de control válido |
| Artículos | 20 | Servicios por unidad, hora y mes, y productos de impresión |
| Facturas | unas 55 | Once meses hasta hoy. Lo antiguo cobrado salvo dos vencidas y una a medias; lo reciente, mitad cobrado; dos borradores sin número |
| Recurrentes | 3 | Mensuales, con todas las facturas que ya han generado |
| Rectificativas | 2 | Totales, serie REC, con referencia cruzada en las notas |
| Cobros | los de las facturas cobradas | Transferencia sobre todo |
| Presupuestos | 15 | Aceptados, rechazados, caducados, enviados y borradores |
| Proformas | 6 | Las aceptadas enlazadas a su factura |
| Albaranes | 8 | Solo productos; con y sin precios |
| Gastos | unos 33 | Cuota de autónomos y telefonía cada mes, y gastos puntuales |

Los correos de los clientes son `@demo.onfactu.com`, dominio nuestro: si algún día se activa el envío, nada saldrá hacia terceros.

Datos inventados en `app/Services/Demo/CatalogoDemo.php`. Generadores: `MaestrosDemo`, `FacturasDemo`, `ComercialDemo` y `GastosDemo`, con lo común en `ContextoDemo`.

## Comprobaciones útiles

```bash
# Reiniciar a mano
docker exec onf-demo-publica-invoiceshelf_app php artisan demo:reiniciar

# Ver que el reinicio nocturno está programado
docker exec onf-demo-publica-invoiceshelf_app php artisan schedule:list
```

## Lo que salió al montarla (23 de septiembre de 2026)

- **La plantilla estaba atrasada.** A `plantilla.sql` le faltaba la forma de pago en los documentos, que se había añadido con SQL a mano y sin fichero de migración. Siete clientes creados después tenían la base incompleta, sin llegar a notarlo. Se arregló con una migración (v.1.11.1). Ver `BASE_DE_DATOS_Y_SESIONES.md`.
- **Caddy no veía el bloque nuevo.** El script lo renombraba con `sed -i`, que crea un fichero nuevo, y el contenedor de Caddy monta el `Caddyfile` como fichero suelto: siguió viendo el antiguo hasta reiniciarlo. Mientras tanto tampoco habría visto las altas nuevas. El script ya no usa `sed -i` sobre el `Caddyfile`.
- **La entrada fallaba con otras instancias abiertas** en el mismo navegador: todas compartían la cookie de sesión. Se arregló para todas (v.1.11.2).

## Pendiente

- **Demo del portal de gestorías**, sincronizada con esta: meses ya cerrados y la gestoría vinculada de fábrica. Se activará poniendo `GESTORIA_ACTIVA` a 1 en `AjustesDemo`.
