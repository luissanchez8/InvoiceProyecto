<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Onfactu v.1.13.0 — Las facturas solo tienen dos estados: Borrador y Aprobada.
 *
 * Antes había Borrador, Enviada, Vista y Completada (cobrada). Enviar, verla el
 * cliente o cobrarla cambiaba el estado, y varias acciones sacaban una factura
 * del borrador sin asignarle número. Ahora:
 *  - Aprobar es el único paso para salir del borrador, y asigna el número.
 *  - Enviada y vista pasan a ser información, con su fecha.
 *  - El cobro va aparte, en paid_status.
 *
 * Conversión de lo existente:
 *  - Enviadas, vistas, completadas y aprobadas CON número → Aprobada.
 *  - Las mismas SIN número → Borrador: nunca se aprobaron de verdad.
 *  - Los borradores, tengan o no número, se quedan en borrador. Hay borradores
 *    con número de antes de este cambio ("Guardar" numeraba sin emitir) y no
 *    hay forma de saber si se enviaron: aprobarlos a ciegas bloquearía
 *    facturas a medio hacer. Conservan su número y lo mantienen al aprobarlas.
 *
 * Recurrentes: auto_approve decide si la factura generada se aprueba sola o se
 * crea en borrador. Las que ya enviaban solas se aprueban solas; las demás
 * crean borradores, que es lo más parecido a lo que hacían (numerar sin emitir).
 *
 * Se puede ejecutar varias veces. No se deshace: los estados antiguos no se
 * pueden reconstruir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
            if (! Schema::hasColumn('invoices', 'sent_at')) {
                $table->timestamp('sent_at')->nullable();
            }
            if (! Schema::hasColumn('invoices', 'viewed_at')) {
                $table->timestamp('viewed_at')->nullable();
            }
        });

        Schema::table('recurring_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('recurring_invoices', 'auto_approve')) {
                $table->boolean('auto_approve')->default(false);
            }
        });

        $emitidas = ['SENT', 'VIEWED', 'COMPLETED', 'APPROVED'];

        DB::table('invoices')
            ->whereIn('status', $emitidas)
            ->whereNotNull('invoice_number')->where('invoice_number', '<>', '')
            ->update([
                'status'      => 'APPROVED',
                'approved_at' => DB::raw('COALESCE(approved_at, updated_at, created_at)'),
            ]);

        DB::table('invoices')
            ->whereIn('status', $emitidas)
            ->where(fn ($q) => $q->whereNull('invoice_number')->orWhere('invoice_number', ''))
            ->update(['status' => 'DRAFT', 'invoice_number' => null, 'sequence_number' => null]);

        // Fecha de envío: la del último email que consta. Si se marcó como
        // enviada a mano, no hay fecha: se queda solo "enviada". mailable_id es texto:
        // por eso i.id::text.
        DB::statement("
            UPDATE invoices i SET sent_at = e.ultima
            FROM (SELECT mailable_id, MAX(created_at) AS ultima
                  FROM email_logs WHERE mailable_type = 'App\\Models\\Invoice'
                  GROUP BY mailable_id) e
            WHERE e.mailable_id = i.id::text AND i.sent_at IS NULL
        ");

        DB::table('recurring_invoices')->where('send_automatically', true)->update(['auto_approve' => true]);
    }

    public function down(): void
    {
        // Irreversible a propósito: ver el comentario de la clase.
    }
};
