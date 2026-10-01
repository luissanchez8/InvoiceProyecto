# Presupuestos, proformas y albaranes

> Copia de la página de Docmost **Funcionalidades → Presupuestos, proformas y albaranes**. Si cambia una, hay que cambiar la otra.

Desde la v.1.17.0, los presupuestos, las proformas y los albaranes tienen dos estados: el comercial y el de facturación. Además se numeran al crearlos, quedan enlazados a su factura y se bloquean al facturarlos. Un presupuesto se puede aceptar desde el correo y se pueden facturar anticipos. También se pueden juntar varios albaranes en una factura.

Era la fase 3 del plan del 25/09/2026. Hasta entonces, convertir un documento en factura no dejaba rastro: se podía facturar dos veces, editarlo después o borrarlo. Cada documento tenía además su propia forma de convertirse, y no todas funcionaban igual. Los albaranes no se podían facturar desde la pantalla porque faltaba la ruta.

## Decisiones

Tomadas el 30/09/2026, siguiendo cómo lo hace Holded y lo que pide la ley para los anticipos:

- **Número al crearlos**, también en borrador. No son documentos fiscales, así que un hueco en la numeración no importa, y un documento sin número no se puede nombrar al cliente ni enlazar a su factura. Desaparece "Guardar como borrador" sin número.
- **Dos estados separados.** El comercial es el de siempre: enviado, visto, aceptado, rechazado o caducado, y en los albaranes, entregado. El de facturación es nuevo: *Sin facturar*, *Anticipo* o *Facturado*.
- **Facturado es bloqueado.** No se edita, no se borra y no se vuelve a facturar. Si se borra la factura (solo se pueden borrar los borradores), el documento se desbloquea.
- **Una sola forma de convertir.** La factura nace en borrador, como todas, y queda enlazada al documento.
- **Anticipos con factura de verdad.** El IVA de un anticipo se debe en el momento de cobrarlo (artículo 75.2 de la Ley del IVA), así que no basta con apuntarlo. La factura final lo resta con su base y su IVA.
- **Aceptación online sin cuenta.** El cliente usa el enlace del correo, como el que ya servía para ver el PDF.
- **Plantilla única**: la de las facturas, también para los documentos ya hechos, porque no son fiscales.
- **Convertir un presupuesto ya no lo borra nunca.** Queda aceptado y enlazado a su factura. Por eso se ha quitado de Ajustes → Personalización → Presupuestos la opción "Al convertir el presupuesto" (no hacer nada, borrarlo o marcarlo como aceptado).

## Los dos estados

| Estado de facturación | Cuándo | Qué se puede hacer |
| --- | --- | --- |
| Sin facturar | No tiene ninguna factura | Todo |
| Anticipo | Tiene una o más facturas de anticipo, pero no la final | Todo: editarlo, más anticipos o la factura final |
| Facturado | Tiene su factura final, aunque siga en borrador | Verlo, enviarlo, duplicarlo y cambiar su estado comercial. No se edita, no se borra ni se vuelve a facturar |

En las listas, el estado de facturación sale al lado del comercial. "Sin facturar" no se enseña porque es lo normal. La lista de albaranes tiene además la pestaña **Sin facturar**.

**Un presupuesto cuenta como facturado si lo está la proforma o el albarán que salió de él.** Y al revés: si el presupuesto ya está facturado, su proforma o su albarán no se pueden facturar ("Sale del presupuesto PRE-4, que ya está facturado"). Así no se factura dos veces lo mismo por dos caminos. Sí se puede convertir un presupuesto facturado en albarán, porque se puede entregar después de facturar.

La pantalla de cada documento enseña, encima del PDF, sus facturas con enlace, lo anticipado y lo pendiente, el presupuesto del que sale o lo que salió de él. En los presupuestos enseña también la respuesta del cliente.

## Convertir en factura

Desde el menú de cada documento: **Convertir en factura**. Es la misma operación para los tres (`ConvertirEnFactura`):

- La factura nace en borrador, sin número y con la plantilla única. Se abre al crearla.
- Lleva las líneas, los impuestos, el descuento, la forma de pago y las notas del documento.
- **Resta los anticipos** ya facturados del documento y de su presupuesto: una línea en negativo por cada línea del anticipo, con el texto "Anticipo según factura FAC-X del dd/mm/aaaa", la misma base y el mismo IVA. Si queda algún anticipo en borrador, pide aprobarlo o borrarlo antes. Los anticipos anulados con una rectificativa no se restan.
- Si los anticipos ya cubren el total, no deja hacerla.
- El presupuesto o la proforma pasa a **aceptado**.

### Varios albaranes en una factura

En la lista de albaranes, se marcan los que se quieran y se pulsa **Facturar juntos**. Tienen que ser del mismo cliente y la misma moneda, estar sin facturar y llevar los impuestos de la misma forma (todos por línea, o todos en el total con los mismos tipos). Si no, avisa y hay que facturarlos por separado.

Cada línea de la factura lleva delante el número de su albarán ("ALB-000002 · Consultoría"), y las notas, la lista de albaranes con su fecha.

## Anticipos

Desde el menú de un presupuesto o una proforma: **Facturar anticipo**, por porcentaje del total o por importe (con impuestos). Crea una factura en borrador y la abre (`FacturarAnticipo`).

