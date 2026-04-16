# MEJORAS.md

Registro de mejoras aplicadas al proyecto InvoiceShelf. Cada sección documenta qué se cambió, por qué, y qué archivos están involucrados.

---

## Mejora 1: Nueva plantilla PDF de factura profesional (`invoice4`)

**Fecha:** 2026-03-20
**Referencia visual:** `mejoras/FAC-001_ejemplo.pdf`

### Motivación

Las plantillas PDF existentes (`invoice1`, `invoice2`, `invoice3`) tienen un diseño básico que no se ajusta a las necesidades del proyecto. Se requería un formato profesional con:
- Cabecera con logo a la izquierda y título "FACTURA" con metadatos a la derecha
- Datos del emisor claramente visibles bajo la cabecera
- Datos del cliente y dirección de envío en dos columnas con etiquetas en itálica
- Tabla de conceptos con cabecera gris y bordes finos
- Bloque de totales limpio con "Total a pagar" destacado
- Sección de notas y términos en dos columnas
- Espacio para firma
- Paginación en el pie

### Archivos creados

| Archivo | Descripción |
|---------|-------------|
| `resources/views/app/pdf/invoice/invoice4.blade.php` | Plantilla Blade completa con HTML y CSS inline que replica el diseño del PDF de ejemplo. Contiene comentarios extensos en cada sección explicando su propósito y las variables Blade utilizadas. |
| `mejoras/MEJORAS.md` | Este archivo de documentación de mejoras. |

### Archivos modificados

| Archivo | Cambio realizado |
|---------|-----------------|
| `lang/es.json` | Se añadieron 12 nuevas claves de traducción para textos específicos de la plantilla (`pdf_invoice_number_short`, `pdf_invoice_date_short`, `pdf_invoice_due_date_short`, `pdf_invoice_customer_data`, `pdf_invoice_shipping_address`, `pdf_invoice_concept`, `pdf_invoice_base_amount`, `pdf_invoice_total_to_pay`, `pdf_invoice_terms`, `pdf_invoice_signature`, `pdf_invoice_page`, `pdf_invoice_of`). |
| `lang/en.json` | Se añadieron las mismas 12 claves con sus equivalentes en inglés. |

### Archivos NO modificados (y por qué)

- **`app/Models/Invoice.php`** — No necesita cambios. El método `getPDFData()` ya carga cualquier plantilla por nombre de archivo automáticamente.
- **`app/Traits/GeneratesPdfTrait.php`** — No necesita cambios. Las funciones `getFieldsArray()` y `getFormattedString()` ya proveen todas las variables necesarias.
- **`app/Space/PdfTemplateUtils.php`** — No necesita cambios. Descubre las plantillas automáticamente buscando archivos `*.blade.php` en el directorio de plantillas de factura.
- **`resources/views/app/pdf/invoice/partials/table.blade.php`** — No se reutiliza porque el diseño de la tabla en `invoice4` es sustancialmente diferente (sin columna de numeración, cabecera con fondo gris, columnas renombradas).
- **Plantillas existentes** (`invoice1.blade.php`, `invoice2.blade.php`, `invoice3.blade.php`) — No se modifican para preservar la compatibilidad con facturas ya generadas.

### Estructura de la plantilla `invoice4.blade.php`

La plantilla se divide en las siguientes secciones (en orden de aparición):

```
1. CABECERA (header-table)
   ├── Logo empresa (izquierda) — usa ImageUtils::toBase64Src()
   └── Título "FACTURA" + Nº + Fecha + Vencimiento (derecha)

2. DATOS DEL EMISOR (issuer-section)
   └── Dirección empresa formateada — usa $company_address

3. DIRECCIONES (addresses-table)
   ├── Datos del cliente (izquierda) — usa $billing_address
   └── Dirección de envío (derecha) — usa $shipping_address

4. TABLA DE CONCEPTOS (items-table)
   ├── Cabecera: Concepto | [Custom Fields] | Cantidad | Precio | [Descuento] | [Impuesto] | Total
   └── Filas: un <tr> por cada $invoice->items

5. BLOQUE DE TOTALES (totals-table)
   ├── Subtotal
   ├── Descuento (condicional)
   ├── Base Imponible (condicional, si hay descuento)
   ├── Impuestos (iterados por tipo)
   ├── Total a pagar (negrita, grande)
   ├── Importe pagado (condicional, si hay pagos)
   └── Importe pendiente (condicional, si hay pagos)

6. NOTAS + TÉRMINOS (footer-table)
   ├── Notas (izquierda) — usa $notes
   └── Términos (derecha) — espacio reservado

7. FIRMA (signature-section)

8. PAGINACIÓN (page-footer)
```

