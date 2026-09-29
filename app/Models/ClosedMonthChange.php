<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Onfactu v.1.16.0 — Lo que se hace con un mes cerrado mientras está abierto
 * para corregir: abrirlo, cada documento creado, editado o borrado, y volver a
 * cerrarlo. Ver App\Services\ReaperturaMes.
 */
class ClosedMonthChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id', 'closed_month_id', 'year', 'month', 'reapertura', 'user_id',
        'accion', 'tipo', 'documento_id', 'descripcion',
    ];

    public function closedMonth()
    {
        return $this->belongsTo(ClosedMonth::class);
    }
}
