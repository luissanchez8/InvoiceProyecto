<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Onfactu — Mes cerrado.
 *
 * Un registro por (company_id, year, month). Su existencia significa que ese
 * mes esta CERRADO.
 *
 * v.1.16.0: el propietario puede abrirlo para corregirlo (reopened_at), hasta
 * reopen_expires_at como mucho. Mientras está abierto se pueden cambiar sus
 * gastos, cobros, presupuestos, proformas y albaranes, pero NUNCA sus facturas:
 * una factura emitida no se modifica, se corrige con una rectificativa
 * (Real Decreto 1007/2023). Ver App\Services\ReaperturaMes.
 */
class ClosedMonth extends Model
{
    protected $fillable = [
        'company_id', 'year', 'month', 'closed_by', 'closed_at', 'totals',
        'sent_status', 'sent_at', 'sent_error', 'sent_attempts',
        'reopened_at', 'reopened_by', 'reopen_reason', 'reopen_expires_at', 'reopen_count',
    ];

    protected $casts = [
        'closed_at'     => 'datetime',
        'sent_at'       => 'datetime',
        'totals'        => 'array',
        'year'          => 'integer',
        'month'         => 'integer',
        'sent_attempts' => 'integer',
        'reopened_at'   => 'datetime',
        'reopen_expires_at' => 'datetime',
        'reopen_count'  => 'integer',
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
        return static::cargar($companyId)['cerrados'];
    }

    /** Periodos cerrados que ahora mismo están abiertos para corregir. */
    public static function reopenedPeriodsFor(int $companyId): array
    {
        return static::cargar($companyId)['abiertos'];
    }

    private static function cargar(int $companyId): array
    {
        if (! array_key_exists($companyId, static::$cache)) {
            $filas = static::query()->where('company_id', $companyId)->get();
            $periodo = fn ($r) => sprintf('%04d-%02d', $r->year, $r->month);
            static::$cache[$companyId] = [
                'cerrados' => $filas->map($periodo)->all(),
                'abiertos' => $filas->filter(fn ($r) => $r->estaAbierto())->map($periodo)->values()->all(),
            ];
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
     * True si la fecha cae en un mes cerrado que ahora está abierto para
     * corregir (y no ha caducado).
     */
    public static function isReopened(int $companyId, $date): bool
    {
        $period = static::toPeriod($date);

        return $period !== null && in_array($period, static::reopenedPeriodsFor($companyId), true);
    }

    /**
     * True si un documento de ese tipo, con esa fecha, no se puede tocar.
     * Las facturas no se abren nunca; lo demás, sí mientras el mes esté
     * abierto para corregir.
     */
    public static function isLocked(int $companyId, $date, string $recurso): bool
    {
        if (! static::isClosed($companyId, $date)) {
            return false;
        }

        return $recurso === 'invoices' || ! static::isReopened($companyId, $date);
    }

    /** Si este mes está abierto para corregir ahora mismo. */
    public function estaAbierto(): bool
    {
        return $this->reopened_at !== null
            && ($this->reopen_expires_at === null || $this->reopen_expires_at->isFuture());
    }

    public function cambios()
    {
        return $this->hasMany(ClosedMonthChange::class);
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
