<?php

namespace App\Http\Middleware;

use App\Services\Facturacion\Documentos;
use App\Services\Facturacion\EstadoFacturacion;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Onfactu v.1.17.0 — Un presupuesto, proforma o albarán facturado no se toca.
 *
 * Bloquea sobre un documento con estado de facturación FACTURADO:
 *   - editarlo (PUT/PATCH) y borrarlo (DELETE o borrado masivo con ids[]);
 *   - volver a facturarlo (convert, convert-to-invoice, anticipo).
 * Deja pasar lo que no cambia el documento ni lo factura otra vez: enviarlo,
 * cambiar su estado comercial, duplicarlo, verlo, y convertir un presupuesto
 * facturado en albarán (se puede entregar después de facturar).
 *
 * Para cambiarlo hay que borrar antes su factura, si aún es un borrador.
 * Mismo esquema que CheckMonthClosed: a nivel de grupo de rutas, para que no
 * se escape ninguna.
 */
class CheckDocumentoFacturado
{
    private const ACCIONES_BLOQUEADAS = ['convert', 'convert-to-invoice'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $segmentos = array_map('strtolower', $request->segments());   // api, v1, estimates, 12, convert-to-invoice

        // Anticipo y varios documentos juntos: el tipo y los ids van en el body
        if (in_array('facturacion', $segmentos, true)) {
            $tipo = (string) $request->input('tipo');
            $ids = array_filter(array_merge((array) $request->input('ids', []), [$request->input('id')]), 'is_numeric');

            return $this->comprobar($request, $next, $tipo, $ids);
        }

        foreach ($segmentos as $i => $s) {
            $tipo = Documentos::tipoPorSegmento($s);
            if (! $tipo) {
                continue;
            }

            $id = $segmentos[$i + 1] ?? null;
            $accion = $segmentos[$i + 2] ?? null;

            // Borrado masivo: /estimates/delete con ids[]
            if ($id === 'delete') {
                return $this->comprobar($request, $next, $tipo, (array) $request->input('ids', []));
            }

            if (! ctype_digit((string) $id)) {
                return $next($request);
            }

            $bloquea = $accion === null
                ? in_array($request->method(), ['PUT', 'PATCH', 'DELETE'], true)
                : in_array($accion, self::ACCIONES_BLOQUEADAS, true);

            return $bloquea ? $this->comprobar($request, $next, $tipo, [(int) $id]) : $next($request);
        }

        return $next($request);
    }

    private function comprobar(Request $request, Closure $next, string $tipo, array $ids): Response
    {
        if (! isset(Documentos::TIPOS[$tipo]) || empty($ids)) {
            return $next($request);
        }

        $cfg = Documentos::cfg($tipo);
        $facturado = DB::table($cfg['tabla'])
            ->whereIn('id', array_map('intval', $ids))
            ->where('company_id', (int) $request->header('company'))
            ->where('billing_status', EstadoFacturacion::FACTURADO)
            ->value($cfg['numero']);

        if ($facturado === null) {
            return $next($request);
        }

        $mensaje = ucfirst($cfg['el']).' '.$facturado.' ya está facturad'.$cfg['o']
            .'. Para cambiarl'.$cfg['o'].', borra antes su factura en borrador.';

        return response()->json([
            'error' => 'document_invoiced',
            'message' => $mensaje,
            'errors' => ['facturacion' => [$mensaje]],
        ], 422);
    }
}
