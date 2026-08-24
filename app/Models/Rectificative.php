<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Onfactu — Factura rectificativa.
 *
 * Comparte tabla con Invoice; lo unico que la distingue es tener
 * `rectifies_invoice_id` relleno.
 *
 * Existe como modelo propio por una razon concreta: el SerialNumberFormatter
 * deduce la clave del ajuste de numeracion a partir del nombre de la clase
 * (`strtolower(class_basename($model)).'_number_format'`). Con este modelo:
 *
 *   - Coge `rectificative_number_format`  -> serie REC
 *   - Coge `rectificative_start_number`
 *   - Y como el scope global filtra solo rectificativas, la secuencia se
 *     calcula unicamente sobre ellas: REC-000001, REC-000002...
 *     independiente del contador de facturas.
 *
 * Asi no hay que tocar el SerialNumberFormatter, que es codigo compartido.
 */
class Rectificative extends Invoice
{
    protected $table = 'invoices';

    protected static function booted(): void
    {
        // Solo ve rectificativas
        static::addGlobalScope('rectificatives', function (Builder $q) {
            $q->whereNotNull('invoices.rectifies_invoice_id');
        });
    }
}
