<?php

namespace App\Providers;

use App\Listeners\RemitenteVerificado;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Onfactu v.1.14.0 — Registra el ajuste del remitente de todos los correos
 * (RemitenteVerificado). Está en bootstrap/providers.php.
 */
class RemitenteCorreoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(MessageSending::class, [RemitenteVerificado::class, 'handle']);
    }
}