### Nuevas claves de traducción

| Clave | Español | Inglés |
|-------|---------|--------|
| `pdf_invoice_number_short` | Nº | No. |
| `pdf_invoice_date_short` | Fecha | Date |
| `pdf_invoice_due_date_short` | Vencimiento | Due date |
| `pdf_invoice_customer_data` | Datos del cliente | Customer data |
| `pdf_invoice_shipping_address` | Dirección de envío | Shipping address |
| `pdf_invoice_concept` | Concepto | Concept |
| `pdf_invoice_base_amount` | Base Imponible | Taxable base |
| `pdf_invoice_total_to_pay` | Total a pagar | Total to pay |
| `pdf_invoice_terms` | Términos | Terms |
| `pdf_invoice_signature` | Firma | Signature |
| `pdf_invoice_page` | Pág. | Page |
| `pdf_invoice_of` | de | of |

### Cómo usar la nueva plantilla

1. Crear o editar una factura desde el panel de administración
2. En la vista de la factura, pulsar el botón de seleccionar plantilla
3. Seleccionar **invoice4** de la lista de plantillas disponibles
4. Al descargar o previsualizar el PDF, se usará el nuevo diseño

### Dependencias técnicas

- **Motor PDF:** Compatible con DomPDF (configurado en `.env` como `PDF_DRIVER=dompdf`)
- **Fuente:** Satoshi (Regular + Black), ya incluida en `resources/static/fonts/`
- **Función de moneda:** `format_money_pdf()` de `app/Space/helpers.php`
- **Conversión de logo:** `\App\Space\ImageUtils::toBase64Src()` de `app/Space/ImageUtils.php`
- **Variables Blade:** Inyectadas por `Invoice::getPDFData()` en `app/Models/Invoice.php`

---

## Mejora 2: Facturas Proforma y Albaranes

**Fecha:** 2026-03-20

### Motivación

Se necesitan dos nuevos tipos de documento que funcionen como las facturas:
- **Factura Proforma**: documento previo a la factura real, convertible a factura. Similar a los presupuestos pero con aspecto de factura.
- **Albarán (Delivery Note)**: documento de entrega con toda la estructura de una factura, pero con opción de ocultar precios en el PDF.

Ambos tipos tienen toggle on/off a nivel de empresa y menú lateral condicional.

### Archivos creados (backend)

| Archivo | Descripción |
|---------|-------------|
| `database/migrations/2026_03_20_122624_create_proforma_invoices_table.php` | Tabla principal de facturas proforma. Misma estructura que invoices sin campos de pago. Añade `converted_invoice_id` para la relación con la factura resultante. |
| `database/migrations/2026_03_20_122626_create_proforma_invoice_items_table.php` | Líneas de ítems de facturas proforma. Misma estructura que invoice_items. |
| `database/migrations/2026_03_20_122627_create_delivery_notes_table.php` | Tabla principal de albaranes. Misma estructura que invoices sin campos de pago. Añade `show_prices` (boolean) para controlar visibilidad de precios en PDF. |
| `database/migrations/2026_03_20_122629_create_delivery_note_items_table.php` | Líneas de ítems de albaranes. |
| `app/Models/ProformaInvoice.php` | Modelo Eloquent. Estados: DRAFT, SENT, VIEWED, ACCEPTED, REJECTED. Método `convertToInvoice()` para convertir a factura real. CRUD estáticos. PDF con flag `is_proforma`. |
| `app/Models/ProformaInvoiceItem.php` | Modelo de línea de proforma con relaciones a Tax e Item. |
| `app/Models/DeliveryNote.php` | Modelo Eloquent. Estados: DRAFT, SENT, DELIVERED. Campo `show_prices`. CRUD estáticos. PDF con flag `is_delivery_note` y `show_prices`. |
| `app/Models/DeliveryNoteItem.php` | Modelo de línea de albarán. |
| `app/Http/Controllers/V1/Admin/ProformaInvoice/ProformaInvoicesController.php` | CRUD completo (index, store, show, update, delete). |
| `app/Http/Controllers/V1/Admin/ProformaInvoice/ChangeProformaInvoiceStatusController.php` | Cambio de estado (SENT, ACCEPTED, REJECTED). |
| `app/Http/Controllers/V1/Admin/ProformaInvoice/ConvertProformaInvoiceController.php` | Convierte proforma en factura real. |
| `app/Http/Controllers/V1/Admin/DeliveryNote/DeliveryNotesController.php` | CRUD completo para albaranes. |
| `app/Http/Controllers/V1/Admin/DeliveryNote/ChangeDeliveryNoteStatusController.php` | Cambio de estado (SENT, DELIVERED). |
| `app/Policies/ProformaInvoicePolicy.php` | Autorización Bouncer para proformas (view, create, edit, delete, send, deleteMultiple). |
| `app/Policies/DeliveryNotePolicy.php` | Autorización Bouncer para albaranes. |

