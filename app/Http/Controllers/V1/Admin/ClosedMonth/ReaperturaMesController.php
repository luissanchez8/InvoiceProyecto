<?php

namespace App\Http\Controllers\V1\Admin\ClosedMonth;

use App\Http\Controllers\Controller;
use App\Models\ClosedMonth;
use App\Services\ReaperturaMes;
use Illuminate\Http\Request;

/**
 * Onfactu v.1.16.0 — Abrir un mes cerrado para corregirlo y volver a cerrarlo.
 *
 * POST /api/v1/closed-months/reabrir     { year, month, motivo }   solo el propietario
 * GET  /api/v1/closed-months/reabierto   ?year&month               cambios y totales antes de cerrar
 * POST /api/v1/closed-months/recerrar    { year, month }
 *
 * Las reglas están en App\Services\ReaperturaMes.
 */
class ReaperturaMesController extends Controller
{
    public function abrir(Request $request)
    {
        $datos = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'motivo' => 'required|string|min:5|max:500',
        ], [
            'motivo.required' => 'Escribe el motivo: se lo enviaremos a tu gestoría.',
            'motivo.min' => 'Explica un poco más el motivo.',
        ]);

        if (! ReaperturaMes::puedeAbrir($request->user())) {
            return $this->error('Solo el propietario de la cuenta puede abrir un mes cerrado.', 403);
        }

        $empresa = (int) $request->header('company');
        $mes = $this->mes($empresa, $datos['year'], $datos['month']);
        if (! $mes) {
            return $this->error('Ese mes no está cerrado.');
        }
        if ($mes->estaAbierto()) {
            return $this->error('Ese mes ya está abierto.');
        }

        $otro = ClosedMonth::where('company_id', $empresa)->whereNotNull('reopened_at')
            ->where('reopen_expires_at', '>', now())->first();
        if ($otro) {
            return $this->error('Ya tienes abierto '.ClosedMonth::label(sprintf('%04d-%02d-01', $otro->year, $otro->month))
                .'. Ciérralo antes de abrir otro.');
        }

        ReaperturaMes::abrir($mes, (int) $request->user()->id, (string) $request->user()->name, trim($datos['motivo']));

        return response()->json([
            'ok' => true,
            'message' => ucfirst(ClosedMonth::label(sprintf('%04d-%02d-01', $mes->year, $mes->month)))
                .' está abierto hasta mañana a esta hora. Hemos avisado a tu gestoría.',
        ]);
    }

    public function vistaPrevia(Request $request)
    {
        [$empresa, $year, $month] = $this->periodo($request);
        $mes = $this->mes($empresa, $year, $month);
        if (! $mes || ! $mes->reopened_at) {
            return $this->error('Ese mes no está abierto.');
        }

        return response()->json(ReaperturaMes::vistaPrevia($mes));
    }

    public function cerrar(Request $request)
    {
        [$empresa, $year, $month] = $this->periodo($request);
        $mes = $this->mes($empresa, $year, $month);
        if (! $mes || ! $mes->reopened_at) {
            return $this->error('Ese mes no está abierto.');
        }

        $resumen = ReaperturaMes::cerrar($mes, (int) $request->user()->id);
        $n = count($resumen['cambios']);

        return response()->json([
            'ok' => true,
            'message' => $resumen['nombre'].' cerrado de nuevo. '
                .($n ? 'Tu gestoría ya tiene los cambios.' : 'No había cambios.'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────

    private function periodo(Request $request): array
    {
        $v = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        return [(int) $request->header('company'), (int) $v['year'], (int) $v['month']];
    }

    private function mes(int $empresa, int $year, int $month): ?ClosedMonth
    {
        return ClosedMonth::where('company_id', $empresa)->where('year', $year)->where('month', $month)->first();
    }

    private function error(string $mensaje, int $codigo = 422)
    {
        return response()->json(['message' => $mensaje, 'errors' => ['reapertura' => [$mensaje]]], $codigo);
    }
}
