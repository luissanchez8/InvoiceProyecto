<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Capado de la demo pública (demo.onfactu.com). Solo actúa con APP_ENV=demo;
 * en el resto de instancias deja pasar todo sin mirar nada.
 *
 * Bloquea en el servidor, no solo ocultando menús: aunque una opción no esté
 * en pantalla, su dirección de la API sigue existiendo.
 *
 * Tres niveles:
 *  - Bloqueo total, también la lectura: lo que expone credenciales (correo,
 *    discos, proveedores de tipo de cambio, copias) o datos internos (usuarios).
 *  - Bloqueo de escritura: lo que cambiaría la demo para todos los visitantes,
 *    que comparten el mismo usuario, o saldría fuera (VeriFactu, gestoría).
 *  - Subida de archivos: ninguna, para que nadie use la demo como alojamiento.
 */
class DemoMode
{
    private const MENSAJE = 'Esta opción no está disponible en la demo.';

    private const BLOQUEO_TOTAL = [
        '#^api/v1/users(/|$)#',
        '#^api/v1/roles(/|$)#',
        '#^api/v1/abilities#',
        '#^api/v1/(backups|download-backup)#',
        '#^api/v1/disks#',
        '#^api/v1/mail/#',
        '#^api/v1/update/#',
        '#^api/v1/exchange-rate-providers#',
        '#^api/v1/verifactu#',
    ];

    private const BLOQUEO_ESCRITURA = [
        '#^api/v1/me$#',                 // nombre, email y contraseña del usuario compartido
        '#^api/v1/me/#',                 // sus ajustes y su avatar
        '#^api/v1/company/upload-logo#',
        '#^api/v1/companies(/|$)#',      // crear o borrar empresas
        '#^api/v1/settings$#',           // ajustes globales de la aplicación
        '#^api/v1/app-config#',
        '#^api/v1/modules#',             // instalar o activar módulos
        '#^api/v1/gestoria/#',
        '#^api/v1/stripe/#',
        '#password/(email|reset)#',
        '#^login$#',                     // a la demo solo se entra por el botón
        '#/customer/login$#',
    ];

    /** Ajustes de empresa que afectarían a todos los visitantes a la vez. */
    private const AJUSTES_PROTEGIDOS = [
        'language', 'currency', 'time_zone', 'fiscal_year',
        'carbon_date_format', 'moment_date_format', 'carbon_time_format', 'moment_time_format',
    ];

    public function handle(Request $request, Closure $next)
    {
        if (config('app.env') !== 'demo') {
            return $next($request);
        }

        $ruta = $request->path();
        $lectura = in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);

        // Portada de la demo en lugar del acceso normal
        if ($lectura && (in_array($ruta, ['/', 'login', 'forgot-password'], true) || str_starts_with($ruta, 'reset-password'))) {
            return response()->view('demo.bienvenida');
        }

        // Sin Stripe: plan fijo y sin enlace al portal de suscripción
        if ($lectura && $ruta === 'api/v1/app-config/my-plan') {
            return response()->json(['plan_name' => 'Demo', 'portal_url' => null]);
        }
        if ($lectura && $ruta === 'api/v1/stripe/plan-status') {
            return response()->json(['plan_status' => 'active', 'trial_ends_at' => null, 'trial_plan' => null,
                'trial_interval' => null, 'days_left' => null, 'grace_ends_at' => null,
                'grace_days_left' => null, 'portal_url' => null]);
        }

        foreach (self::BLOQUEO_TOTAL as $patron) {
            if (preg_match($patron, $ruta)) {
                return $this->denegar();
            }
        }

        if ($lectura) {
            return $next($request);
        }

        if ($request->allFiles()) {
            return $this->denegar('En la demo no se pueden subir archivos.');
        }

        foreach (self::BLOQUEO_ESCRITURA as $patron) {
            if (preg_match($patron, $ruta)) {
                return $this->denegar();
            }
        }

        if ($ruta === 'api/v1/company/settings') {
            $ajustes = (array) $request->input('settings', []);
            if (array_intersect(array_keys($ajustes), self::AJUSTES_PROTEGIDOS)) {
                return $this->denegar('En la demo no se pueden cambiar el idioma, la moneda, la zona horaria ni los formatos de fecha.');
            }
        }

        return $next($request);
    }

    /** 422 con el formato de errores que el frontend ya enseña como aviso. */
    private function denegar(string $mensaje = self::MENSAJE)
    {
        return response()->json(['message' => $mensaje, 'errors' => ['demo' => [$mensaje]]], 422);
    }
}