### Archivos creados (frontend)

| Archivo | Descripción |
|---------|-------------|
| `resources/scripts/admin/stores/proforma-invoice.js` | Store Pinia con CRUD, cambio de estado y conversión a factura. |
| `resources/scripts/admin/stores/delivery-note.js` | Store Pinia con CRUD y cambio de estado. |
| `resources/scripts/admin/stub/proforma-invoice.js` | Estructura de datos por defecto para nueva proforma. |
| `resources/scripts/admin/stub/proforma-invoice-item.js` | Estructura de datos por defecto para línea de proforma. |
| `resources/scripts/admin/stub/delivery-note.js` | Estructura de datos por defecto para nuevo albarán. Incluye `show_prices: true`. |
| `resources/scripts/admin/stub/delivery-note-item.js` | Estructura de datos por defecto para línea de albarán. |
| `resources/scripts/admin/views/proforma-invoices/Index.vue` | Listado paginado de facturas proforma con filtros y badges de estado. |
| `resources/scripts/admin/views/proforma-invoices/View.vue` | Vista detalle con botón "Convertir a factura". |
| `resources/scripts/admin/views/delivery-notes/Index.vue` | Listado paginado de albaranes. |
| `resources/scripts/admin/views/delivery-notes/View.vue` | Vista detalle de albarán con indicador de show_prices. |

### Archivos modificados

| Archivo | Cambio |
|---------|--------|
| `app/Providers/AppServiceProvider.php` | Importados `ProformaInvoicePolicy` y `DeliveryNotePolicy`. Registrados 4 nuevos Gate::define() para send y deleteMultiple de ambos tipos. |
| `config/abilities.php` | Añadidos imports de `ProformaInvoice` y `DeliveryNote`. Añadidas 10 nuevas abilities (5 por tipo: view, create, edit, delete, send) con sus dependencias. |
| `config/invoiceshelf.php` | Añadidas 2 entradas en `main_menu`: "Facturas Proforma" (option_key: OPCION_MENU_PROFORMA, icon: DocumentDuplicateIcon) y "Albaranes" (option_key: OPCION_MENU_ALBARANES, icon: TruckIcon). |
| `routes/api.php` | Añadidas rutas API: apiResource para proforma-invoices y delivery-notes, más rutas custom para status y convert. |
| `resources/scripts/admin/admin-router.js` | Añadidos imports de componentes de proforma y albarán. Añadidas 4 rutas Vue: index y view para cada tipo. |
| `resources/scripts/admin/stub/abilities.js` | Añadidas 10 constantes de abilities (5 por tipo). |
| `lang/es.json` | Añadidas 27 claves de traducción (navegación, PDF, estados, acciones, toggles). |
| `lang/en.json` | Añadidas las mismas 27 claves en inglés. |

### Datos de configuración insertados

| Tabla | Clave | Valor | Descripción |
|-------|-------|-------|-------------|
| `app_config` | `OPCION_MENU_PROFORMA` | `0` | Toggle de menú para facturas proforma (desactivado por defecto) |
| `app_config` | `OPCION_MENU_ALBARANES` | `0` | Toggle de menú para albaranes (desactivado por defecto) |
| `abilities` | `view/create/edit/delete/send-proforma-invoice` | — | Permisos asignados al usuario admin |
| `abilities` | `view/create/edit/delete/send-delivery-note` | — | Permisos asignados al usuario admin |

