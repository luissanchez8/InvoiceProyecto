<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onfactu v.1.18.0 — Motivo de la rectificación. Lo pide el reglamento de
 * facturación (la causa de la rectificación tiene que constar) y se escribe
 * también en las notas de la rectificativa, que salen en el PDF. Las
 * rectificativas anteriores quedan sin motivo guardado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoices', 'rectificacion_motivo')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->text('rectificacion_motivo')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'rectificacion_motivo')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('rectificacion_motivo');
            });
        }
    }
};
