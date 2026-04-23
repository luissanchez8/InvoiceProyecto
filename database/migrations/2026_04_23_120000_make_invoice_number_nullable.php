<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onfactu: los borradores (status=DRAFT) no consumen número de serie. Por eso
 * el campo invoice_number puede quedar NULL mientras la factura esté en
 * estado DRAFT, y solo se asigna cuando el usuario la "finaliza" (botón
 * Guardar en lugar de Guardar como borrador).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_number')->nullable()->change();
            $table->integer('sequence_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        // No revertir: si algún borrador tiene invoice_number NULL, volver a
        // NOT NULL rompería la tabla. Se deja como no-op intencionalmente.
    }
};