### Cómo activar los nuevos módulos

Los nuevos tipos de documento están **desactivados por defecto**. Para activarlos:

1. Modificar en la tabla `app_config`:
   - `OPCION_MENU_PROFORMA` → `1` (activa facturas proforma)
   - `OPCION_MENU_ALBARANES` → `1` (activa albaranes)
2. Recargar la página. Los nuevos items aparecerán en el menú lateral.

### API Endpoints nuevos

**Facturas Proforma:**
```
GET    /api/v1/proforma-invoices              — Listado
POST   /api/v1/proforma-invoices              — Crear
GET    /api/v1/proforma-invoices/{id}         — Ver
PUT    /api/v1/proforma-invoices/{id}         — Actualizar
POST   /api/v1/proforma-invoices/delete       — Eliminar masivo
POST   /api/v1/proforma-invoices/{id}/status  — Cambiar estado
POST   /api/v1/proforma-invoices/{id}/convert — Convertir a factura
```

**Albaranes:**
```
GET    /api/v1/delivery-notes              — Listado
POST   /api/v1/delivery-notes              — Crear
GET    /api/v1/delivery-notes/{id}         — Ver
PUT    /api/v1/delivery-notes/{id}         — Actualizar
POST   /api/v1/delivery-notes/delete       — Eliminar masivo
POST   /api/v1/delivery-notes/{id}/status  — Cambiar estado
```

### Flujo de estados

**Factura Proforma:** `DRAFT` → `SENT` → `VIEWED` → `ACCEPTED` / `REJECTED`
- Al aceptar, se puede convertir a factura real

**Albarán:** `DRAFT` → `SENT` → `DELIVERED`

### Campo show_prices del albarán

El campo `show_prices` (boolean, default true) en la tabla `delivery_notes` controla si los precios, impuestos y totales se muestran en el PDF del albarán. Cuando `show_prices = false`, el PDF solo muestra conceptos y cantidades.

### Problemas encontrados y soluciones

#### 1. Bouncer scope (abilities no reconocidas)

**Problema:** Al crear abilities con `Bouncer::allow($user)->to('ability-name')` sin establecer el scope primero, las abilities se crean con `scope=NULL` en la tabla `abilities`. El middleware `ScopeBouncer` filtra por `scope=company_id`, por lo que las abilities sin scope no se encuentran y las rutas Vue devuelven 404 (el router guard rechaza el acceso).

**Solución:** Antes de crear abilities, establecer el scope de Bouncer:
```php
Bouncer::scope()->to($companyId);
Bouncer::allow($user)->to('view-proforma-invoice');
```

**Regla:** SIEMPRE usar `Bouncer::scope()->to($companyId)` antes de asignar abilities. Las abilities existentes del sistema tienen `scope` = company_id, no NULL.

#### 2. BaseTable requiere fetchData async

**Problema:** Las vistas Index iniciales pasaban datos estáticos a `BaseTable :data="array"`, lo que no funciona. BaseTable espera una **función async** como `:data="fetchData"`.

**Solución:** Implementar `fetchData({ page, filter, sort })` que devuelve `{ data, pagination: { totalPages, currentPage, totalCount, limit } }`. Los datos de cada fila se acceden con `row.data.campo` (no `row.campo`).

#### 3. Controller response format para paginación

**Problema:** Envolver el paginador de Laravel en `response()->json(['data' => $paginator])` rompe la estructura de paginación que BaseTable espera (`last_page`, `total`, etc.).

**Solución:** Devolver `$paginator->toArray()` directamente como JSON, añadiendo campos extra al array si es necesario:
```php
$response = $paginator->toArray();
$response['custom_total_count'] = Model::whereCompany()->count();
return response()->json($response);
```

---

## Mejora 2b: Formularios de creación y personalización de numeración

**Fecha:** 2026-03-20

### Motivación

Los formularios de creación de facturas proforma y albaranes estaban como placeholder.
Se necesitaban formularios completos funcionales y la configuración de numeración
(como existe para facturas en Ajustes → Personalización → Facturas).

### Archivos creados

