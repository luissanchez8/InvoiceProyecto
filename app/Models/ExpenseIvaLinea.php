<?php

namespace App\Models;

use App\Support\IvaGastos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Onfactu v.1.15.0 — Un tipo de IVA de un gasto: base, cuota y si es
 * deducible. Un gasto desglosado tiene una o varias. Ver App\Support\IvaGastos.
 */
class ExpenseIvaLinea extends Model
{
    protected $table = 'expense_iva_lineas';

    protected $guarded = ['id'];

    protected $appends = ['nombre'];

    protected function casts(): array
    {
        return [
            'porcentaje' => 'float',
            'base' => 'integer',
            'cuota' => 'integer',
            'deducible' => 'boolean',
            'autoliquidacion' => 'boolean',
        ];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function getNombreAttribute(): string
    {
        return IvaGastos::nombre($this->tipo);
    }
}
