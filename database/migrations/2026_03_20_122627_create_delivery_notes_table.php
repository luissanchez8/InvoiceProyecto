<?php

/**
 * Migración: create_delivery_notes_table
 *
 * Crea la tabla principal de albaranes. Replica la estructura de invoices
 * sin campos de pago. Añade campo show_prices (boolean) que controla
 * si los precios se muestran en el PDF generado.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->id();

            // --- Datos principales ---
            $table->dateTime('delivery_note_date')->nullable();
            $table->date('delivery_date')->nullable();                 // Fecha de entrega prevista
            $table->string('delivery_note_number')->nullable();        // Número único (ej: ALB-000001)
            $table->string('reference_number')->nullable();

            // --- Estado: DRAFT, SENT, DELIVERED ---
            $table->string('status')->nullable();

            // --- Configuración de impuestos y descuentos ---
            $table->string('tax_per_item')->default('NO');
            $table->string('discount_per_item')->default('NO');

            // --- Notas y plantilla ---
            $table->text('notes')->nullable();
            $table->string('template_name')->nullable();

            // --- Descuento global ---
            $table->string('discount_type')->nullable();
            $table->decimal('discount', 15, 2)->nullable();
            $table->bigInteger('discount_val')->nullable();

            // --- Totales (céntimos) ---
            $table->bigInteger('sub_total')->nullable();
            $table->bigInteger('total')->nullable();
            $table->bigInteger('tax')->nullable();

            // --- Control de precios en PDF ---
            // Si es false, el PDF se genera sin mostrar precios ni totales
            $table->boolean('show_prices')->default(true);

            // --- Control de envío/visualización ---
            $table->boolean('sent')->default(false);
            $table->boolean('viewed')->default(false);

            // --- Hash para acceso público ---
            $table->string('unique_hash')->nullable();

            // --- Multi-moneda ---
            $table->unsignedInteger('currency_id')->nullable();
            $table->decimal('exchange_rate', 19, 6)->nullable();
            $table->bigInteger('base_discount_val')->nullable();
            $table->bigInteger('base_sub_total')->nullable();
            $table->bigInteger('base_total')->nullable();
            $table->bigInteger('base_tax')->nullable();

            // --- Sales tax ---
            $table->string('sales_tax_type')->nullable();
            $table->string('sales_tax_address_type')->nullable();

            // --- Secuencias de numeración ---
            $table->integer('sequence_number')->nullable();
            $table->integer('customer_sequence_number')->nullable();

            // --- Relaciones ---
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('creator_id')->nullable();

            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $table->foreign('creator_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('currency_id')->references('id')->on('currencies')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_notes');
    }
};
