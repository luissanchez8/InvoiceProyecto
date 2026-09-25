# Base de datos y sesiones de las instancias

Dos reglas que salieron de los problemas del 23 de septiembre de 2026, al montar la demo pública.

## Cambios en la base de datos: siempre con migración

**Todo cambio en la estructura de la base de datos va en un fichero de migración** (`database/migrations/`), nunca con SQL a mano en las instancias.

Al arrancar, cada contenedor aplica solo las migraciones pendientes (`entrypoint.sh`, si existe `/data/app/database_created`). Así un cambio llega a todas las instancias en el siguiente redespliegue, y también a las altas nuevas, aunque `plantilla.sql` esté atrasada.

### Qué pasó

La forma de pago en los documentos se añadió con SQL a mano en las instancias que existían entonces. Faltaban `payment_method_id` en facturas, recurrentes, presupuestos, proformas y albaranes, y `document_text` en formas de pago. Nunca pasó a `plantilla.sql`, así que todas las altas posteriores nacieron sin esas columnas: siete clientes (anibal, celia-diaz-serrano, lacados-viana, lorenzo-aparicio-rodriguez, maria-del-carmen-garrido-serra, poliester-payma y vasiy93087). Ninguno llegó a tener errores.

Arreglo: `2026_09_30_100000_add_payment_method_to_documents.php` (v.1.11.1). Crea las columnas donde faltan, con el mismo tipo que en las instancias completas, y no hace nada donde ya están.

### Comprobar que todas las instancias tienen lo mismo

Para comparar columnas entre instancias: una consulta a `information_schema` sobre cada base `onf_app_*` con el usuario de administración de Postgres, que es superusuario.

Para saber qué ve una instancia concreta, mejor preguntarle desde dentro:

```bash
docker exec onf-<instancia>-invoiceshelf_app php artisan tinker --execute='echo Schema::hasColumn("invoices","payment_method_id") ? "SÍ" : "NO";'
```

**Tras un redespliegue, esperar antes de comprobar.** `redeploy_instancias.sh` no espera a que cada contenedor termine de arrancar. Si se comprueba justo después, las últimas instancias pueden estar todavía aplicando migraciones y parecer incompletas.

## Sesiones: una cookie por instancia

Desde la v.1.11.2, cada instancia tiene en su `.env`:

- `SESSION_DOMAIN`: su propio subdominio (antes `.onfactu.com`)
- `SESSION_COOKIE`: `onf_<instancia>_session` (antes `onfactu_session` en todas)

### Por qué

Con el dominio compartido y el mismo nombre, el navegador enviaba a cada instancia las cookies de todas las demás. Entrar en una rompía la sesión de otra: por ejemplo, con demos abierta, el botón de la demo pública no pasaba del acceso.

### Cómo se cambió sin dejar a nadie fuera

Cambiar solo el dominio no bastaba. Los navegadores conservan las cookies antiguas hasta que caducan, y la de CSRF antigua se enviaría primero, así que el acceso fallaría con "página caducada". Por eso:

- **El middleware `PurgarCookiesCompartidas`** ordena al navegador borrar las cookies antiguas en cuanto aparecen. Solo actúa en instancias ya migradas. Se podrá retirar cuando hayan caducado en todos los navegadores.
- **El nombre nuevo** hace que la cookie de sesión antigua se ignore desde el primer momento.

Efecto para los clientes: tuvieron que iniciar sesión una vez más.

### Dónde se configura

- **Instancias existentes:** `tools/cookies_por_instancia.sh <instancia>...` o `--todas`. Recrea el contenedor y se puede repetir sin problema.
- **Altas nuevas:** `crearInstancia.sh` ya pone las dos variables, tanto en el `.env` de la instancia como en el que retoca dentro del contenedor.

## Sesiones: duración y aviso de caducidad

Desde la v.1.12.2, la sesión dura **8 horas sin actividad** (`SESSION_LIFETIME=480`). Antes eran 2 horas y los clientes perdían la sesión a media mañana. Las sesiones se guardan en ficheros (`SESSION_DRIVER=file`).

Al caducar, aparece "Tu sesión ha caducado. Vuelve a iniciar sesión." y se vuelve al acceso. El aviso sale una sola vez, aunque fallen varias peticiones a la vez.

### Por qué salía "Unauthenticated." en rojo

El frontend reconocía la sesión caducada por el texto "Unauthorized" de la respuesta. Con HTTP/2, que es lo que sirve Caddy, ese texto llega vacío: no lo detectaba, mostraba el mensaje del servidor en inglés y no llevaba al acceso. Ahora se mira el código de respuesta: 401, o 419 cuando lo que ha caducado es el token de seguridad (CSRF) de la sesión. Está en `resources/scripts/helpers/error-handling.js`.

### Dónde se configura

- **Instancias existentes:** `tools/env_instancias.sh SESSION_LIFETIME 480 <instancia>...` o `--todas`. Cambia cualquier variable en el `.env`, en `conf/.env` y en el `docker-compose.yml` (que tiene prioridad sobre el `.env`), y recrea el contenedor. Se puede repetir sin problema.
  Recrea con la imagen actual pero **no registra la versión**: si se usa como despliegue, después `~/redeploy_instancias.sh --solo-version`.
- **Altas nuevas:** la plantilla `plantilla.yml` y el `.env` de `conf/` ya llevan 480.

## Cosas que cuestan descubrir

- **Nunca `sed -i` sobre un fichero que un contenedor monta suelto**, como el `Caddyfile`. `sed -i` crea un fichero nuevo y el contenedor sigue viendo el antiguo hasta reiniciarlo. Para editarlo: `sed ... > /tmp/x && cat /tmp/x > fichero`, que conserva el mismo fichero.
- **`cfctl.js` y `crearInstancia.sh` rechazan subdominios de menos de 5 letras.** Para uno así, el DNS se crea con la API de Cloudflare.
- **Un error de TLS "internal error" en un dominio nuestro** suele ser que Caddy no tiene su certificado: o no ve el bloque, o no pudo sacarlo.
- **Con HTTP/2 el texto del estado de la respuesta llega vacío.** Para reconocer un error hay que mirar el código (401, 419…), nunca el texto ("Unauthorized").