| Archivo | Descripción |
|---------|-------------|
| `app/Http/Requests/ProformaInvoicesRequest.php` | FormRequest con validación y `getProformaInvoicePayload()`. Misma lógica que `InvoicesRequest` sin campos de pago. |
| `app/Http/Requests/DeliveryNotesRequest.php` | FormRequest con validación, `getDeliveryNotePayload()` e incluye `show_prices`. |
| `resources/scripts/admin/views/settings/customization/proforma-invoices/ProformaInvoicesTab.vue` | Tab contenedor para la sección de personalización de proformas. |
| `resources/scripts/admin/views/settings/customization/proforma-invoices/ProformaInvoicesTabNumber.vue` | Usa `NumberCustomizer` con `type="proformainvoice"` y serie "PRF". |
| `resources/scripts/admin/views/settings/customization/delivery-notes/DeliveryNotesTab.vue` | Tab contenedor para la sección de personalización de albaranes. |
| `resources/scripts/admin/views/settings/customization/delivery-notes/DeliveryNotesTabNumber.vue` | Usa `NumberCustomizer` con `type="deliverynote"` y serie "ALB". |

### Archivos modificados

| Archivo | Cambio |
|---------|--------|
| `app/Http/Controllers/V1/Admin/General/NextNumberController.php` | Añadidos imports de `ProformaInvoice` y `DeliveryNote`. Añadidos 4 cases al switch: `proforma_invoice`, `proformainvoice`, `delivery_note`, `deliverynote` (2 por tipo para compatibilidad entre stores y NumberCustomizer). |
| `app/Http/Controllers/V1/Admin/ProformaInvoice/ProformaInvoicesController.php` | `store()` y `update()` ahora usan `ProformaInvoicesRequest` en vez de `Request` genérico. |
| `app/Http/Controllers/V1/Admin/DeliveryNote/DeliveryNotesController.php` | `store()` y `update()` ahora usan `DeliveryNotesRequest`. |
| `resources/scripts/admin/stores/proforma-invoice.js` | Reescritura completa: añadidos getters de cálculo (`getSubTotal`, `getTotalTax`, `getTotal`, etc.), gestión de ítems (`addItem`, `updateItem`, `removeItem`), `fetchProformaInvoiceInitialSettings()`, templates, notas. Patrón idéntico a invoice.js. |
| `resources/scripts/admin/stores/delivery-note.js` | Reescritura completa: mismos getters y acciones que proforma-invoice.js. |
| `resources/scripts/admin/views/proforma-invoices/create/ProformaInvoiceCreate.vue` | Reescritura completa: formulario funcional con selector de cliente, fechas, ítems (CreateItems), totales (CreateTotal), notas (CreateNotesField), campos personalizados, selector de plantilla PDF. Validación con Vuelidate. |
| `resources/scripts/admin/views/delivery-notes/create/DeliveryNoteCreate.vue` | Reescritura completa: igual que proforma pero con toggle `show_prices` (BaseSwitch). |
| `resources/scripts/admin/views/settings/customization/CustomizationSetting.vue` | Añadidas 2 tabs nuevas: "Facturas Proforma" y "Albaranes" con imports de los nuevos componentes. |
| `config/hashids.php` | Añadidas conexiones Hashids para `ProformaInvoice` y `DeliveryNote` (necesarias para `unique_hash`). |
| `lang/es.json` | Añadidas 14 claves para personalización de numeración (NumberCustomizer). |
| `lang/en.json` | Ídem en inglés. |

### Datos de configuración insertados

| Setting | Valor | Descripción |
|---------|-------|-------------|
| `proformainvoice_number_format` | `{{SERIES:PRF}}{{DELIMITER:-}}{{SEQUENCE:6}}` | Formato de numeración de proformas (PRF-000001) |
| `deliverynote_number_format` | `{{SERIES:ALB}}{{DELIMITER:-}}{{SEQUENCE:6}}` | Formato de numeración de albaranes (ALB-000001) |

### Compatibilidad de keys de numeración

El `SerialNumberFormatter` genera la key de settings como `{modelname_lowercase}_number_format`:
- `ProformaInvoice` → `proformainvoice_number_format`
- `DeliveryNote` → `deliverynote_number_format`

El `NumberCustomizer` del frontend usa `type` como prefijo: `{type}_number_format`.
Para mantener compatibilidad, el type se pasa como `proformainvoice` y `deliverynote` (sin guiones bajos intermedios).

