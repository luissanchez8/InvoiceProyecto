# Estados de las facturas

> Copia de la página de Docmost **Funcionalidades → Estados de las facturas**. Si cambia una, hay que cambiar la otra.

Desde la v.1.13.0 una factura solo tiene dos estados: **Borrador** y **Aprobada**. Se sale del borrador de una sola forma, aprobando, y al aprobar es cuando recibe su número y queda cerrada.

Enviada, vista y cobrada ya no son estados: son información que se apunta aparte, con su fecha.

## Por qué se cambió

Antes había cinco estados (borrador, enviada, vista, completada y aprobada) y varios caminos sacaban una factura del borrador **sin darle número**: marcarla como enviada, completarla o registrar un cobro. Una factura de anibal llegó a su cliente así, sin número.

Se tomó como modelo Holded: aprobar es un paso propio, que numera y bloquea la factura; lo demás (enviarla, que el cliente la abra, cobrarla) no cambia su estado. Es también lo que pide la normativa: una factura emitida no se modifica, se rectifica.

## Los dos estados

| Estado | Icono | Qué significa | Se puede |
| --- | --- | --- | --- |
| **Borrador** | Libreta naranja | En preparación. Sin número y sin valor fiscal | Editar, borrar, enviar para revisión, aprobar |
| **Aprobada** | Candado verde | Emitida: tiene número y no se modifica | Enviar, cobrar, rectificar |

Los iconos están en `resources/scripts/components/icons/estados/` (`IconoBorrador.vue` e `IconoAprobada.vue`) y los usa `components/estados/EstadoFactura.vue`, que enseña el estado en las listas y en la ficha.

La lista de facturas tiene las pestañas **Borradores**, **Aprobadas** y **Pendientes de cobro**.

## Aprobar

Botón **Aprobar** en la ficha de la factura, en el menú de la lista y en el formulario. Al pulsarlo se abre una ventana que, **antes de confirmar**, comprueba la factura y dice qué número va a recibir. Si hay un problema, lo explica en la misma ventana.

Lo hace `App\Services\AprobarFactura`, el único sitio del código que saca una factura del borrador:

1. **Comprueba que está completa**: necesita un cliente y al menos una línea.
2. **Comprueba el mes**: si la fecha es de un mes cerrado, no se aprueba (ver **Gestoría → Cierre de mes**).
3. **Le asigna el número** de la serie. Si el primer número libre es un hueco entre facturas antiguas y la fecha no encaja ahí, usa el siguiente al más alto. Un borrador numerado de antes de la v.1.13 conserva su número.
4. **Comprueba el orden de las fechas** con las facturas ya aprobadas: ninguna con número menor puede tener fecha posterior, ni ninguna con número mayor fecha anterior. Las rectificativas no cuentan, porque tienen su propia serie.
5. **La marca como Aprobada** y guarda cuándo (`approved_at`).

Si la fecha falla (mes cerrado o fuera de orden), la ventana ofrece **Aprobar con la fecha de hoy**, siempre que con esa fecha sí encaje. La fecha de vencimiento se mueve con ella, conservando los días de plazo.

**Dos aprobaciones a la vez no pueden llevarse el mismo número**: cada una bloquea la fila de su empresa en la base de datos hasta terminar.

Si la empresa tiene VeriFactu activo, la factura se envía a la AEAT justo después de aprobarla (`App\Services\VerifactuPublicador`).

### Varias a la vez

En la lista se pueden seleccionar borradores y aprobarlos juntos (hasta 200). Se aprueban **por orden de fecha**, para que la numeración salga en orden, y al terminar se dice cuáles se aprobaron y cuáles no, con el motivo.

### Guardar y aprobar desde el formulario

**Guardar** deja siempre la factura en borrador. **Aprobar** la guarda y abre la ventana de aprobar; si se cancela, se queda como borrador guardado.

El número del formulario no se edita: sale el previsto, y el definitivo se asigna al aprobar.

## Lo que ya no cambia el estado

| Acción | Qué hace ahora |
| --- | --- |
| **Enviar por correo** | Apunta que se ha enviado y cuándo (`sent_at`) |
| **Marcar como enviada** | Lo mismo, para lo enviado fuera de Onfactu |
| **El cliente la abre** desde el enlace del correo | Apunta que la ha visto y cuándo (`viewed_at`) |
| **Registrar un cobro** | Solo en facturas aprobadas. Un borrador no admite cobros |
| **Marcar como cobrada** | La da por cobrada sin registrar un cobro. Solo en aprobadas. Sustituye a "Marcar como completada" |

La ficha enseña el envío y la visita como texto, junto a los botones.

## Qué cuenta como venta

Solo las facturas **aprobadas**. Un borrador no está emitido y no suma en ningún total:

- el panel de inicio: ventas, pendiente de cobro, número de facturas y facturas vencidas recientes;
- la ficha de cliente: gráfico y total de ventas;
- los informes en PDF y en Excel: ventas por cliente, ventas por artículo y resumen de impuestos;
- el cierre de mes y el portal de la gestoría (ver **Gestoría → Qué se entrega**).

