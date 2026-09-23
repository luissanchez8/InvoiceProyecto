# Demo pública de Onfactu

Instancia abierta al público en **demo.onfactu.com**. Cualquiera entra con un botón, sin registrarse, y prueba Onfactu con una empresa de ejemplo. Cada noche a las 00:00 (hora de Madrid) vuelve a su estado inicial con datos nuevos.

## Cómo está montada

- Instancia normal de Onfactu llamada por dentro **demo-publica** (contenedor `onf-demo-publica-invoiceshelf_app`). El script de instancias exige 5 caracteres, y así no se confunde con **demos**, la de pruebas.
- Su `.env` lleva `APP_ENV=demo` y el dominio `demo.onfactu.com`. Todo lo de este documento solo se activa con `APP_ENV=demo`: en el resto de instancias el código está pero no hace nada.
- No está en la base central: no tiene Stripe, no sale en el dashboard y no recibe correos de alta.
- Se crea con `tools/crear_demo.sh` del repositorio de infraestructura.

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

## Pendiente

- **Demo del portal de gestorías**, sincronizada con esta: meses ya cerrados y la gestoría vinculada de fábrica. Se activará poniendo `GESTORIA_ACTIVA` a 1 en `AjustesDemo`.
