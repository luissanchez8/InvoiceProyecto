# Pruebas en demos

> Copia de la página de Docmost **Operativa → Pruebas en demos**. Si cambia una, hay que cambiar la otra.

`demos` es la instancia donde se prueba cada versión antes de desplegarla a todas. Desde la v.1.14.6 tiene un juego de datos fijo, pequeño y con importes redondos, para saber de antemano qué tiene que salir en cada pantalla. Desde la v.1.15.0 los gastos llevan el IVA desglosado, con un caso de cada cosa que hay que probar.

## Preparar los datos

```bash
# Ensayo: enseña lo que borraría, sin tocar nada
docker exec onf-demos-invoiceshelf_app php artisan demos:preparar

# Borra los datos de negocio y crea los de prueba
docker exec onf-demos-invoiceshelf_app php artisan demos:preparar --si
```

Al terminar enseña las cifras que tienen que salir. **Se puede repetir siempre que haga falta**, por ejemplo después de aprobar o cerrar algo durante una prueba: vuelve a dejarlo todo igual.

- **Solo actúa en demos.** En cualquier otra instancia se niega, también con `--si`. No confundir con `demo:reiniciar`, que es de la demo pública.
- **Borra**: facturas, cobros, presupuestos, proformas, albaranes, recurrentes, gastos y sus categorías, artículos, clientes con sus direcciones, meses cerrados, correos enviados, notificaciones y adjuntos. También los tipos de impuesto de prueba llamados "Impuesto N".
- **Conserva**: la empresa y su dirección y logo, los usuarios, los ajustes y la numeración, los tipos de impuesto, las formas de pago y la vinculación con la gestoría.
- **Todo o nada**: si algo falla, demos se queda como estaba.
- En la central de gestorías entrega los meses nuevos, y **la gestoría recibe un correo "Mes recibido" por cada uno**. Los meses antiguos de demos no los puede borrar, porque el usuario con el que la instancia se conecta a la central no tiene ese permiso: lo avisa al terminar, y se borran con el administrador:

```bash
docker exec postgres_db_atenea psql -U onfactu_atenea_user -d onfactu_gestorias -c "DELETE FROM gestoria_cierres WHERE subdominio='demos' AND (year, month) NOT IN ((AÑO,M-3),(AÑO,M-2));"
```

  Con `AÑO`, `M-3` y `M-2` cambiados por los meses que acaba de cerrar la orden (en septiembre de 2026, `(2026,6),(2026,7)`).

Código: `app/Console/Commands/DemosPreparar.php` y, en `app/Services/Demo/`, `EscenarioPruebas` (facturas, cobros, rectificativa, cierres y cifras esperadas), `GastosPruebas` (gastos), `ComercialPruebas` (presupuesto, proforma, albarán y recurrente) y `LimpiezaPruebas` (el borrado).

## Los datos

Los meses son relativos al día en que se ejecuta: **M** es el mes en curso, **M-1** el anterior, etc. Ejecutado en septiembre de 2026: M-3 junio, M-2 julio, M-1 agosto, M septiembre.

**Los números de las facturas dependen de la serie configurada en demos**: en esta página se escriben como FAC-000001 a FAC-000006, pero en demos salen ANB000005 a ANB000010, porque su serie es ANB y empieza en el 5. Lo que cuenta es el orden: la primera factura aprobada es la de M-3 a Alfa, y el borrador, al aprobarlo, recibe el siguiente número de la serie.

Tres clientes: **Alfa Servicios S.L.** y **Beta Comercio S.L.** (empresas) y **Carmen Prueba Particular**. Tres artículos: Diseño web (1.000 €), Consultoría (100 €/h) y Mantenimiento mensual (50 €). Todo con IVA al 21 % y sin retención.

### Facturas

| Mes | Número | Cliente | Concepto | Base | IVA | Total | Cobro |
| --- | --- | --- | --- | --- | --- | --- | --- |
| M-3 (cerrado) | FAC-000001 | Alfa | Diseño web x1 | 1.000 | 210 | 1.210 | Cobrada |
| M-3 (cerrado) | FAC-000002 | Beta | Consultoría x1 | 100 | 21 | 121 | Cobrada. Rectificada |
| M-2 (cerrado) | FAC-000003 | Carmen | Consultoría x2 | 200 | 42 | 242 | Cobrada |
| M-2 (cerrado) | REC-000001 | Beta | Rectifica FAC-000002 | -100 | -21 | -121 | Devuelta |
| M-1 (abierto) | FAC-000004 | Alfa | Diseño web x2 | 2.000 | 420 | 2.420 | Cobrada la mitad (1.210) |
| M-1 (abierto) | FAC-000005 | Beta | Consultoría x7 | 700 | 147 | 847 | Sin cobrar, vencida |
| M | FAC-000006 | Carmen | Consultoría x1 | 100 | 21 | 121 | Sin cobrar |
| M (hoy) | Borrador, sin número | Alfa | Diseño web x5 | 5.000 | 1.050 | 6.050 | No cuenta en nada |

