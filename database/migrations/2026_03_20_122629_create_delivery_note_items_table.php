<?php

/**
 * Migración: create_delivery_note_items_table
 *
 * Crea la tabla de líneas/ítems de albaranes.
 * Misma estructura que invoice_items con FK a delivery_notes.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_note_items', function (Blueprint $table) {
            $table->id();

            // --- Datos del ítem ---
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('discount_type')->nullable();
            $table->unsignedBigInteger('price');
            $table->decimal('quantity', 15, 2);
            $table->string('unit_name')->nullable();

            // --- Cálculos por línea (céntimos) ---
            $table->decimal('discount', 15, 2)->nullable();
            $table->bigInteger('discount_val')->default(0);
            $table->bigInteger('tax')->default(0);
            $table->bigInteger('total')->default(0);

            // --- Multi-moneda ---
            $table->decimal('exchange_rate', 19, 6)->nullable();
            $table->unsignedBigInteger('base_price')->nullable();
            $table->bigInteger('base_discount_val')->nullable();
            $table->bigInteger('base_tax')->nullable();
            $table->bigInteger('base_total')->nullable();

            // --- Relaciones ---
            $table->unsignedBigInteger('delivery_note_id')->nullable();
            $table->unsignedInteger('item_id')->nullable();
            $table->unsignedInteger('company_id')->nullable();

            $table->foreign('delivery_note_id')->references('id')->on('delivery_notes')->cascadeOnDelete();
            $table->foreign('item_id')->references('id')->on('items')->nullOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_note_items');
    }
};
