<?php

namespace App\Http\Controllers\V1\Stripe;

use App\Http\Controllers\Controller;
use App\Models\AppConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Onfactu: recibe notificaciones del servidor Stripe cuando cambia el estado
 * de la suscripción del cliente que está ejecutando esta instancia Pro.
 *
 * El servidor Stripe llama a este endpoint cuando:
 *   - Se inicia un trial (status=trialing).
 *   - El trial termina sin tarjeta (status=paused).
 *   - El cliente añade tarjeta (status=active).
 *   - El cliente cancela (status=canceled).
 *   - Hay un pago fallido (status=past_due).
 *
 * Los datos se guardan en app_config con las siguientes claves:
 *   - STRIPE_PLAN_STATUS:    trialing|active|paused|past_due|canceled
 *   - STRIPE_TRIAL_ENDS_AT:  timestamp ISO-8601 del fin del trial
 *   - STRIPE_TRIAL_PLAN:     essential|advanced|pro
 *   - STRIPE_TRIAL_INTERVAL: month|year
 */
class UpdatePlanStatusController extends Controller
{
    public function __invoke(Request $request)
    {
        // Autenticación Bearer token (token compartido con el servidor Stripe).
        $expectedToken = (string) env('ONFACTU_PRO_API_TOKEN', '');
        $providedAuth  = (string) $request->header('Authorization', '');

        if (!$expectedToken || $providedAuth !== 'Bearer ' . $expectedToken) {
            Log::warning('UpdatePlanStatus: token inválido', [
                'ip'       => $request->ip(),
                'provided' => substr($providedAuth, 0, 20) . '...',
            ]);
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'email'          => 'required|email',
            'plan_status'    => 'required|string|in:active,trialing,paused,past_due,canceled,incomplete,incomplete_expired,unpaid',
            'trial_ends_at'  => 'nullable|string',  // ISO-8601
            'trial_plan'     => 'nullable|string',
            'trial_interval' => 'nullable|string',
        ]);

        // Guardar todo en app_config (tabla global de la instancia).
        $keys = [
            'STRIPE_PLAN_STATUS'    => $validated['plan_status'],
            'STRIPE_TRIAL_ENDS_AT'  => $validated['trial_ends_at']  ?? '',
            'STRIPE_TRIAL_PLAN'     => $validated['trial_plan']     ?? '',
            'STRIPE_TRIAL_INTERVAL' => $validated['trial_interval'] ?? '',
        ];

        foreach ($keys as $key => $value) {
            AppConfig::updateOrCreate(
                ['key' => $key],
                ['value' => (string) $value]
            );
        }

        Log::info('UpdatePlanStatus: estado actualizado', [
            'email'       => $validated['email'],
            'plan_status' => $validated['plan_status'],
            'trial_end'   => $validated['trial_ends_at'] ?? null,
        ]);

        return response()->json([
            'ok'      => true,
            'applied' => $keys,
        ]);
    }
}
