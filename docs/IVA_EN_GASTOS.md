# IVA en los gastos

> Copia de la página de Docmost **Funcionalidades → IVA en los gastos**. Si cambia una, hay que cambiar la otra.

Desde la v.1.15.0, cada gasto guarda su IVA desglosado: uno o varios tipos, la retención de IRPF si la lleva, y el proveedor con su NIF y el número de su factura. Con eso Onfactu calcula el IVA soportado, lo entrega a la gestoría al cerrar el mes y saca el resultado del IVA en el informe de impuestos.

Era la fase 4 del plan del 25/09/2026. Hasta entonces un gasto solo tenía el importe total y la categoría, y la gestoría tenía que pedir las facturas de gasto aparte para saber su IVA.

## Decisiones

Tomadas el 29/09/2026:

- **Proveedor opcional y sencillo**: nombre, NIF y número de su factura escritos en el propio gasto, sin lista ni menú de proveedores. Al escribir el nombre se sugieren los ya usados y se rellena su NIF. El campo Cliente del gasto sigue sirviendo para cargar el gasto a un cliente: no es el proveedor. Para corregirlos, una ventana desde el propio campo (ver **Gestionar los proveedores**), también sin menú aparte.
- **Casilla "No deducible"** en cada tipo de IVA del gasto: el IVA queda apuntado, pero no resta en el resultado. El caso típico es una comida.
- **Compras intracomunitarias con autoliquidación** (Google, Meta, Canva…): la factura llega sin IVA y ese IVA no se paga al proveedor, pero se declara a la vez en lo devengado y en lo deducible. El informe lo enseña aparte.
- **Los tipos de IVA son una lista fija del programa**, no los tipos de impuesto de cada instancia, que están duplicados y cambian de una a otra: con ellos el informe de impuestos no cuadraría.
- **Los gastos anteriores no se tocan.** Quedan como "sin desglose": se ven con su total, no suman en el IVA de gastos y se pueden desglosar al editarlos.

## Tipos de IVA

| Tipo | Porcentaje | Autoliquidación |
| --- | --- | --- |
| IVA 21 % | 21 | No |
| IVA 10 % | 10 | No |
| IVA 5 % | 5 | No |
| IVA 4 % | 4 | No |
| IVA 2 % | 2 | No |
| IVA 0 % | 0 | No |
| Exento o no sujeto | 0 | No |
| Intracomunitaria 21 % | 21 | Sí |
| Intracomunitaria 10 % | 10 | Sí |
| Intracomunitaria 4 % | 4 | Sí |

Retención de IRPF: sin retención, 7, 15 o 19 %. Como mucho 10 tipos en un mismo gasto.

La lista está en `App\Support\IvaGastos`. Añadir un tipo es añadir una línea ahí: el formulario, los informes y el cierre la leen de ese sitio.

## Cómo se calcula

- Cada línea: **cuota = base × porcentaje**, redondeada al céntimo.
- La **retención** se calcula sobre la suma de las bases.
- **Total a pagar = bases + cuotas − retención**, sin las cuotas autoliquidadas, que no se pagan al proveedor.
- El total tiene que ser mayor que cero.

**El servidor siempre recalcula.** Del formulario solo se toman los tipos, las bases y la casilla de no deducible; los totales los pone `IvaGastos::calcular()`, así que no se puede guardar un gasto con las cuentas mal hechas aunque se manipule la petición.

Ejemplo, el alquiler de demos: base 500 al 21 % (105) con retención del 19 % (95) = 510 a pagar.

## El formulario

Desde la v.1.15.1 tiene el mismo aspecto que el de las facturas:

- **Arriba**, en una tarjeta: categoría, fecha, proveedor (con NIF y número de factura), divisa, cliente y forma de pago. El proveedor es un selector igual que el de Cliente: sugiere los ya usados y deja escribir uno nuevo.
- **En medio**, la tabla de tipos, como la de artículos de una factura: tipo, base, cuota y total de cada línea, con la casilla "No deducible" debajo del tipo. Se puede escribir la base o el total de la línea: el otro se calcula solo.
- **Abajo a la derecha**, el cuadro de totales, como el de las facturas: base, IVA, IVA autoliquidado (si lo hay), la retención, que se elige ahí igual que el descuento en una factura, y el total a pagar.

Un gasto anterior a la v.1.15.0 se abre con su importe y un botón **Desglosar IVA**, que lo pasa a la tabla de tipos.

### Gestionar los proveedores

Desde la v.1.15.3, el enlace **Gestionar** junto a la etiqueta Proveedor abre una ventana con todos los proveedores escritos en los gastos: nombre, NIF, cuántos gastos tiene y cuántos son de meses cerrados, con un buscador. No hay pantalla ni menú aparte, a propósito: se pidió algo simple desde el propio gasto.

