<?php

/**
 * Migración: añade columnas de FK para facturas proforma y albaranes
 * en la tabla taxes.
 *
 * La tabla taxes almacena impuestos que pueden pertenecer a distintos
 * tipos de documento (invoice, estimate, recurring_invoice, etc.).
 * Estas nuevas columnas permiten asociar impuestos a proformas y albaranes,
 * tanto a nivel de documento como a nivel de ítem.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxes', function (Blueprint $table) {
            // FK a facturas proforma (impuesto a nivel de documento)
            $table->unsignedBigInteger('proforma_invoice_id')->nullable()->after('recurring_invoice_id');
            // FK a líneas de factura proforma (impuesto a nivel de ítem)
            $table->unsignedBigInteger('proforma_invoice_item_id')->nullable()->after('proforma_invoice_id');
            // FK a albaranes (impuesto a nivel de documento)
            $table->unsignedBigInteger('delivery_note_id')->nullable()->after('proforma_invoice_item_id');
            // FK a líneas de albarán (impuesto a nivel de ítem)
            $table->unsignedBigInteger('delivery_note_item_id')->nullable()->after('delivery_note_id');
        });
    }

    public function down(): void
    {
        Schema::table('taxes', function (Blueprint $table) {
            $table->dropColumn([
                'proforma_invoice_id',
                'proforma_invoice_item_id',
                'delivery_note_id',
                'delivery_note_item_id',
            ]);
        });
    }
};
