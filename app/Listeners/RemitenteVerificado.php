<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;

/**
 * Onfactu v.1.14.0 — Todo el correo sale desde el dominio de la plataforma.
 *
 * Las facturas, presupuestos y cobros salían con la dirección escrita en el
 * campo "De" de la ventana de envío (normalmente la de la empresa). El
 * servidor de correo solo puede enviar en nombre de onfactu.com: con otra
 * dirección, el correo acaba en spam, y con Resend directamente se rechaza.
 *
 * Justo antes de enviar cada correo:
 *  - Si el remitente es de otro dominio, sale desde la dirección de la
 *    plataforma (MAIL_FROM_ADDRESS) con el nombre de la empresa, y esa
 *    dirección original pasa a "Responder a": si el cliente contesta, le
 *    llega a la empresa.
 *  - Si ya es del dominio de la plataforma, no se toca.
 *
 * Un solo sitio para todos los correos, en vez de tocar cada uno.
 */
class RemitenteVerificado
{
    public function handle(MessageSending $event): void
    {
        $plataforma = (string) config('mail.from.address');
        $dominio = strtolower((string) substr((string) strrchr($plataforma, '@'), 1));
        if ($dominio === '') {
            return;
        }

        $mensaje = $event->message;
        $original = $mensaje->getFrom()[0] ?? null;

        if (! $original) {
            $mensaje->from(new Address($plataforma, (string) config('mail.from.name')));

            return;
        }

        if (str_ends_with(strtolower($original->getAddress()), '@'.$dominio)) {
            return;
        }

        if (! $mensaje->getReplyTo()) {
            $mensaje->replyTo(new Address($original->getAddress(), $original->getName()));
        }

        $mensaje->from(new Address($plataforma, $original->getName() ?: (string) config('mail.from.name')));
    }
}