El `NextNumberController` soporta ambas variantes (`proforma_invoice` y `proformainvoice`) mediante cases duplicados en el switch.

---

## Mejora 2c: Vistas View con sidebar + PDF, plantilla universal, correcciones

**Fecha:** 2026-03-20

### Motivación

Las vistas de detalle de proforma y albarán no mostraban el PDF ni el sidebar lateral de navegación. Además, la plantilla `invoice4` solo servía para facturas. Se necesitaba una plantilla PDF universal que sirviera para los 4 tipos de documento (factura, presupuesto, proforma, albarán) cambiando solo el título.

### Archivos creados

| Archivo | Descripción |
|---------|-------------|
| `app/Http/Controllers/V1/PDF/ProformaInvoicePdfController.php` | Controller para generar/descargar PDF de facturas proforma. Ruta: `/proforma-invoices/pdf/{unique_hash}`. |
| `app/Http/Controllers/V1/PDF/DeliveryNotePdfController.php` | Controller para generar/descargar PDF de albaranes. Ruta: `/delivery-notes/pdf/{unique_hash}`. |
| `database/migrations/2026_03_20_162702_add_proforma_and_delivery_note_columns_to_taxes_table.php` | Añade columnas `proforma_invoice_id`, `proforma_invoice_item_id`, `delivery_note_id`, `delivery_note_item_id` a la tabla `taxes` para que los impuestos puedan asociarse a proformas y albaranes. |

### Archivos modificados

| Archivo | Cambio |
|---------|--------|
| `resources/views/app/pdf/invoice/invoice4.blade.php` | **Reescritura completa** como plantilla UNIVERSAL. Detecta el tipo de documento via flags `$is_estimate`, `$is_proforma`, `$is_delivery_note` y adapta: título (FACTURA/PRESUPUESTO/FACTURA PROFORMA/ALBARÁN), número, fechas, etiqueta de fecha secundaria. Si `$show_prices=false` (albaranes), oculta columnas de precio, impuestos y bloque de totales. |
| `app/Models/Estimate.php` | `getPDFData()` ahora comparte `$invoice` como alias de `$this` y `$is_estimate=true` para compatibilidad con invoice4. Si la plantilla no existe en `estimate/`, hace fallback a `invoice/` (así invoice4 funciona para presupuestos). |
| `routes/web.php` | Añadidas rutas PDF: `/proforma-invoices/pdf/{proformaInvoice:unique_hash}` y `/delivery-notes/pdf/{deliveryNote:unique_hash}` dentro del grupo `pdf-auth`. |
| `resources/scripts/admin/views/proforma-invoices/View.vue` | **Reescritura completa**: sidebar izquierdo con lista navegable de proformas (búsqueda, ordenación, scroll infinito, item activo resaltado) + iframe con PDF. Botones: marcar como enviada, convertir a factura, editar. |
| `resources/scripts/admin/views/delivery-notes/View.vue` | **Reescritura completa**: sidebar izquierdo + iframe PDF. Botones: marcar como enviado, marcar como entregado, editar. |
| `resources/scripts/components/base/BaseCustomerSelectPopup.vue` | Añadidos imports de `useProformaInvoiceStore` y `useDeliveryNoteStore`. Añadidos cases `proforma-invoice` y `delivery-note` en: `selectedCustomer` (computed), `selectNewCustomer()`, `resetSelectedCustomer()`, e inicialización de customerId. |
| `resources/scripts/admin/stores/invoice.js` | Template por defecto cambiado a `invoice4` (con fallback a `templates[0]`). |
| `resources/scripts/admin/stores/estimate.js` | Template por defecto cambiado a `invoice4`. |
| `resources/scripts/admin/stores/proforma-invoice.js` | Template por defecto `invoice4`. Añadido método `selectCustomer(id)` para compatibilidad con BaseCustomerSelectPopup. |
| `resources/scripts/admin/stores/delivery-note.js` | Template por defecto `invoice4`. Añadido método `selectCustomer(id)`. |
| `resources/scripts/admin/views/proforma-invoices/create/ProformaInvoiceCreate.vue` | Watcher de customer ahora sincroniza `customer_id` y `currency_id`. Cambiado `type="invoice"` a `type="proforma-invoice"` en BaseCustomerSelectPopup. |
| `resources/scripts/admin/views/delivery-notes/create/DeliveryNoteCreate.vue` | Ídem: sincroniza `customer_id`/`currency_id`, cambiado tipo a `delivery-note`. |
| `resources/scripts/admin/views/settings/customization/CustomizationSetting.vue` | **Reescritura** usando tabs manuales (sin BaseTabGroup) para evitar bug "Maximum recursive updates" de Headless UI. Tabs en orden: Facturas, Presupuestos, Facturas Proforma, Albaranes, Pagos, Artículos. |

