<?php

namespace App\Http\Controllers\V1\Stripe;

use App\Http\Controllers\Controller;
use App\Models\AppConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Onfactu: recibe notificaciones del servidor Stripe cuando cambia el estado
 * de la suscripción del cliente que está ejecutando esta instancia Pro.
 */
class UpdatePlanStatusController extends Controller
{
    public function __invoke(Request $request)
    {
        // Token leído con config() (NO env()): sobrevive a config:cache.
        $expectedToken = (string) config('onfactu.pro_api_token', '');
        $providedAuth  = (string) $request->header('Authorization', '');

        if (!$expectedToken || !hash_equals('Bearer ' . $expectedToken, $providedAuth)) {
            Log::warning('UpdatePlanStatus: token inválido', [
                'ip'       => $request->ip(),
                'provided' => substr($providedAuth, 0, 20) . '...',
            ]);
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'email'          => 'required|email',
            'plan_status'    => 'required|string|in:active,trialing,paused,past_due,canceled,incomplete,incomplete_expired,unpaid',
            'trial_ends_at'  => 'nullable|string',
            'trial_plan'     => 'nullable|string',
            'trial_interval' => 'nullable|string',
        ]);

        // El estado SIEMPRE se actualiza.
        $keys = ['STRIPE_PLAN_STATUS' => $validated['plan_status']];

        // Las claves de trial SOLO si llegan con valor: si el emisor manda
        // trial_ends_at vacío NO se machaca la fecha que ya tiene la instancia
        // (esa fecha la usa CheckPlanStatus para los 7 días de gracia).
        if (!empty($validated['trial_ends_at']))  $keys['STRIPE_TRIAL_ENDS_AT']  = $validated['trial_ends_at'];
        if (!empty($validated['trial_plan']))     $keys['STRIPE_TRIAL_PLAN']     = $validated['trial_plan'];
        if (!empty($validated['trial_interval'])) $keys['STRIPE_TRIAL_INTERVAL'] = $validated['trial_interval'];

        foreach ($keys as $key => $value) {
            AppConfig::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        Log::info('UpdatePlanStatus: estado actualizado', [
            'email'       => $validated['email'],
            'plan_status' => $validated['plan_status'],
            'applied'     => array_keys($keys),
        ]);

        return response()->json(['ok' => true, 'applied' => $keys]);
    }
}
