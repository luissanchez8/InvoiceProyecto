<?php

namespace App\Console\Commands;

use App\Services\ReaperturaMes;
use Illuminate\Console\Command;

/**
 * Onfactu v.1.16.0 — Cierra los meses abiertos para corregir que han pasado
 * su plazo (24 horas). El bloqueo vuelve a la hora exacta aunque esto no haya
 * corrido todavía (ClosedMonth::estaAbierto mira la hora); esto recalcula los
 * totales, los entrega y avisa a la gestoría.
 */
class GestoriaCerrarReabiertos extends Command
{
    protected $signature = 'gestoria:cerrar-reabiertos';

    protected $description = 'Vuelve a cerrar los meses abiertos para corregir que han caducado';

    public function handle(): int
    {
        $n = ReaperturaMes::cerrarCaducados();
        $this->info($n ? "Cerrados de nuevo: {$n}." : 'No había meses abiertos caducados.');

        return self::SUCCESS;
    }
}
