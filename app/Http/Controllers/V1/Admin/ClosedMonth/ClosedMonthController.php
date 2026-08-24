<?php

namespace App\Http\Controllers\V1\Admin\ClosedMonth;

use App\Http\Controllers\Controller;
use App\Models\ClosedMonth;
use App\Models\DeliveryNote;
use App\Models\Estimate;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\ProformaInvoice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Onfactu — Cierre de mes.
 *
 * GET    /api/v1/closed-months            -> meses cerrados + estado del ano
 * GET    /api/v1/closed-months/preview    -> resumen y borradores de un mes (no cierra)
 * POST   /api/v1/closed-months            -> cierra el mes (IRREVERSIBLE)
 *
 * Al cerrar, el mes queda congelado: el middleware CheckMonthClosed impide
 * cualquier escritura sobre documentos con fecha en ese periodo.
 *
 * Solo los documentos en estado COMPLETED se consideran entregables a la
 * gestoria. Los borradores quedan fuera y por eso se avisa antes de cerrar.
 */
class ClosedMonthController extends Controller
{
    /** Documentos que cuentan, con su columna de fecha. */
    private const DOCS = [
        'invoices'          => [Invoice::class,          'invoice_date',           'facturas'],
        'estimates'         => [Estimate::class,         'estimate_date',          'presupuestos'],
        'proforma_invoices' => [ProformaInvoice::class,  'proforma_invoice_date',  'proformas'],
        'delivery_notes'    => [DeliveryNote::class,     'delivery_note_date',     'albaranes'],
    ];

    /**
     * Estado de todos los meses de un ano.
     */
    public function index(Request $request)
    {
        $companyId = (int) $request->header('company');
        $year = (int) ($request->query('year') ?: now()->year);

        $cerrados = ClosedMonth::where('company_id', $companyId)
            ->where('year', $year)
            ->get()
            ->keyBy('month');

        $hoy = now();
        $meses = [];

        for ($m = 1; $m <= 12; $m++) {
            $cerrado = $cerrados->get($m);
            $futuro  = $year > $hoy->year || ($year === $hoy->year && $m > $hoy->month);
            $enCurso = $year === $hoy->year && $m === $hoy->month;

            $meses[] = [
                'year'      => $year,
                'month'     => $m,
                'estado'    => $cerrado ? 'cerrado' : ($futuro ? 'futuro' : ($enCurso ? 'en_curso' : 'abierto')),
                'closed_at' => $cerrado?->closed_at?->toIso8601String(),
                'totals'    => $cerrado?->totals,
                'puede_cerrarse' => ! $cerrado && ! $futuro && ! $enCurso,
            ];
        }

        return response()->json([
            'year'  => $year,
            'meses' => $meses,
            // Aviso: mes anterior sin cerrar y ya pasado el dia 15
            'aviso_pendiente' => $this->avisoPendiente($companyId),
        ]);
    }

