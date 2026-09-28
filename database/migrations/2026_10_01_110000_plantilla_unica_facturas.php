<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Onfactu v.1.14.3 — Una sola plantilla PDF para las facturas.
 *
 * Pasa a la plantilla universal (invoice4) lo que todavía no se ha emitido:
 * los borradores y las recurrentes (que se la pasan a cada factura que
 * generan). Las facturas aprobadas conservan su plantilla: ya se entregaron
 * así al cliente y no deben cambiar de aspecto.
 *
 * Solo cambia el nombre de la plantilla; no toca importes, números ni fechas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'template_name')) {
            DB::table('invoices')
                ->where('status', 'DRAFT')
                ->where(function ($q) {
                    $q->whereNull('template_name')->orWhere('template_name', '<>', 'invoice4');
                })
                ->update(['template_name' => 'invoice4']);
        }

        if (Schema::hasTable('recurring_invoices') && Schema::hasColumn('recurring_invoices', 'template_name')) {
            DB::table('recurring_invoices')
                ->where(function ($q) {
                    $q->whereNull('template_name')->orWhere('template_name', '<>', 'invoice4');
                })
                ->update(['template_name' => 'invoice4']);
        }
    }

    public function down(): void
    {
        // Sin vuelta atrás: no se guarda qué plantilla tenía cada borrador.
    }
};
