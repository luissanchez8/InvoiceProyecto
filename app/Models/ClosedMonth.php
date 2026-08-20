<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Onfactu — Mes cerrado.
 *
 * Un registro por (company_id, year, month). Su existencia significa que ese
 * mes esta CERRADO de forma irreversible.
 */
class ClosedMonth extends Model
{
    protected $fillable = [
        'company_id', 'year', 'month', 'closed_by', 'closed_at', 'totals',
        'sent_status', 'sent_at', 'sent_error', 'sent_attempts',
    ];

    protected $casts = [
        'closed_at'     => 'datetime',
        'sent_at'       => 'datetime',
        'totals'        => 'array',
        'year'          => 'integer',
        'month'         => 'integer',
        'sent_attempts' => 'integer',
    ];

    /**
     * Cache por request: evita repetir la consulta en cada comprobacion del
     * middleware cuando una peticion valida varias fechas (p.ej. borrado masivo).
     */
    protected static array $cache = [];

    /**
     * Devuelve los periodos cerrados de una empresa como array ['2026-07', ...].
     */
    public static function periodsFor(int $companyId): array
    {
        if (! array_key_exists($companyId, static::$cache)) {
            static::$cache[$companyId] = static::query()
                ->where('company_id', $companyId)
                ->get(['year', 'month'])
                ->map(fn ($r) => sprintf('%04d-%02d', $r->year, $r->month))
                ->all();
        }

        return static::$cache[$companyId];
    }

    /**
     * Limpia la cache (necesario tras cerrar un mes dentro de la misma peticion).
     */
    public static function forgetCache(?int $companyId = null): void
    {
        if ($companyId === null) {
            static::$cache = [];
        } else {
            unset(static::$cache[$companyId]);
        }
    }

    /**
     * True si la fecha dada cae en un mes ya cerrado.
     * Acepta string ('2026-07-15', ISO-8601...), Carbon o null.
     */
    public static function isClosed(int $companyId, $date): bool
    {
        $period = static::toPeriod($date);
        if ($period === null) {
            return false;
        }

        return in_array($period, static::periodsFor($companyId), true);
    }

    /**
     * Normaliza cualquier fecha a 'YYYY-MM'. Devuelve null si no es parseable.
     */
    public static function toPeriod($date): ?string
    {
        if (empty($date)) {
            return null;
        }

        if ($date instanceof Carbon) {
            return $date->format('Y-m');
        }

        try {
            return Carbon::parse($date)->format('Y-m');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Etiqueta legible del periodo, para mensajes de error.
     */
    public static function label($date): string
    {
        $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                  'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

        try {
            $c = $date instanceof Carbon ? $date : Carbon::parse($date);

            return $meses[(int) $c->format('n')].' de '.$c->format('Y');
        } catch (\Throwable $e) {
            return (string) $date;
        }
    }

    public function scopePending($query)
    {
        return $query->whereIn('sent_status', ['pending', 'failed']);
    }
}
