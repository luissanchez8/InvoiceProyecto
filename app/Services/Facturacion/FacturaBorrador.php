<?php

namespace App\Services\Facturacion;

use App\Models\CompanySetting;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Onfactu v.1.17.0 — Crea la factura en borrador (sin número) a partir de un
 * documento comercial, con las líneas y los totales ya calculados por
 * LineasFactura. La usan la conversión y los anticipos.
 *
 * La factura nace como cualquier otra: en borrador, con la plantilla única, y
 * recibe su número al aprobarla (AprobarFactura).
 */
class FacturaBorrador
{
    public static function crear(Model $doc, array $lineas, array $totales, array $datos = []): Invoice
    {
        $rate = (float) ($doc->exchange_rate ?: 1);
        $empresa = (int) $doc->company_id;
        $hoy = Carbon::now();

        $vencimiento = null;
        if (CompanySetting::getSetting('invoice_set_due_date_automatically', $empresa) === 'YES') {
            $dias = (int) CompanySetting::getSetting('invoice_due_date_days', $empresa);
            $vencimiento = $hoy->copy()->addDays($dias)->format('Y-m-d');
        }

        $total = (int) $totales['total'];
        $factura = Invoice::create(array_merge([
            'creator_id' => Auth::id(),
            'invoice_date' => $hoy->format('Y-m-d'),
            'due_date' => $vencimiento,
            'invoice_number' => null,
            'sequence_number' => null,
            'customer_sequence_number' => null,
            'reference_number' => $doc->reference_number,
            'customer_id' => $doc->customer_id,
            'company_id' => $empresa,
            'template_name' => Invoice::PLANTILLA_PDF,
            'status' => Invoice::STATUS_DRAFT,
            'paid_status' => Invoice::STATUS_UNPAID,
            'tax_per_item' => $doc->tax_per_item,
            'discount_per_item' => $doc->discount_per_item,
            'discount_type' => $doc->discount_type,
            'discount' => $doc->discount,
            'discount_val' => (int) $totales['discount_val'],
            'sub_total' => (int) $totales['sub_total'],
            'tax' => (int) $totales['tax'],
            'total' => $total,
            'due_amount' => $total,
            'notes' => $doc->notes,
            'currency_id' => $doc->currency_id,
            'exchange_rate' => $rate,
            'base_discount_val' => (int) round($totales['discount_val'] * $rate),
            'base_sub_total' => (int) round($totales['sub_total'] * $rate),
            'base_tax' => (int) round($totales['tax'] * $rate),
            'base_total' => (int) round($total * $rate),
            'base_due_amount' => (int) round($total * $rate),
            'payment_method_id' => $doc->payment_method_id,
            'sales_tax_type' => $doc->sales_tax_type,
            'sales_tax_address_type' => $doc->sales_tax_address_type,
        ], $datos));

        $factura->unique_hash = Hashids::connection(Invoice::class)->encode($factura->id);
        $factura->save();

        foreach ($lineas as $l) {
            $linea = $l['linea'];
            $item = $factura->items()->create(array_merge($linea, [
                'company_id' => $empresa,
                'exchange_rate' => $rate,
                'base_price' => (int) round($linea['price'] * $rate),
                'base_discount_val' => (int) round($linea['discount_val'] * $rate),
                'base_tax' => (int) round($linea['tax'] * $rate),
                'base_total' => (int) round($linea['total'] * $rate),
            ]));
            foreach ($l['impuestos'] as $t) {
                $item->taxes()->create(self::impuesto($t, $empresa, $rate, $doc->currency_id));
            }
        }

        foreach ($totales['impuestos_globales'] as $t) {
            $factura->taxes()->create(self::impuesto($t, $empresa, $rate, $doc->currency_id));
        }

        return $factura->fresh();
    }

    private static function impuesto(array $t, int $empresa, float $rate, $moneda): array
    {
        return array_merge($t, [
            'company_id' => $empresa,
            'exchange_rate' => $rate,
            'base_amount' => (int) round($t['amount'] * $rate),
            'currency_id' => $moneda,
        ]);
    }
}
