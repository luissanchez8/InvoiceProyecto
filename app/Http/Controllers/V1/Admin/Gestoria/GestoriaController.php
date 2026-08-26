<?php

namespace App\Http\Controllers\V1\Admin\Gestoria;

use App\Http\Controllers\Controller;
use App\Services\GestoriaService;
use Illuminate\Http\Request;

/**
 * Onfactu — Gestoría (lado cliente).
 *
 * GET    /api/v1/gestoria            -> estado actual (toggle + vinculación)
 * POST   /api/v1/gestoria/toggle     -> activar / desactivar la sección
 * POST   /api/v1/gestoria/vincular   -> enviar el código de la gestoría
 * POST   /api/v1/gestoria/desvincular
 */
class GestoriaController extends Controller
{
    public function show(Request $request)
    {
        return response()->json(GestoriaService::estado());
    }

    /**
     * Activa o desactiva la sección de gestoría.
     * No se puede desactivar mientras haya una gestoría vinculada.
     */
    public function toggle(Request $request)
    {
        $v = $request->validate(['activa' => 'required|boolean']);
        $activa = (bool) $v['activa'];

        if (! $activa) {
            $vinc = GestoriaService::vinculacion();
            if ($vinc) {
                $msg = $vinc->estado === 'aceptada'
                    ? 'Antes de desactivar la gestoría tienes que desvincularla.'
                    : 'Tienes una solicitud pendiente. Cancélala antes de desactivar la gestoría.';

                return $this->error($msg);
            }
        }

        GestoriaService::setActiva($activa);

        return response()->json([
            'ok'      => true,
            'message' => $activa
                ? 'Gestoría activada. Introduce el código que te haya dado tu gestoría.'
                : 'Gestoría desactivada.',
            'data'    => GestoriaService::estado(),
        ]);
    }

    /**
     * Solicita la vinculación con el código de la gestoría.
     */
    public function vincular(Request $request)
    {
        $v = $request->validate([
            'codigo' => 'required|string|max:20',
        ]);

        $res = GestoriaService::solicitar(
            $v['codigo'],
            (string) $request->ip(),
            (string) ($request->user()?->email ?? 'desconocido')
        );

        if (! $res['ok']) {
            return $this->error($res['message']);
        }

        return response()->json([
            'ok'      => true,
            'message' => $res['message'],
            'data'    => GestoriaService::estado(),
        ]);
    }

    /**
     * Corta el vínculo con la gestoría.
     */
    public function desvincular(Request $request)
    {
        $v = $request->validate([
            'motivo' => 'nullable|string|max:500',
        ]);

        $res = GestoriaService::desvincular(
            (string) $request->ip(),
            (string) ($request->user()?->email ?? 'desconocido'),
            $v['motivo'] ?? null
        );

        if (! $res['ok']) {
            return $this->error($res['message']);
        }

        return response()->json([
            'ok'      => true,
            'message' => $res['message'],
            'data'    => GestoriaService::estado(),
        ]);
    }

    private function error(string $mensaje)
    {
        return response()->json([
            'error'   => 'gestoria_error',
            'message' => $mensaje,
            'errors'  => ['gestoria' => [$mensaje]],
        ], 422);
    }
}
