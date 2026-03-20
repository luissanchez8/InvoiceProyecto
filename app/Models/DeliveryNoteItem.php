<?php

/**
 * Modelo DeliveryNoteItem — Línea de albarán
 *
 * Cada instancia representa un concepto/línea dentro de un albarán.
 * Misma estructura que InvoiceItem con FK a delivery_notes.
 */

namespace App\Models;

use App\Traits\HasCustomFieldsTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryNoteItem extends Model
{
    use HasCustomFieldsTrait;
    use HasFactory;

    protected $table = 'delivery_note_items';

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

    /** Albarán padre */
    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    /** Referencia al catálogo de items */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** Impuestos aplicados a esta línea */
    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class, 'delivery_note_item_id');
    }
}
