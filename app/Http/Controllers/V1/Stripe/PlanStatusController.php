<?php

namespace App\Http\Controllers\V1\Stripe;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Onfactu: devuelve al frontend el estado actual del plan de esta instancia.
 * Usado por el sidebar para mostrar el banner "Te quedan X días de prueba"
 * y por la pantalla de bloqueo para pintar el mensaje correcto.
 */
class PlanStatusController extends Controller
{
    public function __invoke(Request $request)
    {
        $status        = (string) app_cfg('STRIPE_PLAN_STATUS', 'active');
        $trialEndsAt   = (string) app_cfg('STRIPE_TRIAL_ENDS_AT', '');
        $trialPlan     = (string) app_cfg('STRIPE_TRIAL_PLAN', '');
        $trialInterval = (string) app_cfg('STRIPE_TRIAL_INTERVAL', '');

        $daysLeft       = null;
        $graceEndsAt    = null;
        $graceDaysLeft  = null;

        if ($trialEndsAt) {
            try {
                $end = Carbon::parse($trialEndsAt);

                if (now()->lt($end)) {
                    // Trial en curso: cuantos días quedan (redondeo hacia arriba
                    // para que el último día parcial cuente como 1 día completo).
                    $daysLeft = (int) ceil(now()->diffInHours($end, false) / 24);
                    if ($daysLeft < 1) $daysLeft = 1;
                }

                // Período de gracia tras fin de trial (7 días hard-coded).
                $graceEnd = $end->copy()->addDays(7);
                $graceEndsAt = $graceEnd->toIso8601String();

                if (now()->gt($end) && now()->lt($graceEnd)) {
                    $graceDaysLeft = (int) ceil(now()->diffInHours($graceEnd, false) / 24);
                    if ($graceDaysLeft < 1) $graceDaysLeft = 1;
                }
            } catch (\Throwable $e) {
                // Si la fecha viene mal, ignorar sin romper.
            }
        }

        // Obtener email del admin principal (primer super admin) para construir
        // la URL del Stripe Portal que permitirá al usuario añadir tarjeta.
        $adminEmail = null;
        try {
            $admin = DB::table('users')
                ->where('role', 'super admin')
                ->orderBy('id')
                ->first();
            $adminEmail = $admin ? $admin->email : null;
        } catch (\Throwable $e) {
            // no crítico
        }

        $stripeServerUrl = rtrim((string) env('STRIPE_SERVER_URL', 'https://pagos.onfactu.com'), '/');
        $portalUrl = $adminEmail
            ? $stripeServerUrl . '/api/stripe/portal.php?email=' . urlencode($adminEmail)
            : null;

        return response()->json([
            'plan_status'     => $status,
            'trial_ends_at'   => $trialEndsAt ?: null,
            'trial_plan'      => $trialPlan ?: null,
            'trial_interval'  => $trialInterval ?: null,
            'days_left'       => $daysLeft,
            'grace_ends_at'   => $graceEndsAt,
            'grace_days_left' => $graceDaysLeft,
            'portal_url'      => $portalUrl,
        ]);
    }
}
