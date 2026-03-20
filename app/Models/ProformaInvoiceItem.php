<?php

/**
 * Modelo ProformaInvoiceItem — Línea de factura proforma
 *
 * Cada instancia representa un concepto/línea dentro de una factura proforma.
 * Contiene nombre, descripción, cantidad, precio, descuento e impuesto.
 * Los valores monetarios se almacenan en céntimos para precisión.
 */

namespace App\Models;

use App\Traits\HasCustomFieldsTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProformaInvoiceItem extends Model
{
    use HasCustomFieldsTrait;
    use HasFactory;

    protected $table = 'proforma_invoice_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'total' => 'integer',
            'discount' => 'float',
            'discount_val' => 'integer',
            'tax' => 'integer',
            'quantity' => 'float',
            'exchange_rate' => 'float',
        ];
    }

    /** Factura proforma padre */
    public function proformaInvoice(): BelongsTo
    {
        return $this->belongsTo(ProformaInvoice::class);
    }

    /** Referencia al catálogo de items */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** Impuestos aplicados a esta línea */
    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class, 'proforma_invoice_item_id');
    }
}
