<?php

namespace App\Http\Middleware;

use App\Models\ClosedMonth;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Onfactu — Bloqueo de meses cerrados.
 *
 * Impide CUALQUIER escritura que afecte a un mes ya cerrado. Cubre:
 *   - Crear un documento con fecha dentro de un mes cerrado (fecha en el body)
 *   - Editar o borrar un documento existente que este en un mes cerrado
 *   - Mover un documento HACIA un mes cerrado (fecha nueva en el body)
 *   - Borrado masivo (POST .../delete con ids[])
 *   - Acciones sueltas sobre un documento: clone, status, approve, send...
 *
 * Se aplica a nivel de grupo de rutas, no en cada FormRequest, porque las rutas
 * que pueden tocar un mes son muchas (invoices, proformas, estimates, expenses,
 * payments, delivery-notes + sus subacciones) y una a una se escaparia alguna.
 *
 * Responde 422 con un mensaje claro para que el frontend lo muestre en el
 * formulario igual que un error de validacion.
 */
class CheckMonthClosed
{
    /**
     * Segmento de URL => tabla y columna de fecha del documento.
     */
    private const RESOURCES = [
        'invoices'           => ['table' => 'invoices',           'date' => 'invoice_date',           'label' => 'la factura'],
        'estimates'          => ['table' => 'estimates',          'date' => 'estimate_date',          'label' => 'el presupuesto'],
        'expenses'           => ['table' => 'expenses',           'date' => 'expense_date',           'label' => 'el gasto'],
        'payments'           => ['table' => 'payments',           'date' => 'payment_date',           'label' => 'el pago'],
        'proforma-invoices'  => ['table' => 'proforma_invoices',  'date' => 'proforma_invoice_date',  'label' => 'la proforma'],
        'delivery-notes'     => ['table' => 'delivery_notes',     'date' => 'delivery_note_date',     'label' => 'el albaran'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Solo nos interesan las escrituras.
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $res = $this->resolveResource($request);
        if ($res === null) {
            return $next($request);
        }

        $companyId = $this->companyId($request);
        if (! $companyId) {
            return $next($request);
        }

        // Si la empresa no tiene ningun mes cerrado, no hay nada que comprobar.
        if (empty(ClosedMonth::periodsFor($companyId))) {
            return $next($request);
        }

        [$segment, $cfg] = $res;

        // ── 1. Fecha que llega en el body (crear o mover el documento) ──
        $bodyDate = $request->input($cfg['date']);
        if ($bodyDate && ClosedMonth::isClosed($companyId, $bodyDate)) {
            return $this->blocked($bodyDate, $cfg['label'], 'destino');
        }

        // ── 2. Documento(s) existente(s) afectados por la operacion ──
        $ids = $this->targetIds($request, $segment);
        if (! empty($ids)) {
            $fechas = DB::table($cfg['table'])
                ->whereIn('id', $ids)
                ->where('company_id', $companyId)
                ->pluck($cfg['date']);

            foreach ($fechas as $f) {
                if (ClosedMonth::isClosed($companyId, $f)) {
                    return $this->blocked($f, $cfg['label'], 'origen');
                }
            }
        }

        return $next($request);
    }

    /**
     * Identifica a que recurso apunta la ruta. Devuelve [segmento, config] o null.
     */
    private function resolveResource(Request $request): ?array
    {
        $segments = $request->segments();          // ['api','v1','invoices','12','clone']

        foreach ($segments as $s) {
            $key = strtolower($s);
            if (isset(self::RESOURCES[$key])) {
                return [$key, self::RESOURCES[$key]];
            }
        }

        return null;
    }

    /**
     * IDs de documentos existentes que la peticion va a tocar.
     *   - /invoices/12            -> [12]
     *   - /invoices/12/clone      -> [12]
     *   - /invoices/delete + ids  -> [1,2,3]
     */
    private function targetIds(Request $request, string $segment): array
    {
        $ids = [];

        // ID en la ruta: el segmento numerico inmediatamente posterior al recurso.
        $segments = $request->segments();
        foreach ($segments as $i => $s) {
            if (strtolower($s) === $segment && isset($segments[$i + 1]) && ctype_digit($segments[$i + 1])) {
                $ids[] = (int) $segments[$i + 1];
                break;
            }
        }

        // Borrado masivo: ids[] en el body.
        $bulk = $request->input('ids');
        if (is_array($bulk)) {
            foreach ($bulk as $id) {
                if (is_numeric($id)) {
                    $ids[] = (int) $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Empresa activa. InvoiceShelf la pasa en la cabecera 'company'.
     */
    private function companyId(Request $request): ?int
    {
        $header = $request->header('company');
        if ($header && is_numeric($header)) {
            return (int) $header;
        }

        $user = $request->user();
        if ($user && ! empty($user->companies) && count($user->companies)) {
            return (int) $user->companies[0]->id;
        }

        // Instancia con una sola empresa (caso normal en Onfactu).
        $only = DB::table('companies')->count() === 1
            ? DB::table('companies')->value('id')
            : null;

        return $only ? (int) $only : null;
    }

    private function blocked($date, string $label, string $motivo): Response
    {
        $mes = ClosedMonth::label($date);

        $msg = $motivo === 'destino'
            ? "No puedes usar una fecha de {$mes}: ese mes ya esta cerrado y no admite nuevos documentos."
            : "No puedes modificar {$label} porque pertenece a {$mes}, un mes ya cerrado.";

        return response()->json([
            'error'   => 'month_closed',
            'message' => $msg,
            'period'  => ClosedMonth::toPeriod($date),
            'errors'  => ['month_closed' => [$msg]],   // formato de validacion Laravel
        ], 422);
    }
}
