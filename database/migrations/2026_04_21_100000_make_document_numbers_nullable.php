<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Numeración diferida — Onfactu.
 *
 * Permite que los números de documento (invoice_number, estimate_number,
 * proforma_invoice_number, delivery_note_number) sean NULL mientras el
 * documento está en estado borrador. El número se asigna:
 *   - Facturas: al aprobar (firma VeriFactu)
 *   - Presupuestos / Proformas / Albaranes: al enviar (cambio DRAFT → SENT)
 *
 * El usuario también puede introducir un número manualmente al crear/editar
 * el borrador, y ese número se respeta (se valida unicidad por empresa).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_number')->nullable()->change();
        });

        Schema::table('estimates', function (Blueprint $table) {
            $table->string('estimate_number')->nullable()->change();
        });

        if (Schema::hasTable('proforma_invoices') && Schema::hasColumn('proforma_invoices', 'proforma_invoice_number')) {
            Schema::table('proforma_invoices', function (Blueprint $table) {
                $table->string('proforma_invoice_number')->nullable()->change();
            });
        }

        if (Schema::hasTable('delivery_notes') && Schema::hasColumn('delivery_notes', 'delivery_note_number')) {
            Schema::table('delivery_notes', function (Blueprint $table) {
                $table->string('delivery_note_number')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // No revertimos a NOT NULL porque podrían existir borradores con valor NULL
        // que bloquearían el rollback. Si se necesita revertir, limpiar los NULL primero.
    }
};
