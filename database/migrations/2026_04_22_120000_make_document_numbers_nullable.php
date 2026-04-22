<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Onfactu — Numeración diferida
 *
 * Hace nullable los campos de número y secuencia en las 4 tablas de
 * documentos:
 *   - invoices.invoice_number, invoices.sequence_number
 *   - estimates.estimate_number, estimates.sequence_number
 *   - proforma_invoices.proforma_invoice_number, proforma_invoices.sequence_number
 *   - delivery_notes.delivery_note_number, delivery_notes.sequence_number
 *
 * Necesario para que los borradores nazcan SIN número. El número se
 * asigna al aprobar (facturas, vía VeriFactu) o al marcar como enviado
 * (presupuestos, proformas, albaranes).
 *
 * En PostgreSQL usamos DB::statement con ALTER COLUMN ... DROP NOT NULL
 * porque el ->change() de Laravel sin doctrine/dbal puede dar problemas.
 * Para MySQL/MariaDB usamos el ALTER TABLE equivalente.
 *
 * Si la columna ya es nullable, el ALTER es idempotente (no falla).
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        $cols = [
            'invoices' => ['invoice_number', 'sequence_number'],
            'estimates' => ['estimate_number', 'sequence_number'],
            'proforma_invoices' => ['proforma_invoice_number', 'sequence_number'],
            'delivery_notes' => ['delivery_note_number', 'sequence_number'],
        ];

        foreach ($cols as $table => $columns) {
            // Saltamos tablas que no existen (por si acaso en alguna instancia
            // no se han creado proforma/delivery aún)
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                if ($driver === 'pgsql') {
                    DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN \"{$column}\" DROP NOT NULL");
                } elseif ($driver === 'mysql' || $driver === 'mariadb') {
                    // Necesitamos saber el tipo actual de la columna para el MODIFY
                    $type = $this->getMysqlColumnType($table, $column);
                    DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$type} NULL");
                } elseif ($driver === 'sqlite') {
                    // SQLite no soporta ALTER COLUMN, pero como en SQLite las
                    // columnas son flexibles por defecto, normalmente no es
                    // necesario hacer nada. Lo logueamos por si acaso.
                    // (En la práctica Onfactu no usa SQLite en producción.)
                }
            }
        }
    }

    public function down(): void
    {
        // No revertimos: pasar a NOT NULL exigiría rellenar los nulls
        // de borradores existentes con valores válidos. Si fuera necesario
        // hay que hacerlo a mano.
    }

    /**
     * Devuelve el tipo SQL de una columna en MySQL/MariaDB para usarlo
     * en un MODIFY que preserve el tipo original.
     */
    protected function getMysqlColumnType(string $table, string $column): string
    {
        $row = DB::selectOne(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?",
            [$table, $column]
        );

        return $row ? $row->COLUMN_TYPE : 'VARCHAR(255)';
    }
};