### Detalle: Plantilla universal invoice4.blade.php

La plantilla detecta el tipo de documento mediante este bloque PHP al inicio:

```php
@php
    $docType = 'invoice';
    if (!empty($is_estimate)) $docType = 'estimate';
    if (!empty($is_proforma)) $docType = 'proforma';
    if (!empty($is_delivery_note)) $docType = 'delivery_note';

    // Título, número, fechas y etiquetas se resuelven según $docType
    // usando null coalescing: $invoice->invoice_number ?? $invoice->estimate_number ?? ...
@endphp
```

Cada modelo getPDFData() comparte estos flags:
- `Invoice`: ningún flag extra (es el default)
- `Estimate`: `$is_estimate = true`, `$invoice = $this` (alias)
- `ProformaInvoice`: `$is_proforma = true`, `$invoice = $this`
- `DeliveryNote`: `$is_delivery_note = true`, `$show_prices = $this->show_prices`, `$invoice = $this`

### Detalle: BaseCustomerSelectPopup

Este componente tiene references hardcodeadas a stores por tipo. Al añadir un nuevo tipo de documento, hay que modificar 4 puntos del componente:
1. `selectedCustomer` (computed): añadir case para el nuevo tipo
2. `selectNewCustomer()`: añadir case con `store.getNextNumber()` + `store.selectCustomer(id)`
3. `resetSelectedCustomer()`: añadir case
4. Inicialización: añadir `else if (props.customerId && props.type === '...')`

### Problema resuelto: TabGroup "Maximum recursive updates"

El componente BaseTabGroup (Headless UI) itera `slots.default()` para construir headers de tabs. Si un componente hijo modifica estado reactivo al montarse, causa un bucle infinito. Solución: reemplazar BaseTabGroup por tabs manuales (botones + `v-if`) en `CustomizationSetting.vue`.

---

## Mejora 2d: Traducción faltante "Marcar como enviado"

**Fecha:** 2026-03-20

### Problema

El botón "Marcar como enviado" en las vistas View de proforma y albarán mostraba la clave sin traducir: `general.mark_as_sent`.

### Solución

Añadida la clave `general.mark_as_sent` a ambos archivos de idioma:

| Archivo | Clave | Valor |
|---------|-------|-------|
| `lang/es.json` | `general.mark_as_sent` | Marcar como enviado |
| `lang/en.json` | `general.mark_as_sent` | Mark as sent |

### Nota sobre convenciones de traducción

- Claves compartidas entre tipos de documento: usar prefijo `general.*`
- Claves específicas de un tipo: usar prefijo `{tipo}s.*` (ej: `invoices.mark_as_sent`)
- Antes de añadir una clave nueva, verificar que no exista ya con otro prefijo

---

## Mejora 3: Panel de Asistencia

**Fecha:** 2026-04-15

### Motivación

El rol `asistencia` (soporte de Onfactu) necesita un panel donde pueda configurar opciones de la instancia sin tocar la BD directamente: activar/desactivar secciones del menú según el plan del cliente, y consultar qué plan tiene contratado.

### Archivos creados

| Archivo | Descripción |
|---------|-------------|
| `app/Http/Controllers/V1/Admin/Settings/AppConfigController.php` | Controlador con endpoints `index`, `update`, `planFromStripe`. Solo accesible para `role = 'asistencia'`. |
| `resources/scripts/admin/views/settings/AppConfigSetting.vue` | Vista con 3 secciones: plan contratado + botón consulta, toggles de menú, otros ajustes. |

### Archivos modificados

| Archivo | Cambio |
|---------|--------|
| `routes/api.php` | Rutas GET/PUT `/v1/app-config` y GET `/v1/app-config/plan-from-stripe` |
| `config/database.php` | Añadida conexión `stripe` para consultar `users_onfactu_stripe` |
| `resources/scripts/admin/admin-router.js` | Ruta `/admin/settings/app-config` con flag `asistencia_only` |
| `app/Providers/AppServiceProvider.php` | `generateMenu()` propaga `asistencia_only` |