**El resumen de impuestos cuenta el IVA de todas las facturas aprobadas del periodo, se hayan cobrado o no**, porque el IVA se declara por la fecha de la factura. Hasta la v.1.14.5 solo contaba las cobradas, que era lo que hacía el programa base. Las rectificativas restan. Desde la v.1.15.0 el informe lleva también el IVA de los gastos y el resultado (ver **IVA en los gastos → Informes**).

Esto estaba en el plan de la fase 2 y se quedó sin hacer al desplegarla: el panel y los informes siguieron sumando los borradores hasta la v.1.14.5.

## Un borrador se puede enviar, pero lo dice

Un borrador se puede mandar al cliente para que lo revise. Su PDF sale con el título **BORRADOR** en vez de FACTURA, con el número "Pendiente", y el adjunto del correo se llama `Borrador.pdf`.

Durante unas horas (v.1.14.3) se prohibió enviar borradores, porque su PDF salía como una factura normal con el número en blanco, y un cliente podía contabilizarlo o pagarlo como tal. En la v.1.14.4 se volvió a permitir, marcando el PDF para que no se pueda confundir.

Si el asunto del correo usa el número de factura, en un borrador sale vacío.

## Plantilla del PDF

Todas las facturas nuevas salen con la plantilla universal, `invoice4`. Desde la v.1.14.3 lo impone el servidor (`Invoice::PLANTILLA_PDF`) en cualquier factura nueva o en borrador y en la que se aprueba, venga de donde venga: creada a mano, duplicada, generada por una recurrente, convertida desde un presupuesto, proforma o albarán, o rectificativa. Las recurrentes también la llevan siempre, y el selector de plantilla ya no aparece en los formularios.

**Las facturas aprobadas conservan la plantilla con la que se emitieron**, aunque sea una antigua: ya se entregaron así al cliente y no deben cambiar de aspecto. Por eso quedan facturas antiguas con `invoice1` en algunas instancias.

Por qué hizo falta: la plantilla única solo se ponía al crear una factura desde cero en pantalla. Duplicar copiaba la plantilla de la original, las recurrentes nacían con la primera de la lista (`invoice1`) y la pasaban a cada factura que generaban, y las conversiones arrastraban la suya. Una instancia llegó a tener 141 facturas con la antigua y 4 con la universal, y el mismo cliente recibía PDF con dos diseños distintos.

Desde la v.1.17.0, presupuestos, proformas y albaranes también la llevan siempre, incluidos los que ya existían, porque no son documentos fiscales (ver **Presupuestos, proformas y albaranes**).

## Recurrentes

Cada recurrente tiene la opción **Aprobar automáticamente** (`auto_approve`):

- **Activada**: cada factura que genera se aprueba al crearse, con su número.
- **Desactivada**: la genera en borrador, para revisarla y aprobarla a mano.

**El envío automático solo manda facturas aprobadas.** Una recurrente con envío automático y sin aprobación automática deja el borrador y no lo envía.

## Rectificativas

Solo se rectifica una factura **aprobada**, y la rectificativa nace aprobada. Ver **Facturas Rectificativas**.

## Portal del cliente

El cliente final no ve los borradores en su portal: solo las facturas aprobadas.

## Cómo se pasaron las facturas existentes

Con la migración `2026_10_01_100000_estados_factura_borrador_aprobada.php`, que se aplicó sola en cada instancia al desplegar:

- Enviada, vista, completada o aprobada **con número** → **Aprobada**, con la fecha de aprobación que tuviera o, si no, la de su última modificación.
- Las mismas **sin número** → **Borrador**, porque no se habían emitido de verdad. Entre ellas, la de anibal, que tiene que aprobarla y volver a enviarla a su cliente.
- La fecha de envío se recuperó del registro de correos enviados (`email_logs`).
- Las recurrentes que tenían envío automático pasaron a tener también aprobación automática, para que siguieran enviándose como antes.

No tiene vuelta atrás: no se guardó el estado anterior de cada factura.

## Columnas nuevas

| Columna | Para qué |
| --- | --- |
| `invoices.approved_at` | Cuándo se aprobó |
| `invoices.sent_at` | Cuándo se envió |
| `invoices.viewed_at` | Cuándo la abrió el cliente |
| `recurring_invoices.auto_approve` | Si las facturas que genera se aprueban solas |

## Dónde está en el código

| Qué | Dónde |
| --- | --- |
| Aprobar | `app/Services/AprobarFactura.php`, `ApproveInvoiceController`, `ApproveMultipleInvoicesController` |
| Ver el número antes de aprobar | `ApprovalPreviewController` (`GET /api/v1/invoices/{invoice}/approve-preview`) |
| Enviada y cobrada | `ChangeInvoiceStatusController` |
| Motivos para no aprobar | `app/Exceptions/AprobacionFacturaException.php`, con un código por caso (`incompleta`, `mes_cerrado`, `fecha_anterior`, `fecha_posterior`) |
| Plantilla y número obligatorio | Los `saving` de `booted()` en `app/Models/Invoice.php`: ninguna factura sale del borrador sin número, por el camino que sea |
| Ventana de aprobar | `admin/components/modal-components/AprobarFacturaDialog.vue` y `admin/composables/useAprobarFactura.js` |
