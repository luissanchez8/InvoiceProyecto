<?php

namespace App\Console\Commands;

use App\Services\CierreMes;
use Illuminate\Console\Command;

/**
 * Reintenta entregar a la central los meses cerrados que no llegaron.
 * Programada cada hora en routes/console.php.
 */
class GestoriaReenviarCierres extends Command
{
    protected $signature = 'gestoria:reenviar-cierres';

    protected $description = 'Reintenta entregar a la gestoría los meses cerrados que no llegaron';

    public function handle(): int
    {
        $n = CierreMes::reenviarPendientes();
        if ($n > 0) {
            $this->info("Entregados {$n} cierre(s) pendientes.");
        }

        return self::SUCCESS;
    }
}
