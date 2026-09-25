<?php

namespace App\Listeners;

use App\Models\Company;
use App\Models\CompanySetting;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;

/**
 * Onfactu v.1.14 — Todo el correo sale desde el dominio de la plataforma y las
 * respuestas llegan a la empresa.
 *
 * Las facturas, presupuestos y cobros salían con la dirección escrita en el
 * campo "De" de la ventana de envío. El servidor de correo solo puede enviar
 * en nombre de onfactu.com: con otra dirección, el correo acaba en spam, y con
 * Resend directamente se rechaza.
 *
 * Justo antes de enviar cada correo:
 *  - Remitente: siempre del dominio de la plataforma (MAIL_FROM_ADDRESS),
 *    con el nombre que traiga (el de la empresa en facturas y presupuestos).
 *  - Responder a, si el correo no lo trae ya:
 *      · si en "De" se escribió otra dirección, esa;
 *      · si se dejó la de la plataforma, el correo de la empresa: el de
 *        contacto de su ficha, si no el de notificaciones y si no el del
 *        usuario propietario.
 *    Así, si el cliente contesta, le llega a la empresa y no a no-reply.
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
        $nombre = $original?->getName() ?: (string) config('mail.from.name');
        $esDeLaPlataforma = $original && str_ends_with(strtolower($original->getAddress()), '@'.$dominio);

        if (! $mensaje->getReplyTo()) {
            $responderA = $esDeLaPlataforma || ! $original
                ? self::correoEmpresa($dominio)
                : $original->getAddress();
            if ($responderA) {
                $mensaje->replyTo(new Address($responderA, $nombre));
            }
        }

        if (! $esDeLaPlataforma) {
            $mensaje->from(new Address($plataforma, $nombre));
        }
    }

    /** Correo de la empresa de esta instancia, o null si no tiene uno válido. */
    private static function correoEmpresa(string $dominio): ?string
    {
        try {
            $empresa = Company::query()->orderBy('id')->first();
            if (! $empresa) {
                return null;
            }

            $candidatos = [
                $empresa->contact_email,
                CompanySetting::getSetting('notification_email', $empresa->id),
                $empresa->owner?->email,
            ];

            foreach ($candidatos as $correo) {
                $correo = trim((string) $correo);
                if ($correo !== ''
                    && filter_var($correo, FILTER_VALIDATE_EMAIL)
                    && ! str_ends_with(strtolower($correo), '@'.$dominio)
                    && ! str_ends_with(strtolower($correo), '@invoiceshelf.com')) {
                    return $correo;
                }
            }
        } catch (\Throwable $e) {
            // Sin "Responder a" antes que no enviar el correo
        }

        return null;
    }
}
