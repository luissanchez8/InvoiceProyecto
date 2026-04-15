<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckMenuOption
{
    /**
     * Mapeo de prefijos de ruta → clave en app_config.
     * Si la clave tiene valor distinto de '1', se bloquea el acceso.
     */
    protected static array $routeMap = [
        'invoices' => 'OPCION_MENU_FACTURAS',
        'estimates' => 'OPCION_MENU_PRESUPUESTOS',
        'proforma-invoices' => 'OPCION_MENU_PROFORMAS',
        'delivery-notes' => 'OPCION_MENU_ALBARANES',
        'recurring-invoices' => 'OPCION_MENU_FRA_RECURRENTE',
        'payments' => 'OPCION_MENU_PAGOS',
        'expenses' => 'OPCION_MENU_GASTOS',
    ];

    public function handle(Request $request, Closure $next)
    {
        $path = $request->path();

        foreach (self::$routeMap as $segment => $configKey) {
            if ($this->pathContainsSegment($path, $segment)) {
                if ((int) app_cfg($configKey, 1) !== 1) {
                    if ($request->expectsJson()) {
                        return response()->json([
                            'error' => 'Esta funcionalidad no está disponible en tu plan.',
                        ], 403);
                    }
                    abort(403, 'Esta funcionalidad no está disponible en tu plan.');
                }
                break;
            }
        }

        return $next($request);
    }

    /**
     * Comprueba si la ruta contiene el segmento como parte de la URL.
     * Ej: "api/v1/xyz/customer/delivery-notes" contiene "delivery-notes"
     */
    protected function pathContainsSegment(string $path, string $segment): bool
    {
        return str_contains($path, "/{$segment}") || str_contains($path, "/{$segment}/") || str_ends_with($path, "/{$segment}");
    }
}
