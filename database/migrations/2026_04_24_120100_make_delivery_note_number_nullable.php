<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onfactu: borradores de albarán sin número.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->string('delivery_note_number')->nullable()->change();
            $table->integer('sequence_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        // No revertir.
    }
};