### Notas técnicas

- Los toggles usan propiedad `.enabled` (boolean) separada del `.value` (string) porque `BaseSwitch` solo acepta Boolean. Conversión `'1'` ↔ `true` al cargar/guardar.
- La conexión `stripe` usa `onfactu_atenea_user` como fallback de credenciales (no `invoiceshelf` que no tiene acceso a esa BD).
- El botón "Consultar plan" busca por email del admin (primer user con role `super admin`) en `users_onfactu_stripe`.

---

## Mejora 4: Seguridad multicapa para opciones de menú

**Fecha:** 2026-04-15

### Motivación

Cuando una opción de menú se desactivaba en `app_config`, el enlace del sidebar desaparecía pero la ruta seguía siendo accesible escribiendo la URL directamente. Esto es un fallo de seguridad: un cliente con plan Essential podía acceder a Pagos o Gastos escribiendo la URL manualmente.

### Archivos creados

| Archivo | Descripción |
|---------|-------------|
| `app/Http/Middleware/CheckMenuOption.php` | Middleware que devuelve 403 si el path contiene un segmento deshabilitado según `app_cfg()` |

### Archivos modificados

| Archivo | Cambio |
|---------|--------|
| `bootstrap/app.php` | Registrado alias `check-menu` |
| `routes/api.php` | Middleware aplicado a grupos `bouncer` (admin) y `auth:customer,customer-portal` (customer) |
| `config/invoiceshelf.php` | Eliminadas entradas duplicadas de proformas/albaranes en `main_menu` + añadido `option_key` en customer_menu (facturas, presupuestos, pagos) |
| `app/Http/Controllers/V1/Admin/General/BootstrapController.php` | Añadido `disabled_menu_options` a la respuesta |
| `app/Http/Controllers/V1/Customer/General/BootstrapController.php` | Igual para customer |
| `resources/scripts/admin/stores/global.js` | Campo `disabledMenuOptions` en state |
| `resources/scripts/customer/stores/global.js` | Idem + propagación al store admin para el router guard |
| `resources/scripts/router/index.js` | Guard que redirige al dashboard si la ruta es deshabilitada |

### Mapeo ruta → app_config

```
invoices           → OPCION_MENU_FACTURAS
estimates          → OPCION_MENU_PRESUPUESTOS
proforma-invoices  → OPCION_MENU_PROFORMAS
delivery-notes     → OPCION_MENU_ALBARANES
recurring-invoices → OPCION_MENU_FRA_RECURRENTE
payments           → OPCION_MENU_PAGOS
expenses           → OPCION_MENU_GASTOS
```

---

## Mejora 5: Vista de cliente con pestañas (admin)

**Fecha:** 2026-04-15

### Motivación

La vista `/admin/customers/:id/view` solo mostraba una gráfica. No se podían ver los documentos asociados a ese cliente sin ir a cada sección por separado y filtrar. Se añaden pestañas para ver todo agrupado.

### Archivos creados

| Archivo | Descripción |
|---------|-------------|
| `resources/scripts/admin/views/customers/partials/CustomerDocumentsTab.vue` | Componente genérico que recibe `customerId`, `docType`, `viewRoute` y muestra una tabla paginada con los documentos de ese tipo filtrados por cliente. Usa `BaseInvoiceStatusBadge` + `BaseInvoiceStatusLabel` para los estados. |

### Archivos modificados

| Archivo | Cambio |
|---------|--------|
| `resources/scripts/admin/views/customers/View.vue` | Reescrito para añadir sistema de pestañas: Dashboard + Facturas + Presupuestos + Pagos + Proformas + Albaranes + Gastos. Las pestañas se filtran automáticamente por `disabledMenuOptions` del store global. |

### Notas técnicas

- Los controladores existentes (`InvoicesController`, etc.) ya soportan el filtro `?customer_id=X` via `applyFilters` — no se crean endpoints nuevos.
- La gráfica original se mantiene intacta en la pestaña "Dashboard".
- Los estados se muestran como texto (ENVIADO, BORRADOR, PAGADO) usando los componentes Base ya existentes, igual que en el portal de cliente.
