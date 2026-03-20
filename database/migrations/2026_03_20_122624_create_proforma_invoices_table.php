<?php

/**
 * Migración: create_proforma_invoices_table
 *
 * Crea la tabla principal de facturas proforma. La estructura replica
 * la tabla invoices pero sin campos de pago (due_amount, paid_status, overdue)
 * ya que las proforma no son documentos de cobro.
 * Añade converted_invoice_id para registrar la factura real resultante
 * cuando se convierte la proforma.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proforma_invoices', function (Blueprint $table) {
            $table->id();

            // --- Datos principales del documento ---
            $table->dateTime('proforma_invoice_date')->nullable();
            $table->date('expiry_date')->nullable();                   // Fecha de validez (en vez de due_date)
            $table->string('proforma_invoice_number')->nullable();     // Número único por empresa (ej: PRF-000001)
            $table->string('reference_number')->nullable();            // Referencia opcional del cliente

            // --- Estado del documento ---
            // Valores posibles: DRAFT, SENT, VIEWED, ACCEPTED, REJECTED
            $table->string('status')->nullable();

            // --- Configuración de impuestos y descuentos ---
            $table->string('tax_per_item')->default('NO');             // YES/NO: impuesto por línea
            $table->string('discount_per_item')->default('NO');        // YES/NO: descuento por línea

            // --- Notas y plantilla ---
            $table->text('notes')->nullable();
            $table->string('template_name')->nullable();               // Nombre de plantilla PDF (ej: 'invoice4')

            // --- Descuento global ---
            $table->string('discount_type')->nullable();               // 'percentage' o 'fixed'
            $table->decimal('discount', 15, 2)->nullable();            // Porcentaje o valor del descuento
            $table->bigInteger('discount_val')->nullable();            // Valor calculado del descuento (en céntimos)

            // --- Totales (almacenados en céntimos para precisión) ---
            $table->bigInteger('sub_total')->nullable();               // Subtotal antes de impuestos
            $table->bigInteger('total')->nullable();                   // Total final
            $table->bigInteger('tax')->nullable();                     // Total de impuestos

            // --- Control de envío/visualización ---
            $table->boolean('sent')->default(false);                   // Si fue enviada por email
            $table->boolean('viewed')->default(false);                 // Si el destinatario la vio

            // --- Hash para acceso público (URLs de descarga PDF) ---
            $table->string('unique_hash')->nullable();

            // --- Conversión a factura real ---
            // Se rellena cuando la proforma se convierte a factura
            $table->unsignedBigInteger('converted_invoice_id')->nullable();

            // --- Soporte multi-moneda ---
            $table->unsignedInteger('currency_id')->nullable();
            $table->decimal('exchange_rate', 19, 6)->nullable();
            $table->bigInteger('base_discount_val')->nullable();       // Descuento en moneda base
            $table->bigInteger('base_sub_total')->nullable();
            $table->bigInteger('base_total')->nullable();
            $table->bigInteger('base_tax')->nullable();

            // --- Sales tax (impuesto por dirección, estilo US) ---
            $table->string('sales_tax_type')->nullable();
            $table->string('sales_tax_address_type')->nullable();

            // --- Secuencias de numeración ---
            $table->integer('sequence_number')->nullable();            // Secuencia global por empresa
            $table->integer('customer_sequence_number')->nullable();   // Secuencia por cliente

            // --- Relaciones (foreign keys) ---
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('creator_id')->nullable();

            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $table->foreign('creator_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('currency_id')->references('id')->on('currencies')->nullOnDelete();
            $table->foreign('converted_invoice_id')->references('id')->on('invoices')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proforma_invoices');
    }
};
