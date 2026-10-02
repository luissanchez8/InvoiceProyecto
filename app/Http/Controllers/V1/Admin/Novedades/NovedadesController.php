<?php

namespace App\Http\Controllers\V1\Admin\Novedades;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Onfactu v.1.18.0 — Ventana de novedades.
 *
 * GET  /api/v1/novedades        las novedades (config/novedades.php) y si el
 *                               usuario tiene alguna sin ver
 * POST /api/v1/novedades/vistas apunta que ya las ha visto
 *
 * Lo visto se guarda por usuario (user_settings, clave novedades_vistas = la
 * última versión vista). Un usuario dado de alta después de la última novedad
 * no la ve: no es nueva para él. En la demo pública no salen sola, porque el
 * usuario es compartido y no puede guardar ajustes.
 */
class NovedadesController extends Controller
{
    public const CLAVE = 'novedades_vistas';

    public function index(Request $request)
    {
        $lista = config('novedades', []);
        $ultima = $lista[0]['version'] ?? null;
        $usuario = $request->user();
        $vista = $usuario->getSettings([self::CLAVE])[self::CLAVE] ?? null;

        if ($vista === null && $ultima && $usuario->created_at
            && Carbon::parse($usuario->created_at)->gt(Carbon::parse($lista[0]['fecha'])->endOfDay())) {
            $vista = $ultima;
        }

        $sinVer = [];
        foreach ($lista as $n) {
            if ($n['version'] === $vista) {
                break;
            }
            $sinVer[] = $n['version'];
        }

        return response()->json([
            'novedades' => $lista,
            'sin_ver' => config('app.env') === 'demo' ? [] : $sinVer,
            'ultima' => $ultima,
        ]);
    }

    public function vistas(Request $request)
    {
        $ultima = config('novedades.0.version');
        if ($ultima && config('app.env') !== 'demo') {
            $request->user()->setSettings([self::CLAVE => $ultima]);
        }

        return response()->json(['success' => true]);
    }
}
