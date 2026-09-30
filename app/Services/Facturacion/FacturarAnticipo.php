<?php

namespace App\Services\Facturacion;

use App\Models\DocumentoFacturado;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Onfactu v.1.17.0 — Factura de anticipo de un presupuesto o una proforma.
 *
 * Un anticipo cobrado lleva IVA desde que se cobra (artículo 75.2 de la Ley
 * del IVA), así que no basta con apuntarlo: hace falta una factura. Es una
 * factura normal de la serie de siempre, que nace en borrador. Sus líneas son
 * el documento a escala (una por cada combinación de impuestos), así cada
 * tipo de IVA lleva su base.
 *
 * La factura final del documento lo resta (ConvertirEnFactura). Se pueden
 * hacer varios anticipos, pero entre todos tienen que dejar algo pendiente:
 * el último cobro se hace con la factura final.
 */
class FacturarAnticipo
{
    public const PORCENTAJE = 'porcentaje';

    public const IMPORTE = 'importe';

    /**
     * @param  string  $modo  porcentaje (del total) o importe (en euros, con impuestos)
     */
    public static function crear(Model $doc, string $modo, float $valor): Invoice
    {
        $tipo = Documentos::tipoDe($doc);
        if (! in_array($tipo, Documentos::CON_ANTICIPO, true)) {
            throw new NoSePuedeFacturar('Los anticipos se facturan desde un presupuesto o una proforma.');
        }
        if ($doc->billing_status === EstadoFacturacion::FACTURADO) {
            throw new NoSePuedeFacturar(Documentos::yaFacturado($doc));
        }

        if ($doc->estimate_id && ($p = \App\Models\Estimate::find($doc->estimate_id))
            && $p->billing_status === EstadoFacturacion::FACTURADO) {
            throw new NoSePuedeFacturar('Sale del presupuesto '.$p->estimate_number.', que ya está facturado.');
        }

        $total = (int) $doc->total;
        if ($total <= 0) {
            throw new NoSePuedeFacturar('El documento no tiene importe.');
        }

        $importe = $modo === self::PORCENTAJE
            ? (int) round($total * $valor / 100)
            : (int) round($valor * 100);
        if ($importe <= 0) {
            throw new NoSePuedeFacturar('Indica un importe mayor que cero.');
        }

        [$aprobados, $borradores] = EstadoFacturacion::anticipos($doc);
        $pendiente = $total - (int) $aprobados->concat($borradores)->sum('total');
        if ($importe >= $pendiente) {
            throw new NoSePuedeFacturar('El anticipo tiene que ser menor que lo pendiente ('
                .number_format($pendiente / 100, 2, ',', '.').' €). Para cobrarlo todo, haz la factura final.');
        }

        $ratio = $importe / $total;
        $porcentaje = rtrim(rtrim(number_format($ratio * 100, 2, ',', ''), '0'), ',');
        $texto = 'Anticipo '.self::del($doc).' '.Documentos::numero($doc).' ('.$porcentaje.' %)';

        $lineas = LineasFactura::anticipo($doc, $ratio, $texto);
        $totales = LineasFactura::totales(
            $lineas,
            LineasFactura::impuestosGlobales($doc),
            (int) round((int) $doc->discount_val * $ratio),
            (string) $doc->tax_per_item
        );

        return DB::transaction(function () use ($doc, $lineas, $totales) {
            $factura = FacturaBorrador::crear($doc, $lineas, $totales, [
                'discount_type' => 'fixed',
                'discount' => $totales['discount_val'] / 100,
                'discount_per_item' => 'NO',
                'notes' => '<p>Factura de anticipo '.e(self::del($doc)).' '.e(Documentos::numero($doc)).'.</p>',
            ]);
            EstadoFacturacion::enlazar($doc, $factura, DocumentoFacturado::ANTICIPO);

            return $factura;
        });
    }

    /** "del presupuesto", "de la proforma" */
    private static function del(Model $doc): string
    {
        $el = Documentos::cfg($doc)['el'];

        return str_starts_with($el, 'el ') ? 'del '.substr($el, 3) : 'de '.$el;
    }
}
