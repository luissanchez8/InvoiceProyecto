<?php

use App\Models\RecurringInvoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Onfactu v.1.18.0 — Recalcula la "próxima factura" de las recurrentes
 * activas, que se quedaba en la primera fecha (ver
 * RecurringInvoice::getNextInvoiceDate). Solo cambia esa fecha: no genera
 * facturas ni toca nada más.
 */
return new class extends Migration
{
    public function up(): void
    {
        RecurringInvoice::where('status', 'ACTIVE')->get()->each(function (RecurringInvoice $r) {
            try {
                $r->updateNextInvoiceDate();
            } catch (\Throwable $e) {
                Log::warning('No se pudo recalcular la próxima factura', ['id' => $r->id, 'error' => $e->getMessage()]);
            }
        });
    }

    public function down(): void
    {
    }
};
