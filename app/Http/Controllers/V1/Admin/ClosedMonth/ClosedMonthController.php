<?php

namespace App\Http\Controllers\V1\Admin\ClosedMonth;

use App\Http\Controllers\Controller;
use App\Models\ClosedMonth;
use App\Models\DeliveryNote;
use App\Models\Estimate;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\ProformaInvoice;
use App\Services\CierreMes;
use App\Services\GestoriaService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Onfactu — Cierre de mes.
 *
 * GET    /api/v1/closed-months            -> estado de los meses del año
 * GET    /api/v1/closed-months/preview    -> resumen de un mes (no cierra)
 * GET    /api/v1/closed-months/export     -> CSV de un mes cerrado
 * POST   /api/v1/closed-months            -> cierra el mes (IRREVERSIBLE)
 *
 * Al cerrar, el mes queda congelado: el middleware CheckMonthClosed impide
 * cualquier escritura sobre documentos con fecha en ese periodo.
 *
 * Qué se entrega a la gestoría lo decide App\Services\CierreMes.
 */
class ClosedMonthController extends Controller
{
    /** Documentos que cuentan como actividad del mes, con su columna de fecha. */
    private const DOCS = [
        [Invoice::class,         'invoice_date'],
        [Estimate::class,        'estimate_date'],
        [ProformaInvoice::class, 'proforma_invoice_date'],
        [DeliveryNote::class,    'delivery_note_date'],
    ];

    public function index(Request $request)
    {
        $companyId = (int) $request->header('company');
        $year = (int) ($request->query('year') ?: now()->year);

        // Si algún cierre no llegó a la central, se reintenta al abrir la
        // pantalla (además de la tarea programada de cada hora).
        CierreMes::reenviarPendientes($companyId);

        $cerrados = ClosedMonth::where('company_id', $companyId)->where('year', $year)->get()->keyBy('month');
        $hoy = now();
        $meses = [];

        for ($m = 1; $m <= 12; $m++) {
            $cerrado = $cerrados->get($m);
            $futuro  = $year > $hoy->year || ($year === $hoy->year && $m > $hoy->month);
            $enCurso = $year === $hoy->year && $m === $hoy->month;

            $meses[] = [
                'year'           => $year,
                'month'          => $m,
                'estado'         => $cerrado ? 'cerrado' : ($futuro ? 'futuro' : ($enCurso ? 'en_curso' : 'abierto')),
                'closed_at'      => $cerrado?->closed_at?->toIso8601String(),
                'totals'         => $cerrado?->totals,
                'entregado'      => $cerrado ? $cerrado->sent_status === 'sent' : null,
                'puede_cerrarse' => ! $cerrado && ! $futuro && ! $enCurso,
            ];
        }

        return response()->json([
            'year'            => $year,
            'meses'           => $meses,
            'aviso_pendiente' => $this->avisoPendiente($companyId),
        ]);
    }

    public function preview(Request $request)
    {
        [$companyId, $year, $month] = $this->periodoPedido($request);

        return response()->json(CierreMes::resumen($companyId, $year, $month));
    }

