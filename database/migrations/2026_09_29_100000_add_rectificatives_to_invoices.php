<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Onfactu — Facturas rectificativas.
 *
 * Las rectificativas viven en la MISMA tabla `invoices` y se distinguen por
 * tener `rectifies_invoice_id` relleno (apunta a la factura que rectifican).
 *
 * Se les da su propia serie (REC por defecto) mediante los ajustes
 * `rectificative_number_format` y `rectificative_start_number`, que el
 * SerialNumberFormatter resuelve a partir del nombre del modelo
 * (App\Models\Rectificative -> "rectificative_number_format").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('rectifies_invoice_id')->nullable()->after('recurring_invoice_id');

            $table->foreign('rectifies_invoice_id')
                ->references('id')->on('invoices')
                ->onDelete('restrict');   // no se puede borrar una factura rectificada

            $table->index('rectifies_invoice_id');
        });

        // Ajustes de numeracion de la serie REC, uno por empresa.
        $companies = DB::table('companies')->pluck('id');
        $now = now();

        foreach ($companies as $companyId) {
            $defaults = [
                'rectificative_number_format' => '{{SERIES:REC}}{{DELIMITER:-}}{{SEQUENCE:6}}',
                'rectificative_start_number'  => '1',
            ];

            foreach ($defaults as $option => $value) {
                $exists = DB::table('company_settings')
                    ->where('company_id', $companyId)
                    ->where('option', $option)
                    ->exists();

                if (! $exists) {
                    DB::table('company_settings')->insert([
                        'company_id' => $companyId,
                        'option'     => $option,
                        'value'      => $value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['rectifies_invoice_id']);
            $table->dropIndex(['rectifies_invoice_id']);
            $table->dropColumn('rectifies_invoice_id');
        });

        DB::table('company_settings')
            ->whereIn('option', ['rectificative_number_format', 'rectificative_start_number'])
            ->delete();
    }
};
