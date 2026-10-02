<?php

namespace App\Http\Controllers\V1\Admin\Exportar;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\Invoice;
use App\Services\Exportar\ExportarFacturas;
use App\Services\Exportar\ExportarGastos;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PDF;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Onfactu v.1.18.0 — Exportar la lista de facturas o de gastos, con los
 * filtros que tenga puestos la pantalla, a Excel (CSV con separador ";",
 * como los informes) o a PDF (con el diseño de los informes).
 *
 * GET /api/v1/exportar/{facturas|gastos}/{excel|pdf}?filtros de la lista
 */
class ExportarListadoController extends Controller
{
    private const TIPOS = [
        'facturas' => [ExportarFacturas::class, Invoice::class],
        'gastos' => [ExportarGastos::class, Expense::class],
    ];

    public function __invoke(Request $request, string $tipo, string $formato)
    {
        abort_unless(isset(self::TIPOS[$tipo]) && in_array($formato, ['excel', 'pdf'], true), 404);
        [$fuente, $modelo] = self::TIPOS[$tipo];
        $this->authorize('viewAny', $modelo);

        $filtros = $request->except(['page', 'limit']);
        $filas = $fuente::filas($filtros);
        $importes = $fuente::importes();
        $totales = [];
        foreach ($importes as $i) {
            $totales[$i] = array_sum(array_map(fn ($f) => (int) ($f[$i] ?? 0), $filas));
        }

        $empresa = Company::find($request->header('company'));
        $periodo = $this->periodo($filtros);
        $nombre = $tipo.'-'.now()->format('Y-m-d');

        if ($formato === 'excel') {
            return $this->csv($nombre.'.csv', $empresa, $fuente::titulo(), $periodo, $fuente::cabecera(), $filas, $importes, $totales);
        }

        $moneda = Currency::find(CompanySetting::getSetting('currency', $empresa->id));
        view()->share([
            'company' => $empresa,
            'periodo' => $periodo,
            'titulo' => $fuente::titulo(),
            'cabecera' => $fuente::cabecera(),
            'filas' => $filas,
            'importes' => $importes,
            'totales' => $totales,
            'pieTotal' => $totales[array_search('Total', $fuente::cabecera(), true)] ?? 0,
            'currency' => $moneda,
            'maximo' => count($filas) >= $fuente::MAXIMO,
        ]);

        return PDF::loadView('app.pdf.listado')->setPaper('a4', 'landscape')->download($nombre.'.pdf');
    }

    private function periodo(array $f): string
    {
        if (! empty($f['from_date']) && ! empty($f['to_date'])) {
            return Carbon::parse($f['from_date'])->format('d/m/Y').' - '.Carbon::parse($f['to_date'])->format('d/m/Y');
        }

        return 'Exportado el '.now()->format('d/m/Y');
    }

    private function csv(string $fichero, ?Company $empresa, string $titulo, string $periodo, array $cabecera, array $filas, array $importes, array $totales): StreamedResponse
    {
        $fmt = fn ($v) => $v === null ? '' : number_format(((int) $v) / 100, 2, ',', '');

        return new StreamedResponse(function () use ($empresa, $titulo, $periodo, $cabecera, $filas, $importes, $totales, $fmt) {
            $h = fopen('php://output', 'w');
            fwrite($h, "\xEF\xBB\xBF");   // BOM: que Excel lea bien los acentos
            fputcsv($h, [$empresa?->name], ';');
            fputcsv($h, [$titulo], ';');
            fputcsv($h, [$periodo], ';');
            fputcsv($h, [], ';');
            fputcsv($h, $cabecera, ';');
            foreach ($filas as $fila) {
                foreach ($importes as $i) {
                    $fila[$i] = $fmt($fila[$i]);
                }
                fputcsv($h, $fila, ';');
            }
            fputcsv($h, [], ';');
            $total = array_fill(0, count($cabecera), '');
            $total[0] = 'TOTAL ('.count($filas).')';
            foreach ($importes as $i) {
                $total[$i] = $fmt($totales[$i]);
            }
            fputcsv($h, $total, ';');
            fclose($h);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fichero.'"',
        ]);
    }
}
