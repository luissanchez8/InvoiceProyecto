<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forma de pago en los documentos (facturas, recurrentes, presupuestos,
 * proformas y albaranes) y texto de la forma de pago para el PDF.
 *
 * Estas columnas se añadieron con SQL a mano en las instancias que existían
 * entonces y nunca pasaron a plantilla.sql: todas las altas posteriores
 * nacieron sin ellas. Esta migración las crea donde faltan, con el mismo tipo
 * que en las instancias completas (entero sin clave foránea, y texto), y no
 * hace nada donde ya están. Se aplica sola al arrancar el contenedor.
 */
return new class extends Migration
{
    private const TABLAS = ['invoices', 'recurring_invoices', 'estimates', 'proforma_invoices', 'delivery_notes'];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            if (! Schema::hasColumn($tabla, 'payment_method_id')) {
                Schema::table($tabla, function (Blueprint $t) {
                    $t->integer('payment_method_id')->nullable();
                });
            }
        }

        if (! Schema::hasColumn('payment_methods', 'document_text')) {
            Schema::table('payment_methods', function (Blueprint $t) {
                $t->text('document_text')->nullable();
            });
        }
    }

    /**
     * Sin marcha atrás: en las instancias antiguas estas columnas ya existían
     * antes de la migración y tienen datos. Borrarlas perdería información.
     */
    public function down(): void
    {
    }
};