    /**
     * CSV de un mes cerrado: lo mismo que ha recibido la gestoría.
     */
    public function export(Request $request)
    {
        [$companyId, $year, $month] = $this->periodoPedido($request);

        if (! ClosedMonth::where('company_id', $companyId)->where('year', $year)->where('month', $month)->exists()) {
            return $this->error('Solo se pueden descargar los meses cerrados.');
        }

        return response(CierreMes::csv($companyId, $year, $month), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.CierreMes::nombreFichero($companyId, $year, $month).'"',
            'Cache-Control'       => 'no-store',
        ]);
    }

    /**
     * Cierra el mes. IRREVERSIBLE.
     */
    public function store(Request $request)
    {
        [$companyId, $year, $month] = $this->periodoPedido($request);

        // Hace falta una gestoría vinculada: el cierre existe para entregarle el periodo
        if (! GestoriaService::activa()) {
            return $this->error('Tienes que activar la gestoría en los ajustes para poder cerrar meses.');
        }

        $vinc = GestoriaService::vinculacion();
        if (! $vinc || $vinc->estado !== 'aceptada') {
            return $this->error(
                $vinc && $vinc->estado === 'pendiente'
                    ? 'Tu solicitud de vinculación todavía está pendiente de aprobación.'
                    : 'Tienes que vincular una gestoría antes de poder cerrar meses.'
            );
        }

        if (ClosedMonth::where('company_id', $companyId)->where('year', $year)->where('month', $month)->exists()) {
            return $this->error('Ese mes ya está cerrado.');
        }

        if (Carbon::create($year, $month, 1)->endOfMonth()->isFuture()) {
            return $this->error('No puedes cerrar un mes que todavía no ha terminado.');
        }

        // No dejar huecos: exige cerrar antes los meses anteriores del año
        for ($m = 1; $m < $month; $m++) {
            $estaCerrado = ClosedMonth::where('company_id', $companyId)->where('year', $year)->where('month', $m)->exists();
            if (! $estaCerrado && $this->hayDocumentos($companyId, $year, $m)) {
                return $this->error('Antes de cerrar este mes tienes que cerrar '.CierreMes::nombreMes($m).' de '.$year.'.');
            }
        }

        $resumen = CierreMes::resumen($companyId, $year, $month);

        $cierre = ClosedMonth::create([
            'company_id'  => $companyId,
            'year'        => $year,
            'month'       => $month,
            'closed_by'   => $request->user()?->id,
            'closed_at'   => now(),
            'totals'      => $resumen['totales'],
            'sent_status' => 'pending',
        ]);

        ClosedMonth::forgetCache($companyId);

        // Entrega a la central. Si falla, el mes queda cerrado igualmente (el
        // bloqueo ya es efectivo) y se reintenta solo: al abrir la pantalla
        // de gestoría y cada hora.
        $enviado = GestoriaService::registrarCierre($year, $month, $resumen['totales'], $cierre->closed_at);

        $cierre->update([
            'sent_status'   => $enviado ? 'sent' : 'failed',
            'sent_at'       => $enviado ? now() : null,
            'sent_error'    => $enviado ? null : 'No se pudo registrar en la BD central',
            'sent_attempts' => 1,
        ]);

        $mes = CierreMes::nombreMes($month, true).' de '.$year;

        return response()->json([
            'ok'        => true,
            'entregado' => $enviado,
            'message'   => $enviado
                ? $mes.' cerrado. '.$vinc->gestoria_nombre.' ya lo tiene disponible.'
                : $mes.' cerrado. No se ha podido entregar a tu gestoría ahora mismo: lo reintentaremos automáticamente.',
            'data'      => $cierre,
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────

    /** @return array{0: int, 1: int, 2: int} empresa, año y mes de la petición */
    private function periodoPedido(Request $request): array
    {
        $v = $request->validate([
            'year'  => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        return [(int) $request->header('company'), (int) $v['year'], (int) $v['month']];
    }

    private function hayDocumentos(int $companyId, int $year, int $month): bool
    {
        [$ini, $fin] = CierreMes::periodo($year, $month);

        foreach (self::DOCS as [$modelo, $columna]) {
            if ($modelo::where('company_id', $companyId)->whereBetween($columna, [$ini, $fin])->exists()) {
                return true;
            }
        }

        return Expense::where('company_id', $companyId)->whereBetween('expense_date', [$ini, $fin])->exists();
    }

    /**
     * Aviso de mes pendiente. A partir del día 15, si el mes anterior sigue
     * abierto, se avisa (las gestorías suelen presentar sobre el día 19-20).
     */
    private function avisoPendiente(int $companyId): ?array
    {
        $hoy = now();
        if ($hoy->day < 15) {
            return null;
        }

        $anterior = $hoy->copy()->subMonthNoOverflow();
        if (ClosedMonth::where('company_id', $companyId)->where('year', $anterior->year)->where('month', $anterior->month)->exists()) {
            return null;
        }

        return [
            'year'    => $anterior->year,
            'month'   => $anterior->month,
            'nombre'  => CierreMes::nombreMes($anterior->month).' de '.$anterior->year,
            'mensaje' => 'Todavía no has cerrado '.CierreMes::nombreMes($anterior->month)
                        .'. Ciérralo para que tu gestoría pueda presentarlo a tiempo.',
        ];
    }

    private function error(string $mensaje)
    {
        return response()->json([
            'error'   => 'cannot_close',
            'message' => $mensaje,
            'errors'  => ['close' => [$mensaje]],
        ], 422);
    }
}