- **Editar**: corrige el nombre y el NIF en todos sus gastos a la vez.
- **Unir**: si el nombre nuevo es el de otro proveedor que ya existe, la ventana lo avisa y al guardar quedan en uno. Sirve para los que se escribieron de dos formas.
- **Quitar**: deja esos gastos sin proveedor. Los gastos no se borran. Se confirma en la misma fila.
- **Los gastos de meses cerrados no se tocan nunca**: ya se entregaron así a la gestoría. El mensaje final dice cuántos se han quedado sin cambiar.

Si el proveedor que se corrige o se quita es el del gasto que está abierto, el formulario se actualiza: si no, al guardarlo volvería a escribir el nombre antiguo.

Cada nombre se lista tal como está escrito, sin juntar mayúsculas y minúsculas, para que se vean los repetidos y se puedan unir. Hace falta el permiso de editar gastos.

En la lista de gastos sale la columna del proveedor, y los gastos sin desglose lo indican bajo el importe.

Código: `resources/scripts/admin/views/expenses/Create.vue`, con `components/expenses/` (`DesgloseIvaGasto`, `TotalesGasto`, `ProveedorGasto` y `ModalProveedores`) y la lógica en `composables/useDesgloseIva.js`.

## Datos

Los totales del gasto van en columnas nuevas de `expenses`, y cada tipo en una fila de `expense_iva_lineas`. El detalle de cada columna está en **Base de Datos → BD de cada instancia**.

`expenses.amount` sigue siendo el total a pagar, como siempre, así que todo lo que ya sumaba gastos (panel, pérdidas y ganancias) sigue funcionando sin cambios.

Rutas nuevas, declaradas antes de las de gastos para que no las confunda con un identificador:

| Ruta | Qué devuelve |
| --- | --- |
| `GET /api/v1/expenses/iva/catalogo` | Los tipos y las retenciones |
| `GET /api/v1/expenses/iva/proveedores?search=` | Los proveedores ya usados, cada uno con su último NIF |
| `GET /api/v1/expenses/iva/proveedores/gestion?search=` | La lista de la ventana Gestionar: nombre, NIF, gastos y gastos en meses cerrados |
| `PUT /api/v1/expenses/iva/proveedores` | Cambia nombre y NIF (`nombre`, `nuevo_nombre`, `nif`) en los gastos de meses abiertos |
| `POST /api/v1/expenses/iva/proveedores/quitar` | Quita el proveedor (`nombre`) de los gastos de meses abiertos |

## Informes

- **Gastos**: por categoría, con la base, el IVA y el total, y cuántos gastos van sin desglose.
- **Impuestos**: IVA de las ventas por tipo, retenciones de las ventas, IVA de los gastos por tipo (deducible y no deducible), IVA autoliquidado, gastos sin desglose, retenciones de los gastos y el **resultado**: IVA devengado (ventas más autoliquidado) menos IVA deducible. Lo calcula `App\Services\ResumenIva`, que usan el PDF y el Excel, para que los dos den siempre lo mismo.
- **Excel de gastos**: una fila por gasto con proveedor, NIF, número, base, IVA, retención, total y el desglose por tipos.

Todo en la moneda de la empresa (importe × tipo de cambio). Las ventas siguen la regla de **Estados de las facturas → Qué cuenta como venta**.

### Diseño común de los informes en PDF

Desde la v.1.15.2 los cinco informes en PDF (ventas por cliente, ventas por artículo, gastos, pérdidas y ganancias e impuestos) extienden una misma plantilla, `resources/views/app/pdf/reports/layout.blade.php`: la misma cabecera, los mismos tamaños de letra, las mismas tablas y el mismo pie con el total en verde. **El aspecto de los informes se cambia ahí, no en cada uno.**

## Gestoría y portal

El cierre de mes entrega el neto, el IVA, el IVA deducible, el autoliquidado y la retención de los gastos, y el CSV lleva proveedor, NIF, número, neto, IVA y retención de cada gasto. Ver **Gestoría → Qué se entrega** y **Portal de Gestorías → Detalle**.

## Cómo se probó

Contra una copia local del código con PostgreSQL y en demos, con los datos de `demos:preparar`, que incluyen un caso de cada cosa: retención, un gasto sin desglose, una compra intracomunitaria, un tipo no deducible y dos tipos en el mismo ticket. Las cifras que tienen que salir están en **Operativa → Pruebas en demos**. El CSV del portal y el de Onfactu se compararon byte a byte, también con una factura con descuento y retención.
