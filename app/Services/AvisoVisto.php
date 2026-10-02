<?php

namespace App\Services;

use App\Listeners\RemitenteVerificado;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Estimate;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Onfactu v.1.18.0 — Aviso a la empresa cuando su cliente abre una factura o
 * un presupuesto desde el enlace del correo.
 *
 * Se activa en Ajustes → Notificaciones (notify_invoice_viewed y
 * notify_estimate_viewed, apagados de fábrica). Sustituye a los correos del
 * programa base (InvoiceViewedMail y EstimateViewedMail), que tenían el
 * diseño antiguo, decían "InvoiceShelf" y no saltaban con la página de
 * aceptación de presupuestos de la v.1.17.0.
 *
 * Solo la primera vez que lo abre, y nunca si lo abre alguien de la empresa
 * con la sesión de Onfactu abierta (quien lo llama ya lo comprueba).
 */
class AvisoVisto
{
    public static function factura(Invoice $f): void
    {
        self::enviar($f->company_id, 'notify_invoice_viewed', 'la factura', $f->invoice_number,
            $f->customer?->name, url('/admin/invoices/'.$f->id.'/view'), 'Factura');
    }

    public static function presupuesto(Estimate $p): void
    {
        self::enviar($p->company_id, 'notify_estimate_viewed', 'el presupuesto', $p->estimate_number,
            $p->customer?->name, url('/admin/estimates/'.$p->id.'/view'), 'Presupuesto');
    }

    /** Correo al que se avisa: el de Ajustes → Notificaciones si es válido, o el de la empresa. */
    public static function destinatario(int $empresa): ?string
    {
        $correo = trim((string) CompanySetting::getSetting('notification_email', $empresa));
        if ($correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL)
            && ! str_ends_with(strtolower($correo), '@invoiceshelf.com')) {
            return $correo;
        }

        return RemitenteVerificado::correoDeLaEmpresa();
    }

    private static function enviar(int $empresa, string $ajuste, string $el, ?string $numero, ?string $cliente, string $enlace, string $titulo): void
    {
        if (CompanySetting::getSetting($ajuste, $empresa) !== 'YES') {
            return;
        }

        $para = self::destinatario($empresa);
        if (! $para) {
            Log::info('Documento visto sin correo al que avisar', ['ajuste' => $ajuste, 'numero' => $numero]);

            return;
        }

        $numero = $numero ?: 'BORRADOR';
        $cuerpo = CorreoOnfactu::p(e($cliente ?: 'Tu cliente').' ha abierto '.$el.' <strong>'.e($numero).'</strong> desde el enlace del correo.');

        try {
            Mail::html(CorreoOnfactu::html([
                'titulo' => $titulo.' '.$numero.' vist'.($titulo === 'Factura' ? 'a' : 'o'),
                'cuerpo' => $cuerpo,
                'boton_texto' => 'Ver '.mb_strtolower($titulo),
                'boton_url' => $enlace,
                'nota_pie' => 'Recibes este aviso porque está activado en Ajustes → Notificaciones.',
                'logo' => Company::find($empresa)?->logo ?: CorreoOnfactu::LOGO_GESTORIA,
            ]), function ($m) use ($para, $titulo, $numero) {
                $m->to($para)->subject($titulo.' '.$numero.' vist'.($titulo === 'Factura' ? 'a' : 'o').' por tu cliente');
            });
        } catch (\Throwable $e) {
            Log::warning('Aviso de documento visto no enviado', ['numero' => $numero, 'error' => $e->getMessage()]);
        }
    }
}