    /**
     * Resumen de un mes SIN cerrarlo. Alimenta el modal de confirmacion.
     */
    public function preview(Request $request)
    {
        $companyId = (int) $request->header('company');
        $v = $request->validate([
            'year'  => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        return response()->json(
            $this->resumen($companyId, (int) $v['year'], (int) $v['month'])
        );
    }

    /**
     * Cierra el mes. IRREVERSIBLE.
     */
    public function store(Request $request)
    {
        $companyId = (int) $request->header('company');
        $v = $request->validate([
            'year'  => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);
        $year  = (int) $v['year'];
        $month = (int) $v['month'];

        // Ya cerrado
        if (ClosedMonth::where('company_id', $companyId)->where('year', $year)->where('month', $month)->exists()) {
            return $this->error('Ese mes ya está cerrado.');
        }

        // No se puede cerrar un mes que aun no ha terminado
        $fin = Carbon::create($year, $month, 1)->endOfMonth();
        if ($fin->isFuture()) {
            return $this->error('No puedes cerrar un mes que todavía no ha terminado.');
        }

        // No dejar huecos: exige cerrar antes los meses anteriores del ano
        for ($m = 1; $m < $month; $m++) {
            $existeActividad = $this->hayDocumentos($companyId, $year, $m);
            $estaCerrado = ClosedMonth::where('company_id', $companyId)
                ->where('year', $year)->where('month', $m)->exists();

            if (! $estaCerrado && $existeActividad) {
                return $this->error(
                    'Antes de cerrar este mes tienes que cerrar '
                    .$this->nombreMes($m).' de '.$year.'.'
                );
            }
        }

        $resumen = $this->resumen($companyId, $year, $month);

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

        return response()->json([
            'ok'      => true,
            'message' => $this->nombreMes($month).' de '.$year.' cerrado correctamente.',
            'data'    => $cierre,
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────

    /**
     * Calcula el resumen de un mes: totales de lo que se entrega y
     * borradores que quedarian fuera.
     */
    private function resumen(int $companyId, int $year, int $month): array
    {
        $ini = Carbon::create($year, $month, 1)->startOfDay();
        $fin = $ini->copy()->endOfMonth()->endOfDay();

        // ── Facturas COMPLETED (lo que se entrega) ──
        $facturas = Invoice::where('company_id', $companyId)
            ->whereBetween('invoice_date', [$ini, $fin])
            ->where('status', Invoice::STATUS_COMPLETED)
            ->get(['sub_total', 'tax', 'total', 'rectifies_invoice_id']);

        $rectificativas = $facturas->whereNotNull('rectifies_invoice_id');

        // ── Gastos (no tienen estado: entran todos) ──
        $gastos = Expense::where('company_id', $companyId)
            ->whereBetween('expense_date', [$ini, $fin])
            ->get(['amount']);

        // ── Borradores que NO se enviaran ──
        $borradores = [];
        foreach (self::DOCS as $tabla => [$modelo, $columna, $etiqueta]) {
            $n = $modelo::where('company_id', $companyId)
                ->whereBetween($columna, [$ini, $fin])
                ->where('status', '!=', Invoice::STATUS_COMPLETED)
                ->count();

            if ($n > 0) {
                $borradores[] = ['tipo' => $etiqueta, 'total' => $n];
            }
        }

        return [
            'year'   => $year,
            'month'  => $month,
            'nombre' => $this->nombreMes($month).' de '.$year,
            'totales' => [
                'facturas'        => $facturas->count(),
                'neto'            => (int) $facturas->sum('sub_total'),
                'iva'             => (int) $facturas->sum('tax'),
                'bruto'           => (int) $facturas->sum('total'),
                'rectificativas'  => $rectificativas->count(),
                'importe_rectificativas' => (int) $rectificativas->sum('total'),
                'gastos'          => $gastos->count(),
                'importe_gastos'  => (int) $gastos->sum('amount'),
            ],
            'borradores' => $borradores,
            'tiene_borradores' => count($borradores) > 0,
        ];
    }

    private function hayDocumentos(int $companyId, int $year, int $month): bool
    {
        $ini = Carbon::create($year, $month, 1)->startOfDay();
        $fin = $ini->copy()->endOfMonth()->endOfDay();

        foreach (self::DOCS as [$modelo, $columna]) {
            if ($modelo::where('company_id', $companyId)->whereBetween($columna, [$ini, $fin])->exists()) {
                return true;
            }
        }

        return Expense::where('company_id', $companyId)
            ->whereBetween('expense_date', [$ini, $fin])->exists();
    }

    /**
     * Aviso de mes pendiente. A partir del dia 15, si el mes anterior sigue
     * abierto, se avisa (las gestorias suelen cerrar sobre el dia 19-20).
     */
    private function avisoPendiente(int $companyId): ?array
    {
        $hoy = now();
        if ($hoy->day < 15) {
            return null;
        }

        $anterior = $hoy->copy()->subMonthNoOverflow();
        $cerrado = ClosedMonth::where('company_id', $companyId)
            ->where('year', $anterior->year)
            ->where('month', $anterior->month)
            ->exists();

        if ($cerrado) {
            return null;
        }

        return [
            'year'    => $anterior->year,
            'month'   => $anterior->month,
            'nombre'  => $this->nombreMes($anterior->month).' de '.$anterior->year,
            'mensaje' => 'Todavía no has cerrado '.$this->nombreMes($anterior->month)
                        .'. Ciérralo para que tu gestoría pueda presentarlo a tiempo.',
        ];
    }

    private function nombreMes(int $m): string
    {
        return ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'][$m];
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
