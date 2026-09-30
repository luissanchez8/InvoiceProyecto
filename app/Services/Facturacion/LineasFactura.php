<?php

namespace App\Services\Facturacion;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Onfactu v.1.17.0 — Las líneas y los importes de una factura que sale de un
 * presupuesto, una proforma o un albarán.
 *
 * Una línea es ['linea' => [campos de invoice_items], 'impuestos' => [[campos
 * de taxes], ...]]. Los importes van en céntimos, como en toda la aplicación.
 *
 * Los cálculos imitan a los del formulario de factura (stores/invoice.js y
 * CreateItemRowTax.vue), para que al abrir el borrador y guardarlo no cambie
 * ninguna cifra:
 *   - total de la línea = precio × cantidad − descuento de la línea
 *   - impuestos por línea: sobre el total de la línea
 *   - impuestos globales: sobre subtotal − descuento del documento
 *   - total = subtotal − descuento + impuestos
 */
class LineasFactura
{
    private const CAMPOS_LINEA = ['name', 'description', 'discount_type', 'price', 'quantity', 'discount',
        'discount_val', 'tax', 'total', 'item_id', 'unit_name'];

    private const CAMPOS_IMPUESTO = ['tax_type_id', 'name', 'percent', 'compound_tax', 'calculation_type',
        'fixed_amount', 'amount'];

    /** Las líneas del documento tal cual, con un prefijo opcional ("ALB-000003 · "). */
    public static function copiar(Model $doc, ?string $prefijo = null): array
    {
        $doc->loadMissing('items.taxes');
        $lineas = [];
        foreach ($doc->items as $item) {
            $linea = array_intersect_key($item->getAttributes(), array_flip(self::CAMPOS_LINEA));
            if ($prefijo) {
                $linea['name'] = mb_substr($prefijo.$linea['name'], 0, 255);
            }
            $lineas[] = [
                'linea' => $linea,
                'impuestos' => $item->taxes->map(fn ($t) => self::impuesto($t->getAttributes()))->filter(fn ($t) => $t['amount'])->values()->all(),
            ];
        }

        return $lineas;
    }

    /**
     * Las líneas de un anticipo: el documento a escala ($ratio), una línea por
     * cada combinación de impuestos, para que cada tipo de IVA lleve su base.
     * Con impuestos globales es una sola línea.
     */
    public static function anticipo(Model $doc, float $ratio, string $texto): array
    {
        $doc->loadMissing('items.taxes');

        if ($doc->tax_per_item !== 'YES') {
            return [[
                'linea' => self::lineaNueva($texto, (int) round($doc->sub_total * $ratio)),
                'impuestos' => [],
            ]];
        }

        $grupos = [];
        foreach ($doc->items as $item) {
            $impuestos = $item->taxes->map(fn ($t) => self::impuesto($t->getAttributes()))->values()->all();
            $firma = collect($impuestos)->map(fn ($t) => $t['tax_type_id'].':'.$t['percent'])->sort()->implode('|');
            $grupos[$firma] ??= ['base' => 0, 'impuestos' => $impuestos];
            $grupos[$firma]['base'] += (int) $item->total;
        }

        $lineas = [];
        foreach ($grupos as $g) {
            $nombre = $texto;
            if (count($grupos) > 1) {
                $nombre .= ' · '.(collect($g['impuestos'])->pluck('name')->implode(', ') ?: 'Sin impuestos');
            }
            $linea = self::lineaNueva($nombre, (int) round($g['base'] * $ratio));
            $lineas[] = self::conImpuestos($linea, $g['impuestos']);
        }

        return $lineas;
    }

