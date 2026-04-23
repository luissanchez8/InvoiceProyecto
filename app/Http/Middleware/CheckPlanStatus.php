<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Onfactu: middleware que bloquea/limita el acceso según el estado de la
 * suscripción de la instancia Pro. Los datos de estado vienen de app_config.
 *
 * Reglas:
 *   - active / trialing:      acceso completo.
 *   - paused / past_due:
 *       · Durante los primeros 7 días tras fin de trial: acceso de lectura
 *         (GET), escrituras bloqueadas con HTTP 402.
 *       · Tras 7 días de gracia: acceso totalmente bloqueado (HTTP 402).
 *   - canceled:               acceso totalmente bloqueado (HTTP 402).
 *
 * El frontend, al recibir 402, redirige al usuario a la pantalla de bloqueo
 * con botón para añadir tarjeta en Stripe Portal.
 */
class CheckPlanStatus
{
    /** Días de gracia tras fin de trial antes de bloquear del todo. */
    const GRACE_PERIOD_DAYS = 7;

    public function handle(Request $request, Closure $next): Response
    {
        $status = (string) app_cfg('STRIPE_PLAN_STATUS', 'active');

        // Estados que permiten todo sin mas.
        if (in_array($status, ['active', 'trialing'], true)) {
            return $next($request);
        }

        // Excepción crítica: el endpoint que recibe notificaciones de Stripe
        // debe funcionar SIEMPRE (para poder desbloquear cuando el cliente paga).
        if ($this->isExemptRoute($request)) {
            return $next($request);
        }

        $trialEndsAtRaw = (string) app_cfg('STRIPE_TRIAL_ENDS_AT', '');
        $trialEndsAt = $trialEndsAtRaw ? Carbon::parse($trialEndsAtRaw) : null;

        if (in_array($status, ['paused', 'past_due'], true)) {
            // ¿Todavía dentro del período de gracia?
            $graceEndsAt = $trialEndsAt
                ? $trialEndsAt->copy()->addDays(self::GRACE_PERIOD_DAYS)
                : null;

            if ($graceEndsAt && now()->lt($graceEndsAt)) {
                // Gracia activa: permitir lecturas, bloquear escrituras.
                $method = strtoupper($request->method());
                if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
                    return $next($request);
                }

                return $this->blockedResponse(
                    'trial_grace_write_blocked',
                    'Tu período de prueba ha terminado. Añade un método de pago para poder seguir creando documentos.',
                    $trialEndsAt->toIso8601String(),
                    $graceEndsAt->toIso8601String()
                );
            }

            // Gracia expirada: bloqueo total.
            return $this->blockedResponse(
                'trial_grace_expired',
                'Tu período de prueba y el de gracia han terminado. Añade un método de pago para reactivar tu cuenta.',
                $trialEndsAt ? $trialEndsAt->toIso8601String() : null,
                $graceEndsAt ? $graceEndsAt->toIso8601String() : null
            );
        }

        if ($status === 'canceled') {
            return $this->blockedResponse(
                'subscription_canceled',
                'Tu suscripción ha sido cancelada. Contacta con soporte para recuperar el acceso.',
                null,
                null
            );
        }

        // Cualquier otro estado raro (incomplete, etc.) → bloquear por seguridad.
        return $this->blockedResponse(
            'subscription_inactive',
            'Tu suscripción no está activa. Contacta con soporte.',
            null,
            null
        );
    }

    /**
     * Rutas que no deben ser bloqueadas por el middleware porque son necesarias
     * para que el usuario pueda volver a activar su cuenta.
     */
    private function isExemptRoute(Request $request): bool
    {
        $path = trim($request->path(), '/');
        return str_starts_with($path, 'api/v1/stripe/');
    }

    /**
     * Respuesta JSON estándar cuando se bloquea por suscripción.
     * Usa HTTP 402 Payment Required que el frontend reconoce para mostrar
     * la pantalla de bloqueo con botón a Stripe Portal.
     */
    private function blockedResponse(string $reason, string $message, ?string $trialEndsAt, ?string $graceEndsAt): Response
    {
        return response()->json([
            'ok'             => false,
            'error'          => $reason,
            'message'        => $message,
            'trial_ends_at'  => $trialEndsAt,
            'grace_ends_at'  => $graceEndsAt,
        ], 402);
    }
}
