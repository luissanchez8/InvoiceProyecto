<?php

namespace App\Services\Facturacion;

use App\Models\DeliveryNote;
use App\Models\DocumentoFacturado;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\ProformaInvoice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Onfactu v.1.17.0 — Estado de facturación de presupuestos, proformas y
 * albaranes, aparte de su estado comercial (enviado, aceptado, entregado...).
 *
 *   PENDIENTE  sin factura
 *   ANTICIPO   con una o más facturas de anticipo, sin la final
 *   FACTURADO  con su factura final (aunque siga en borrador). Queda
 *              bloqueado: no se edita, no se borra y no se vuelve a facturar
 *              (middleware CheckDocumentoFacturado)
 *
 * Un presupuesto también cuenta como facturado si lo está la proforma o el
 * albarán que salió de él. Si se borra la factura (solo se pueden borrar los
 * borradores), el documento vuelve a su estado anterior.
 */
class EstadoFacturacion
{
    public const PENDIENTE = 'PENDIENTE';

    public const ANTICIPO = 'ANTICIPO';

    public const FACTURADO = 'FACTURADO';

    private const ORDEN = [self::PENDIENTE => 0, self::ANTICIPO => 1, self::FACTURADO => 2];

    /** Documentos de las facturas que se están borrando, para recalcularlos después. */
    private static array $alBorrar = [];

    public static function enlaces(Model $doc): Collection
    {
        return DocumentoFacturado::with('invoice')
            ->where('documento_tipo', Documentos::tipoDe($doc))
            ->where('documento_id', $doc->id)
            ->orderBy('id')
            ->get()
            ->filter(fn ($e) => $e->invoice !== null)
            ->values();
    }

    public static function enlazar(Model $doc, Invoice $factura, string $tipo): void
    {
        DocumentoFacturado::create([
            'company_id' => $doc->company_id, 'invoice_id' => $factura->id,
            'documento_tipo' => Documentos::tipoDe($doc), 'documento_id' => $doc->id,
            'tipo' => $tipo, 'importe' => (int) $factura->total,
        ]);
        self::recalcular($doc);
    }

    /** Calcula y guarda el estado. Si el documento salió de un presupuesto, recalcula también el presupuesto. */
    public static function recalcular(Model $doc): string
    {
        $estado = self::calcular($doc);
        if ($doc->billing_status !== $estado) {
            // Sin pasar por el modelo: no es una edición del documento
            DB::table(Documentos::cfg($doc)['tabla'])->where('id', $doc->id)->update(['billing_status' => $estado]);
            $doc->billing_status = $estado;
            $doc->syncOriginalAttribute('billing_status');
        }

        if (! $doc instanceof Estimate && $doc->estimate_id && ($presupuesto = Estimate::find($doc->estimate_id))) {
            self::recalcular($presupuesto);
        }

        return $estado;
    }

    public static function calcular(Model $doc): string
    {
        $estado = self::PENDIENTE;
        foreach (self::enlaces($doc) as $e) {
            $estado = self::mayor($estado, $e->tipo === DocumentoFacturado::FINAL ? self::FACTURADO : self::ANTICIPO);
        }

        if ($doc instanceof Estimate) {
            foreach (self::derivados($doc) as $hijo) {
                $estado = self::mayor($estado, $hijo->billing_status ?: self::PENDIENTE);
            }
        }

        return $estado;
    }

    /** Proformas y albaranes que salieron de este presupuesto. */
    public static function derivados(Estimate $presupuesto): Collection
    {
        return ProformaInvoice::where('estimate_id', $presupuesto->id)->get()
            ->concat(DeliveryNote::where('estimate_id', $presupuesto->id)->get());
    }

    /**
     * Facturas de anticipo que tiene que restar la factura final de este
     * documento: las suyas y las de su presupuesto. Las anuladas con una
     * rectificativa no cuentan. Devuelve [aprobadas, borradores].
     */
    public static function anticipos(Model $doc): array
    {
        $docs = collect([$doc]);
        if (! $doc instanceof Estimate && $doc->estimate_id && ($p = Estimate::find($doc->estimate_id))) {
            $docs->push($p);
        }

        $facturas = $docs->flatMap(fn ($d) => self::enlaces($d))
            ->where('tipo', DocumentoFacturado::ANTICIPO)
            ->map(fn ($e) => $e->invoice)
            ->unique('id')
            ->reject(fn ($f) => $f->estaAnulada());   // v.1.18.0: con rectificativas de rectificativas

        return [
            $facturas->where('status', '!=', Invoice::STATUS_DRAFT)->values(),
            $facturas->where('status', Invoice::STATUS_DRAFT)->values(),
        ];
    }

    /** Lo que enseña la pantalla del documento. */
    public static function detalle(Model $doc): array
    {
        $facturas = self::enlaces($doc)->map(fn ($e) => [
            'id' => $e->invoice->id,
            'numero' => $e->invoice->invoice_number,
            'fecha' => Carbon::parse($e->invoice->invoice_date)->format('d/m/Y'),
            'tipo' => $e->tipo,
            'total' => (int) $e->invoice->total,
            'borrador' => $e->invoice->status === Invoice::STATUS_DRAFT,
        ])->values();

        $origen = null;
        if (! $doc instanceof Estimate && $doc->estimate_id && ($p = Estimate::find($doc->estimate_id))) {
            $origen = ['tipo' => 'estimate', 'id' => $p->id, 'numero' => $p->estimate_number];
        }

        $derivados = $doc instanceof Estimate
            ? self::derivados($doc)->map(fn ($d) => [
                'tipo' => Documentos::tipoDe($d), 'id' => $d->id, 'numero' => Documentos::numero($d),
                'estado' => $d->billing_status,
            ])->values()
            : collect();

        $anticipado = (int) $facturas->where('tipo', DocumentoFacturado::ANTICIPO)->sum('total');

        return [
            'estado' => $doc->billing_status ?: self::PENDIENTE,
            'bloqueado' => ($doc->billing_status === self::FACTURADO),
            'facturas' => $facturas,
            'anticipado' => $anticipado,
            'pendiente' => $doc->billing_status === self::FACTURADO ? 0 : max(0, (int) $doc->total - $anticipado),
            'puede_anticipo' => in_array(Documentos::tipoDe($doc), Documentos::CON_ANTICIPO, true)
                && $doc->billing_status !== self::FACTURADO,
            'origen' => $origen,
            'derivados' => $derivados,
        ];
    }

    // ── Al borrar una factura (Invoice::booted) ──────────────────

    public static function antesDeBorrarFactura(Invoice $factura): void
    {
        $enlaces = DocumentoFacturado::where('invoice_id', $factura->id)->get();
        self::$alBorrar[$factura->id] = $enlaces->map(fn ($e) => [$e->documento_tipo, (int) $e->documento_id])->all();
        DocumentoFacturado::where('invoice_id', $factura->id)->delete();

        // La proforma guardaba su factura en converted_invoice_id
        ProformaInvoice::where('converted_invoice_id', $factura->id)->update(['converted_invoice_id' => null]);
    }

    public static function despuesDeBorrarFactura(Invoice $factura): void
    {
        foreach (self::$alBorrar[$factura->id] ?? [] as [$tipo, $id]) {
            if ($doc = Documentos::buscar($tipo, $id)) {
                self::recalcular($doc);
            }
        }
        unset(self::$alBorrar[$factura->id]);
    }

    private static function mayor(string $a, string $b): string
    {
        return (self::ORDEN[$b] ?? 0) > (self::ORDEN[$a] ?? 0) ? $b : $a;
    }
}
