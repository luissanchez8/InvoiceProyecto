<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onfactu v.1.15.0 — IVA en los gastos (fase 4).
 *
 * En el gasto:
 *   - proveedor_nombre, proveedor_nif, numero_factura: el proveedor, opcional.
 *   - con_desglose: si el IVA está desglosado. Los gastos anteriores quedan
 *     en false ("sin desglose"): no se les inventa un tipo.
 *   - base_imponible, cuota_iva, cuota_deducible, cuota_autoliquidada,
 *     retencion_porcentaje, retencion: totales del desglose, en la moneda del
 *     gasto y en céntimos, para no recalcularlos en cada informe. Los calcula
 *     siempre el servidor (App\Support\IvaGastos).
 *
 * Cada tipo de IVA del gasto es una fila de expense_iva_lineas. Un ticket con
 * productos al 10 % y al 21 % tiene dos.
 *
 * Solo añade columnas y una tabla: los gastos existentes no cambian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('expenses', 'proveedor_nombre')) {
                $table->string('proveedor_nombre', 190)->nullable();
                $table->string('proveedor_nif', 30)->nullable();
                $table->string('numero_factura', 60)->nullable();
                $table->boolean('con_desglose')->default(false);
                $table->bigInteger('base_imponible')->nullable();
                $table->bigInteger('cuota_iva')->nullable();
                $table->bigInteger('cuota_deducible')->nullable();
                $table->bigInteger('cuota_autoliquidada')->nullable();
                $table->decimal('retencion_porcentaje', 5, 2)->nullable();
                $table->bigInteger('retencion')->nullable();
                $table->index(['company_id', 'proveedor_nombre']);
            }
        });

        if (! Schema::hasTable('expense_iva_lineas')) {
            Schema::create('expense_iva_lineas', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('expense_id');
                $table->foreign('expense_id')->references('id')->on('expenses')->onDelete('cascade');
                $table->unsignedInteger('company_id')->nullable();
                $table->string('tipo', 20);
                $table->decimal('porcentaje', 5, 2);
                $table->bigInteger('base');
                $table->bigInteger('cuota');
                $table->boolean('deducible')->default(true);
                $table->boolean('autoliquidacion')->default(false);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->timestamps();
                $table->index('expense_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_iva_lineas');

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'proveedor_nombre']);
            $table->dropColumn([
                'proveedor_nombre', 'proveedor_nif', 'numero_factura', 'con_desglose', 'base_imponible',
                'cuota_iva', 'cuota_deducible', 'cuota_autoliquidada', 'retencion_porcentaje', 'retencion',
            ]);
        });
    }
};
