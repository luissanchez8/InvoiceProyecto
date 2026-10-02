<?php

/*
 * Onfactu v.1.18.0 — Novedades que se enseñan a los clientes en la ventana de
 * novedades (VentanaNovedades.vue), la primera vez que entran después de
 * desplegar una versión que tenga entrada aquí. Solo cambios que el cliente
 * nota: los arreglos internos no se ponen.
 *
 * Para anunciar una versión nueva: añadir una entrada AL PRINCIPIO con su
 * versión (la del fichero VERSION), la fecha y los puntos. Textos cortos, en
 * lenguaje de cliente; "enlace" es opcional y lleva a la pantalla.
 * Ver Docmost → Operativa → Despliegue → Novedades para los clientes.
 */
return [
    [
        'version' => 'v.1.18.0',
        'fecha' => '2026-10-02',
        'puntos' => [
            ['titulo' => 'Exportar facturas y gastos', 'texto' => 'Descarga la lista en Excel o PDF con el botón Exportar. Sale lo que tengas filtrado.', 'enlace' => '/admin/invoices'],
            ['titulo' => 'Motivo de la rectificación', 'texto' => 'Al crear una factura rectificativa se indica el motivo, que sale en el PDF. También se puede rectificar una rectificativa.'],
            ['titulo' => 'Aviso de documento visto', 'texto' => 'Recibe un correo cuando tu cliente abra una factura o un presupuesto desde el enlace. Actívalo en Ajustes, Notificaciones.', 'enlace' => '/admin/settings/notifications'],
            ['titulo' => 'Recordar la sesión', 'texto' => 'Marca "Recordarme" al entrar y no tendrás que volver a hacerlo en 30 días.'],
        ],
    ],
    [
        'version' => 'v.1.17.0',
        'fecha' => '2026-10-01',
        'puntos' => [
            ['titulo' => 'Aceptación online de presupuestos', 'texto' => 'Tu cliente acepta o rechaza el presupuesto desde el correo, sin cuenta, y te llega un aviso.', 'enlace' => '/admin/estimates'],
            ['titulo' => 'Anticipos', 'texto' => 'Factura un anticipo de un presupuesto o una proforma. La factura final lo descuenta sola.'],
            ['titulo' => 'Varios albaranes en una factura', 'texto' => 'Marca los albaranes de un cliente en la lista y pulsa Facturar juntos.', 'enlace' => '/admin/delivery-notes'],
            ['titulo' => 'Documentos facturados', 'texto' => 'Presupuestos, proformas y albaranes enseñan si están facturados y no se pueden facturar dos veces.'],
        ],
    ],
];
