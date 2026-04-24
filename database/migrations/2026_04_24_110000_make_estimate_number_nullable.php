<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onfactu: los borradores de presupuesto no consumen número de serie. Por eso
 * estimate_number y sequence_number pueden quedar NULL mientras el presupuesto
 * esté en estado DRAFT sin finalizar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->string('estimate_number')->nullable()->change();
            $table->integer('sequence_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        // No revertir para no romper borradores existentes.
    }
};