    /** Las líneas que restan un anticipo en la factura final: las suyas, con cantidad negativa. */
    public static function deducir(Invoice $anticipo): array
    {
        $anticipo->loadMissing('items.taxes');
        $nombre = 'Anticipo según factura '.$anticipo->invoice_number
            .' del '.Carbon::parse($anticipo->invoice_date)->format('d/m/Y');

        $lineas = [];
        foreach ($anticipo->items as $item) {
            $linea = self::lineaNueva($nombre, (int) $item->price, -1 * (float) $item->quantity);
            $linea['description'] = $item->name;
            $impuestos = $item->taxes->map(fn ($t) => self::impuesto($t->getAttributes()))->all();
            $lineas[] = self::conImpuestos($linea, $impuestos);
        }

        return $lineas;
    }

    /** Los impuestos globales de uno o varios documentos, sin importe (se calcula en totales()). */
    public static function impuestosGlobales(Model $doc): array
    {
        $doc->loadMissing('taxes');

        return $doc->taxes->map(fn ($t) => self::impuesto($t->getAttributes()))->values()->all();
    }

    /**
     * Subtotal, impuestos y total de una factura con estas líneas.
     * Devuelve también los impuestos globales con su importe.
     */
    public static function totales(array $lineas, array $globales, int $descuento, string $impuestoPorLinea): array
    {
        $subtotal = (int) array_sum(array_map(fn ($l) => (int) $l['linea']['total'], $lineas));
        $base = $subtotal - $descuento;

        if ($impuestoPorLinea === 'YES') {
            $impuesto = (int) array_sum(array_map(fn ($l) => (int) $l['linea']['tax'], $lineas));
            $globales = [];
        } else {
            $simples = 0;
            foreach ($globales as $i => $t) {
                if (! $t['compound_tax']) {
                    $globales[$i]['amount'] = self::cuota($t, $base);
                    $simples += $globales[$i]['amount'];
                }
            }
            foreach ($globales as $i => $t) {
                if ($t['compound_tax']) {
                    $globales[$i]['amount'] = self::cuota($t, $base + $simples);
                }
            }
            $impuesto = (int) array_sum(array_column($globales, 'amount'));
        }

        return [
            'sub_total' => $subtotal,
            'discount_val' => $descuento,
            'tax' => $impuesto,
            'total' => $base + $impuesto,
            'impuestos_globales' => $globales,
        ];
    }

    // ─────────────────────────────────────────────────────────────

    private static function lineaNueva(string $nombre, int $precio, float $cantidad = 1): array
    {
        return [
            'name' => mb_substr($nombre, 0, 255), 'description' => null, 'discount_type' => 'fixed',
            'price' => $precio, 'quantity' => $cantidad, 'discount' => 0, 'discount_val' => 0,
            'tax' => 0, 'total' => (int) round($precio * $cantidad), 'item_id' => null, 'unit_name' => null,
        ];
    }

    /** Pone los impuestos a la línea y calcula su cuota, como CreateItemRowTax.vue. */
    private static function conImpuestos(array $linea, array $impuestos): array
    {
        $cuotas = 0;
        foreach ($impuestos as $i => $t) {
            $impuestos[$i]['amount'] = self::cuota($t, (int) $linea['total']);
            $cuotas += $impuestos[$i]['amount'];
        }
        $linea['tax'] = $cuotas;

        return ['linea' => $linea, 'impuestos' => array_values(array_filter($impuestos, fn ($t) => $t['amount']))];
    }

    private static function cuota(array $impuesto, int $base): int
    {
        if (($impuesto['calculation_type'] ?? 'percentage') === 'fixed') {
            return (int) ($impuesto['fixed_amount'] ?? 0) * ($base < 0 ? -1 : 1);
        }

        return (int) round($base * (float) $impuesto['percent'] / 100);
    }

    private static function impuesto(array $t): array
    {
        $t = array_intersect_key($t, array_flip(self::CAMPOS_IMPUESTO));
        $t['compound_tax'] = (int) ($t['compound_tax'] ?? 0);
        $t['calculation_type'] ??= 'percentage';
        $t['amount'] = (int) ($t['amount'] ?? 0);

        return $t;
    }
}
