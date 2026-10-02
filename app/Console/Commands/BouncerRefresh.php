<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Silber\Bouncer\BouncerFacade;

/**
 * Onfactu v.1.18.0 — Vacía la caché de permisos (Bouncer).
 *
 * El proceso de altas (worker-altas.js) la llama al terminar de crear una
 * instancia y al cambiar de plan. Hasta ahora no existía: cada llamada dejaba
 * "Command bouncer:refresh is not defined" en el registro de la instancia,
 * sin más efecto, porque el cache:clear de después ya refrescaba los permisos.
 */
class BouncerRefresh extends Command
{
    protected $signature = 'bouncer:refresh';

    protected $description = 'Vacía la caché de permisos para que se lean de nuevo';

    public function handle(): int
    {
        BouncerFacade::refresh();
        $this->info('Permisos refrescados.');

        return self::SUCCESS;
    }
}
