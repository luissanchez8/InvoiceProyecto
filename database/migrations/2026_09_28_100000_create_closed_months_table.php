<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onfactu — Cierre de mes.
 *
 * Registra los meses que el cliente ha dado por cerrados. Un mes cerrado es
 * IRREVERSIBLE: no se puede reabrir ni crear/editar/borrar ningun documento
 * cuya fecha caiga dentro de el.
 *
 * Esta tabla vive en la INSTANCIA (no en la BD central) porque es la fuente de
 * verdad del bloqueo: el middleware la consulta en cada escritura y no puede
 * depender de una llamada de red a la central. El envio a la central (para el
 * portal de gestoria) se registra aqui mismo en las columnas sent_*.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('closed_months', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('company_id');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');          // 1-12

            // Quien y cuando lo cerro
            $table->unsignedInteger('closed_by')->nullable();
            $table->timestamp('closed_at');

            // Resumen congelado en el momento del cierre (n docs, netos, IVA...).
            // Se guarda para poder mostrarlo sin recalcular y para auditoria.
            $table->json('totals')->nullable();

            // Estado del envio a la BD central / portal de gestoria.
            //   pending  -> aun no enviado
            //   sent     -> la central confirmo recepcion
            //   failed   -> fallo; hay que reintentar (ver sent_error)
            //   skipped  -> no habia gestoria vinculada, no se envia
            $table->string('sent_status', 20)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->text('sent_error')->nullable();
            $table->unsignedTinyInteger('sent_attempts')->default(0);

            $table->timestamps();

            $table->unique(['company_id', 'year', 'month']);
            $table->index(['company_id', 'sent_status']);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('closed_months');
    }
};
