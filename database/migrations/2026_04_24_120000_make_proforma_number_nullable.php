<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onfactu: borradores de proforma sin número.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->string('proforma_invoice_number')->nullable()->change();
            $table->integer('sequence_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        // No revertir para no romper borradores existentes.
    }
};
