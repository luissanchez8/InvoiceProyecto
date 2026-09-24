<?php

namespace App\Console\Commands;

use App\Models\ClosedMonth;
use App\Services\CierreMes;
use App\Services\GestoriaService;
use Illuminate\Console\Command;

/**
 * Recalcula los totales de los meses ya cerrados con la regla actual de
 * CierreMes.
 *
 * Hasta la v.1.12.0 solo se contaban las facturas cobradas, así que los meses
 * cerrados con facturas pendientes de cobro tienen los totales incompletos,
 * en la instancia y en la central. No cambia ningún documento: solo el
 * resumen congelado, que era erróneo.
 *
 *   php artisan gestoria:recalcular-cierres           ensayo: enseña las diferencias
 *   php artisan gestoria:recalcular-cierres --apply   las corrige
 */
class GestoriaRecalcularCierres extends Command
{
    protected $signature = 'gestoria:recalcular-cierres {--apply : Guardar los totales corregidos}';

    protected $description = 'Recalcula los totales de los meses cerrados con la regla actual';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('apply');
        $cambios = 0;

        foreach (ClosedMonth::orderBy('year')->orderBy('month')->get() as $c) {
            $nuevo = CierreMes::resumen($c->company_id, $c->year, $c->month)['totales'];
            $viejo = $c->totals ?? [];
            if ($nuevo == $viejo) {
                continue;
            }

            $cambios++;
            $this->line(sprintf('  %d-%02d: facturas %d -> %d, bruto %s -> %s',
                $c->year, $c->month, $viejo['facturas'] ?? 0, $nuevo['facturas'],
                number_format(($viejo['bruto'] ?? 0) / 100, 2, ',', '.'), number_format($nuevo['bruto'] / 100, 2, ',', '.')));

            if ($aplicar) {
                $c->update(['totals' => $nuevo]);
                if ($c->sent_status === 'sent' && ! GestoriaService::actualizarTotales($c->year, $c->month, $nuevo)) {
                    $this->warn('    No se pudo actualizar en la central');
                }
            }
        }

        $this->info($cambios === 0 ? 'Todos los cierres están bien.'
            : ($aplicar ? "Corregidos {$cambios} cierre(s)." : "{$cambios} cierre(s) por corregir. Repite con --apply."));

        return self::SUCCESS;
    }
}