**El borrador es grande a propósito**: si alguna pantalla lo suma, se nota enseguida.

Las facturas se crean en borrador y se aprueban con el mismo servicio que el botón Aprobar, así que el número lo pone Onfactu.

### Gastos

| Mes | Proveedor | Concepto | Desglose | Total |
| --- | --- | --- | --- | --- |
| M-3 (cerrado) | Inmuebles Centro S.L. | Alquiler | Base 500 al 21 % (105), retención del 19 % (95) | 510 |
| M-3 (cerrado) | Sin proveedor | Material de oficina | Sin desglose, como los gastos anteriores a la v.1.15.0 | 121 |
| M-2 (cerrado) | Inmuebles Centro S.L. | Alquiler | Igual que el de M-3 | 510 |
| M-1 (abierto) | Inmuebles Centro S.L. | Alquiler | Igual que el de M-3 | 510 |
| M-1 (abierto) | Google Ireland Ltd | Publicidad | Base 100, intracomunitaria al 21 %: autoliquida 21, que no se paga | 100 |
| M-1 (abierto) | Restaurante El Puerto | Comida con un cliente | Base 50 al 10 % (5), no deducible | 55 |
| M-1 (abierto) | Suministros Sur S.L. | Papelería y café | Base 100 al 21 % (21) y 50 al 10 % (5) en el mismo ticket | 176 |
| M | Inmuebles Centro S.L. | Alquiler | Igual que el de M-3 | 510 |

Del año: IVA soportado **451** (446 deducible y 5 no deducible), autoliquidado **21** y retenciones **380**. Los importes los calcula `IvaGastos`, igual que al guardar desde la pantalla.

### Lo demás

- **Gastos**: ocho, en la tabla de abajo. Total 2.492 €.
- **Presupuesto** PRE-000001 a Beta, enviado: 10 horas, 1.210 €.
- **Proforma** a Carmen, en borrador y sin número: 1.210 €.
- **Albarán** ALB-000001 a Alfa, entregado: 3 horas, 363 €.
- **Recurrente** a Beta: mantenimiento mensual de 60,50 €, sin aprobación ni envío automáticos. Empieza el día 1 del mes que viene.
- **Meses cerrados**: M-3 y M-2, entregados a la gestoría. M-1 se deja abierto para probar el cierre.

## Qué probar y qué tiene que salir

Las cifras son del año en curso. Si el comando se ejecuta en enero, febrero o marzo, parte de los datos cae en el año anterior: las cifras buenas son las que enseña el comando al terminar.

### 1. Panel de inicio

| Dato | Tiene que salir | Si sale esto, falla |
| --- | --- | --- |
| Facturas | 7 | 8 (cuenta el borrador) |
| Pendiente de cobro | 2.178,00 € | 8.228,00 € |
| Ventas del año | 4.840,00 € | 10.890,00 € |
| Cobros del año | 2.783,00 € | |
| Gastos del año | 2.492,00 € | |
| Beneficio neto | 291,00 € | |
| Facturas pendientes | FAC-000006, FAC-000005 y FAC-000004 | Aparece el borrador |

### 2. Lista de facturas

- **Borradores**: 1, sin número, con la libreta naranja.
- **Aprobadas**: 7, con el candado verde, incluida REC-000001.
- **Pendientes de cobro**: FAC-000004, FAC-000005 y FAC-000006.

### 3. Ficha de cada cliente

| Cliente | Ventas | Si sale esto, falla |
| --- | --- | --- |
| Alfa | 3.630,00 € | 9.680,00 € (suma el borrador) |
| Beta | 847,00 € | |
| Carmen | 363,00 € | |

En la pestaña de documentos, las facturas dicen **Aprobada** o **Borrador**, en español y con su icono.

### 4. Informes

Periodo: el año en curso.

| Informe | Tiene que salir |
| --- | --- |
| Ventas por cliente | Alfa 3.630, Beta 847 (con REC-000001 en negativo), Carmen 363. Total **4.840,00 €** |
| Ventas por artículo | Diseño web 3.000, Consultoría 1.000. Total **4.000,00 €** |
| Impuestos | Ventas: IVA 21 % **840,00 €** (si sale 252,00 €, cuenta solo las cobradas, como antes de la v.1.14.5). Gastos: deducible **446,00 €** y no deducible 5,00 €. Autoliquidado 21,00 €, que suma en lo devengado y en lo deducible. Un gasto sin desglose (121,00 €). Resultado **394,00 €** |
| Gastos | Alquiler 2.040, Material 297, Publicidad 100, Comidas 55. Total **2.492,00 €**, con uno sin desglose |
| Pérdidas y ganancias | Ingresos 2.783, gastos 2.492, ganancia neta **291,00 €** |

Con el periodo del trimestre en curso (M-2 a M, de julio a septiembre en 2026), el de impuestos da **609,00 €** de IVA de ventas y un resultado de **268,00 €**.

