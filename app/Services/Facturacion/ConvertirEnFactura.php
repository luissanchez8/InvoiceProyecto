<?php

namespace App\Services\Facturacion;

use App\Models\DocumentoFacturado;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\ProformaInvoice;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Onfactu v.1.17.0 — Convierte uno o varios documentos en una factura.
 *
 * Es el único camino para facturar un presupuesto, una proforma o un albarán
 * (antes había uno por documento, cada uno con sus diferencias). La factura:
 *   - nace en borrador, sin número, con la plantilla única;
 *   - lleva las líneas del documento, y si son varios albaranes, cada línea
 *     con el número de su albarán delante;
 *   - resta los anticipos ya facturados, con una línea en negativo por cada
 *     línea del anticipo (la misma base y el mismo IVA);
 *   - queda enlazada a cada documento, que pasa a Facturado y se bloquea.
 *
 * Varios documentos en una factura: del mismo tipo, cliente y moneda, y con
 * los impuestos puestos de la misma forma (todos por línea, o todos en el
 * total con los mismos tipos). Si no, se facturan por separado.
 */
class ConvertirEnFactura
{
    public static function desde(iterable $docs): Invoice
    {
        $docs = collect($docs)->values();
        self::comprobar($docs);

        [$anticipos, $borradores] = self::anticipos($docs);
        if ($borradores->isNotEmpty()) {
            throw new NoSePuedeFacturar('Hay una factura de anticipo en borrador. Apruébala o bórrala antes de hacer la factura final.');
        }

        return DB::transaction(fn () => self::crear($docs, $anticipos));
    }

    private static function comprobar(Collection $docs): void
    {
        if ($docs->isEmpty()) {
            throw new NoSePuedeFacturar('No hay nada que facturar.');
        }

        foreach ($docs as $doc) {
            if ($doc->billing_status === EstadoFacturacion::FACTURADO) {
                throw new NoSePuedeFacturar(Documentos::yaFacturado($doc));
            }
            // Una proforma o un albarán de un presupuesto que ya se ha
            // facturado por otro camino se facturaría dos veces.
            if (! $doc instanceof Estimate && $doc->estimate_id
                && ($p = Estimate::find($doc->estimate_id))
                && $p->billing_status === EstadoFacturacion::FACTURADO) {
                throw new NoSePuedeFacturar('Sale del presupuesto '.$p->estimate_number.', que ya está facturado.');
            }
        }

        if ($docs->count() === 1) {
            return;
        }

        $primero = $docs->first();
        $plural = Documentos::cfg($primero)['plural'];

        if ($docs->map(fn ($d) => Documentos::tipoDe($d))->unique()->count() > 1) {
            throw new NoSePuedeFacturar('Solo se pueden facturar juntos documentos del mismo tipo.');
        }
        if ($docs->pluck('customer_id')->unique()->count() > 1) {
            throw new NoSePuedeFacturar('Los '.$plural.' tienen que ser del mismo cliente.');
        }
        if ($docs->pluck('currency_id')->unique()->count() > 1) {
            throw new NoSePuedeFacturar('Los '.$plural.' tienen que estar en la misma moneda.');
        }
        if ($docs->pluck('tax_per_item')->map(fn ($v) => $v === 'YES' ? 'YES' : 'NO')->unique()->count() > 1) {
            throw new NoSePuedeFacturar('Unos '.$plural.' llevan los impuestos por línea y otros en el total. Factúralos por separado.');
        }
        if ($primero->tax_per_item !== 'YES') {
            $firmas = $docs->map(fn ($d) => collect(LineasFactura::impuestosGlobales($d))
                ->map(fn ($t) => $t['tax_type_id'].':'.$t['percent'])->sort()->implode('|'))->unique();
            if ($firmas->count() > 1) {
                throw new NoSePuedeFacturar('Los '.$plural.' llevan impuestos distintos. Factúralos por separado.');
            }
        }
    }

    /** Anticipos de todos los documentos, sin repetir: [aprobados, borradores]. */
    private static function anticipos(Collection $docs): array
    {
        $aprobados = collect();
        $borradores = collect();
        foreach ($docs as $doc) {
            [$a, $b] = EstadoFacturacion::anticipos($doc);
            $aprobados = $aprobados->concat($a);
            $borradores = $borradores->concat($b);
        }

        return [$aprobados->unique('id')->values(), $borradores->unique('id')->values()];
    }

    private static function crear(Collection $docs, Collection $anticipos): Invoice
    {
        $primero = $docs->first();
        $varios = $docs->count() > 1;

        $lineas = [];
        foreach ($docs as $doc) {
            $lineas = array_merge($lineas, LineasFactura::copiar($doc, $varios ? Documentos::numero($doc).' · ' : null));
        }
        foreach ($anticipos as $anticipo) {
            $lineas = array_merge($lineas, LineasFactura::deducir($anticipo));
        }

        $descuento = (int) $docs->sum('discount_val') - (int) $anticipos->sum('discount_val');
        $totales = LineasFactura::totales($lineas, LineasFactura::impuestosGlobales($primero), $descuento, (string) $primero->tax_per_item);

        if ($totales['total'] <= 0) {
            throw new NoSePuedeFacturar('Los anticipos ya cubren el total: no queda nada por facturar.');
        }

        $datos = [];
        if ($varios || $anticipos->isNotEmpty()) {
            // El descuento pasa a importe fijo: en porcentaje, al restar los
            // anticipos se volvería a aplicar sobre ellos.
            $datos['discount_type'] = 'fixed';
            $datos['discount'] = $descuento / 100;
        }
        if ($varios) {
            $datos['reference_number'] = null;
            $datos['discount_per_item'] = $docs->contains(fn ($d) => $d->discount_per_item === 'YES') ? 'YES' : 'NO';
            $datos['notes'] = '<p>'.e(ucfirst(Documentos::cfg($primero)['plural'])).': '
                .e($docs->map(fn ($d) => Documentos::numero($d).' del '.Carbon::parse($d->{Documentos::cfg($d)['fecha']})->format('d/m/Y'))->implode(', '))
                .'.</p>';
        }

        $factura = FacturaBorrador::crear($primero, $lineas, $totales, $datos);

        foreach ($docs as $doc) {
            self::marcarAceptado($doc, $factura);
            EstadoFacturacion::enlazar($doc, $factura, DocumentoFacturado::FINAL);
        }

        return $factura;
    }

    /** Facturar un presupuesto o una proforma es que el cliente lo ha aceptado. */
    private static function marcarAceptado($doc, Invoice $factura): void
    {
        if ($doc instanceof Estimate && $doc->status !== Estimate::STATUS_ACCEPTED) {
            $doc->forceFill(['status' => Estimate::STATUS_ACCEPTED])->saveQuietly();
        }
        if ($doc instanceof ProformaInvoice) {
            $doc->forceFill(['status' => ProformaInvoice::STATUS_ACCEPTED, 'converted_invoice_id' => $factura->id])->saveQuietly();
        }
    }
}
