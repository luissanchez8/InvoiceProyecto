<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanStatus
{
    const GRACE_PERIOD_DAYS = 7;

    public function handle(Request $request, Closure $next): Response
    {
        $status = (string) app_cfg('STRIPE_PLAN_STATUS', 'active');
        $trialEndsAtRaw = (string) app_cfg('STRIPE_TRIAL_ENDS_AT', '');
        $trialEndsAt = $trialEndsAtRaw ? Carbon::parse($trialEndsAtRaw) : null;

        // Cliente pagando: acceso completo.
        if ($status === 'active') {
            return $next($request);
        }

        // Trial: acceso completo SOLO si sigue vigente por fecha. Blindaje contra
        // el webhook que no propaga el fin de trial (estado congelado en trialing).
        if ($status === 'trialing') {
            if (!$trialEndsAt || now()->lt($trialEndsAt)) {
                return $next($request);
            }
            $status = 'past_due'; // trial vencido y congelado -> degradar
        }

        // El endpoint de Stripe debe funcionar SIEMPRE (para desbloquear al pagar).
        if ($this->isExemptRoute($request)) {
            return $next($request);
        }

        if (in_array($status, ['paused', 'past_due'], true)) {
            $graceEndsAt = $trialEndsAt ? $trialEndsAt->copy()->addDays(self::GRACE_PERIOD_DAYS) : null;

            if ($graceEndsAt && now()->lt($graceEndsAt)) {
                $method = strtoupper($request->method());
                if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
                    return $next($request);
                }
                return $this->blockedResponse('trial_grace_write_blocked',
                    'Tu período de prueba ha terminado. Añade un método de pago para poder seguir creando documentos.',
                    $trialEndsAt->toIso8601String(), $graceEndsAt->toIso8601String());
            }

            return $this->blockedResponse('trial_grace_expired',
                'Tu período de prueba y el de gracia han terminado. Añade un método de pago para reactivar tu cuenta.',
                $trialEndsAt ? $trialEndsAt->toIso8601String() : null,
                $graceEndsAt ? $graceEndsAt->toIso8601String() : null);
        }

        if ($status === 'canceled') {
            return $this->blockedResponse('subscription_canceled',
                'Tu suscripción ha sido cancelada. Contacta con soporte para recuperar el acceso.', null, null);
        }

        return $this->blockedResponse('subscription_inactive',
            'Tu suscripción no está activa. Contacta con soporte.', null, null);
    }

    private function isExemptRoute(Request $request): bool
    {
        $path = trim($request->path(), '/');
        $exemptPrefixes = ['api/v1/stripe/', 'api/v1/bootstrap', 'api/v1/auth/', 'logo-url', 'favicons/'];
        foreach ($exemptPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) return true;
        }
        return false;
    }

    private function blockedResponse(string $reason, string $message, ?string $trialEndsAt, ?string $graceEndsAt): Response
    {
        return response()->json([
            'ok' => false, 'error' => $reason, 'message' => $message,
            'trial_ends_at' => $trialEndsAt, 'grace_ends_at' => $graceEndsAt,
        ], 402);
    }
}