Los cinco informes en PDF tienen que verse con el mismo diseño: la misma cabecera, los mismos tamaños de letra y el mismo pie con el total en verde.

Las descargas en Excel tienen que dar lo mismo. En ventas por artículo, la columna de cantidad de Consultoría sale 12 en vez de 10: suma la hora de la rectificativa en positivo. Es un fallo menor que viene del programa base, apuntado en **Mejoras a Implementar**.

### 5. Cierre de mes

En la pantalla de gestoría:

- **M-3 y M-2 cerrados**, con estos totales:

| Mes | Facturas | Neto | IVA | Bruto | Gastos | IVA de gastos |
| --- | --- | --- | --- | --- | --- | --- |
| M-3 | 2 | 1.100,00 | 231,00 | 1.331,00 | 2 (631,00) | 105,00 |
| M-2 | 2, una rectificativa (-121,00) | 100,00 | 21,00 | 121,00 | 1 (510,00) | 105,00 |

- **Aviso ámbar** de gastos sin desglose, por el material de M-3.

- **Aviso arriba**: "Todavía no has cerrado" M-1.
- **Cerrar M-1**: el resumen previo tiene que decir 2 facturas, neto 2.700,00, IVA 567,00, bruto 3.267,00 y 4 gastos por 841,00 con 136,00 de IVA, sin borradores pendientes. Al cerrarlo, la gestoría recibe el correo y el mes aparece en el portal.
- **Bloqueo**: FAC-000001 no se puede editar, y un gasto nuevo con fecha de M-3 se rechaza.

El plazo del IVA en día hábil solo se ve en el aviso de abril, julio, octubre y enero. Para comprobarlo antes:

```bash
docker exec onf-demos-invoiceshelf_app php artisan tinker --execute='$c = App\Http\Controllers\V1\Admin\ClosedMonth\ClosedMonthController::class; echo $c::plazoIva(2026,10)->format("d/m/Y")." ".$c::plazoIva(2027,1)->format("d/m/Y");'
```

Tiene que salir `20/10/2026 01/02/2027`: el 30 de enero de 2027 es sábado.

### 6. Portal de gestorías

Entrar en `gestoria.onfactu.com` con la Gestoría de Pruebas:

- Demos aparece con M-3 y M-2 (y M-1 si se ha cerrado en el paso anterior), con las mismas cifras, el IVA de los gastos bajo cada mes ("IVA 105,00", e "IVA 136,00" en M-1) y el aviso de gastos sin desglose.
- **Detalle**: el gasto de Google con "Autoliquidado 21,00" bajo el IVA, el del restaurante con "No deducible 5,00" y el material de M-3 con "Sin desglose" bajo el total.
- El CSV de M-2 lleva FAC-000003 (242,00), REC-000001 (-121,00, "Rectifica a FAC-000002") y el alquiler con su número de factura, el proveedor, su NIF, neto 500,00, IVA 105,00, retención 95,00 y bruto 510,00. Tiene que ser idéntico al CSV de ese mes que se descarga en Onfactu.

### 7. Aprobar

- **El borrador**: la ventana de aprobar dice que recibirá el siguiente número de la serie (**FAC-000007**, o ANB000011 en demos). Al aprobarlo, el panel pasa a 8 facturas, 10.890,00 € de ventas y 8.228,00 € pendientes.
- **Fecha fuera de orden**: un borrador nuevo con fecha de M-1 (sin cerrar ese mes) no se aprueba: avisa de que la fecha es anterior a la de FAC-000006 y ofrece aprobarlo con la de hoy.
- **Fecha en mes cerrado**: un borrador con fecha de M-3 avisa de que es de un mes cerrado y ofrece la de hoy.

### 8. Enviar un borrador

Poner un correo propio en el cliente Alfa y enviar el borrador: el PDF dice **BORRADOR** con el número "Pendiente", y el adjunto se llama `Borrador.pdf`.

Al terminar, `demos:preparar --si` deja todo como al principio.

## Cómo se comprobó

El 29/09/2026, con la v.1.15.0 a la v.1.15.2 (IVA en los gastos), se comprobaron en demos las cifras de esta página, el portal y los informes. Antes, en local, el CSV del portal y el de Onfactu salieron idénticos en tres meses y con una factura con descuento y retención.

El 29/09/2026 se ejecutó en demos y se comprobaron a mano el panel, la lista de facturas, las fichas de cliente, los informes, el cierre de agosto y el portal: todo cuadró.

Antes de entregarla se ejecutó contra una copia local del código de la v.1.14.5 con PostgreSQL: el ensayo, la preparación, repetirla, negarse en otra instancia y la central sin permiso de borrado. El panel, la ficha de cliente, los cinco informes en PDF, los tres en Excel, la pantalla de cierre, el resumen de M-1, los plazos del IVA y la aprobación del borrador dieron exactamente las cifras previstas entonces, antes del IVA en los gastos.
