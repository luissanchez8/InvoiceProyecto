<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onfactu v.1.16.0 — Abrir un mes cerrado para corregirlo (fase 5).
 *
 *   closed_months: si el mes está abierto para corregir, hasta cuándo, quién
 *   lo abrió y por qué, y cuántas veces se ha abierto.
 *
 *   closed_month_changes: el registro de lo que se hace con el mes abierto
 *   (abrirlo, cada documento creado, editado o borrado, y volver a cerrarlo).
 *   Es lo que se le cuenta a la gestoría al volver a cerrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('closed_months', function (Blueprint $table) {
            if (! Schema::hasColumn('closed_months', 'reopened_at')) {
                $table->timestamp('reopened_at')->nullable();
                $table->unsignedInteger('reopened_by')->nullable();
                $table->string('reopen_reason', 500)->nullable();
                $table->timestamp('reopen_expires_at')->nullable();
                $table->unsignedSmallInteger('reopen_count')->default(0);
            }
        });

        if (! Schema::hasTable('closed_month_changes')) {
            Schema::create('closed_month_changes', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id');
                $table->unsignedBigInteger('closed_month_id');
                $table->unsignedSmallInteger('year');
                $table->unsignedTinyInteger('month');
                $table->unsignedSmallInteger('reapertura');      // número de la reapertura (1, 2...)
                $table->unsignedInteger('user_id')->nullable();
                $table->string('accion', 20);                     // abierto, creado, editado, borrado, cerrado
                $table->string('tipo', 20)->nullable();           // gasto, cobro, presupuesto, proforma, albaran
                $table->unsignedBigInteger('documento_id')->nullable();
                $table->string('descripcion', 500);
                $table->timestamp('created_at')->nullable();

                $table->index(['closed_month_id', 'reapertura']);
                $table->foreign('closed_month_id')->references('id')->on('closed_months')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('closed_month_changes');
        Schema::table('closed_months', function (Blueprint $table) {
            $table->dropColumn(['reopened_at', 'reopened_by', 'reopen_reason', 'reopen_expires_at', 'reopen_count']);
        });
    }
};