- Es una factura normal, de la serie de siempre, con el texto "Anticipo del presupuesto PRE-4 (40 %)".
- **Una línea por cada combinación de impuestos** del documento, con la parte proporcional de su base. Así cada tipo de IVA (o la retención) lleva lo suyo. Con impuestos en el total, es una sola línea.
- Se pueden hacer varios, pero entre todos tienen que dejar algo pendiente: el último cobro se hace con la factura final.
- El documento pasa a **Anticipo**.

Comprobado con un presupuesto con dos tipos de impuesto por línea, retención y descuento: el anticipo más la factura final suman exactamente el total del presupuesto.

## Aceptación online del presupuesto

El botón del correo del presupuesto ("Ver y responder") lleva a `/presupuesto/{token}`. Es una página suelta, sin entrar en la aplicación, con los datos del presupuesto, el enlace al PDF y dos botones, **Aceptar presupuesto** y **Rechazar**, con un nombre y un comentario opcionales.

- El token es el del registro del envío (`email_logs`), el mismo que ya servía para ver el PDF, y caduca igual.
- Al responder, el presupuesto pasa a aceptado o rechazado y se guarda quién y cuándo. La empresa recibe un correo ("Presupuesto PRE-4 aceptado") con el comentario y un botón al presupuesto. Va al correo de la empresa, el mismo que se usa para "Responder a".
- No enseña los botones si ya está respondido, aceptado o facturado, si el enlace ha caducado o si ha pasado la fecha de validez.
- Antes de aceptar o rechazar, pide confirmación en una ventana como las de Onfactu, y al confirmar se envía una sola vez.
- Al abrirlo el cliente, el presupuesto pasa a **Visto**. Si lo abre alguien de la empresa con la sesión de Onfactu abierta, no cuenta como visto.
- Máximo 10 respuestas por minuto desde el mismo enlace y la misma conexión (limitador `presupuesto-publico`, en `RouteServiceProvider`). Si se pasa, la página avisa de que espere un minuto.

## Número y plantilla

- Los tres documentos reciben número al guardarse (`DocumentoComercial`). Los que no tenían número al desplegar se numeraron por orden de fecha.
- Siempre con la plantilla única de las facturas (`invoice4`), que cambia el título según el documento. Ya no hay selector de plantilla en los formularios.

## Datos

- `documentos_facturados`: qué factura sale de qué documento, con su tipo (`final` o `anticipo`) y el total de la factura al enlazarla. Varios albaranes en una factura son varias filas con la misma factura. Se borra sola con la factura.
- `billing_status` en `estimates`, `proforma_invoices` y `delivery_notes`: `PENDIENTE`, `ANTICIPO` o `FACTURADO`.
- `estimate_id` en proformas y albaranes: el presupuesto del que salieron.
- `respuesta_at`, `respuesta_nombre` y `respuesta_comentario` en presupuestos.

El detalle está en **Base de Datos → BD de cada instancia**. Las proformas convertidas antes de la v.1.17.0 (`converted_invoice_id`) quedaron enlazadas y facturadas. Los presupuestos y albaranes convertidos antes no guardaban su factura, así que siguen como "Sin facturar".

## Rutas

| Ruta | Qué hace |
| --- | --- |
| `POST /api/v1/estimates/{id}/convert-to-invoice` | Presupuesto a factura |
| `POST /api/v1/proforma-invoices/{id}/convert` | Proforma a factura |
| `POST /api/v1/delivery-notes/{id}/convert-to-invoice` | Albarán a factura (nueva) |
| `POST /api/v1/facturacion/convertir` | Varios documentos en una factura (`tipo`, `ids`) |
| `POST /api/v1/facturacion/anticipo` | Factura de anticipo (`tipo`, `id`, `modo`: porcentaje o importe, `valor`) |
| `GET/POST /presupuesto/{token}` | La página pública del presupuesto y la respuesta del cliente |

El bloqueo lo pone el middleware `CheckDocumentoFacturado`, en el mismo grupo de rutas que el de meses cerrados, para que no se escape ninguna ruta.

## Código

- `app/Services/Facturacion/`: `Documentos` (los tres tipos en un solo sitio), `EstadoFacturacion`, `ConvertirEnFactura`, `FacturarAnticipo`, `LineasFactura` (líneas e importes, calculados igual que el formulario de factura) y `FacturaBorrador`.
- `app/Models/Concerns/DocumentoComercial.php`: número al crear, plantilla y estado de facturación en los tres modelos.
- `Invoice::booted`: al borrar una factura, recalcula los documentos que salieron de ella.
- `PresupuestoPublicoController` y `resources/views/presupuesto/publico.blade.php`: la aceptación online.
- Pantallas: `components/facturacion/` (`EstadoFacturacionBadge`, `PanelFacturacion`, `ModalAnticipo` y `useBloqueoFacturado`), los menús de los tres documentos y la lista de albaranes.

## Cómo se probó

En local con PostgreSQL y en el navegador:

- anticipo del 40 %, la factura final que lo resta, e intentos de facturar dos veces;
- un presupuesto con IVA y retención por línea y con descuento: anticipo más final igual al total;
- dos albaranes en una factura;
- borrar la factura final, que devuelve el documento a Anticipo;
- de presupuesto a proforma y a factura, restando el anticipo del presupuesto;
- el albarán de un presupuesto ya facturado, rechazado;
- editar y borrar un documento facturado, rechazado;
- abrir y guardar las facturas generadas, sin que cambie ninguna cifra;
- aceptar desde la página pública.

Los casos de demos están en **Operativa → Pruebas en demos**.
