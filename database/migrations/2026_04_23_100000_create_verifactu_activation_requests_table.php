<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Onfactu: guarda cada solicitud de activación de VeriFactu que haga
        // un usuario normal desde Ajustes → Personalización → Facturas.
        // Esa misma acción envía un email a soporte@onfactu.com con los datos
        // necesarios para que Asistencia se ponga en contacto con el cliente.
        Schema::create('verifactu_activation_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('company_id');
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('company_name')->nullable();
            $table->string('status')->default('pending'); // pending | processed | rejected
            $table->text('notes')->nullable(); // notas internas de soporte
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifactu_activation_requests');
    }
};
