<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Onfactu: inicializa las claves de trial/plan en app_config de cada instancia.
 *
 * Estas claves las actualiza el Stripe server vía el endpoint
 * POST /api/v1/stripe/update-plan-status cuando cambia el estado de la
 * suscripción. Al montar una instancia nueva se crean con valores neutros
 * para que la consulta nunca devuelva NULL inesperado.
 */
return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            'STRIPE_PLAN_STATUS'   => 'active',  // active|trialing|paused|past_due|canceled
            'STRIPE_TRIAL_ENDS_AT' => '',        // ISO-8601 o vacío si no hay trial activo
            'STRIPE_TRIAL_PLAN'    => '',        // essential|advanced|pro
            'STRIPE_TRIAL_INTERVAL'=> '',        // month|year
        ];

        foreach ($defaults as $key => $value) {
            $existe = DB::table('app_config')->where('key', $key)->exists();
            if (!$existe) {
                DB::table('app_config')->insert([
                    'key'        => $key,
                    'value'      => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('app_config')->whereIn('key', [
            'STRIPE_PLAN_STATUS',
            'STRIPE_TRIAL_ENDS_AT',
            'STRIPE_TRIAL_PLAN',
            'STRIPE_TRIAL_INTERVAL',
        ])->delete();
    }
};
