<?php

namespace App\Services\Demo;

use Illuminate\Support\Facades\DB;

/**
 * Vacía los datos de negocio de la demo.
 *
 * Los ajustes, tipos de impuesto, formas de pago, unidades, usuarios y la
 * propia empresa NO se borran: son la configuración de Onfactu que viene de
 * plantilla.sql. AjustesDemo los devuelve a su estado.
 */
class LimpiezaDemo
{
    public const TABLAS = [
        'taxes', 'invoice_items', 'estimate_items', 'proforma_invoice_items', 'delivery_note_items',
        'payments', 'transactions', 'invoices', 'estimates', 'proforma_invoices', 'delivery_notes',
        'recurring_invoices', 'expenses', 'expense_categories', 'items', 'customers', 'addresses',
        'custom_field_values', 'email_logs', 'closed_months', 'notifications', 'personal_access_tokens',
        'media', 'exchange_rate_logs',
    ];

    public function vaciar(): void
    {
        // RESTART IDENTITY: la numeración interna vuelve a empezar, como una instancia nueva
        DB::statement('TRUNCATE TABLE ' . implode(', ', array_map(fn ($t) => '"' . $t . '"', self::TABLAS))
            . ' RESTART IDENTITY CASCADE');
    }
}
