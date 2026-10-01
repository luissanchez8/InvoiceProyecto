<?php

namespace App\Http\Controllers\V1\Customer;

use App\Http\Controllers\Controller;
use App\Listeners\RemitenteVerificado;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\EmailLog;
use App\Models\Estimate;
use App\Services\CorreoOnfactu;
use App\Services\Facturacion\EstadoFacturacion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Onfactu v.1.17.0 — Aceptar o rechazar un presupuesto desde el correo.
 *
 * El botón del correo del presupuesto lleva a /presupuesto/{token}: una
 * página con el presupuesto, el enlace al PDF y dos botones. El cliente no
 * necesita cuenta: el token es el del registro del envío (email_logs), el
 * mismo que ya servía para ver el PDF, y caduca igual.
 *
 * Al responder, el presupuesto pasa a aceptado o rechazado, se guarda quién
 * y cuándo (respuesta_*) y la empresa recibe un correo.
 *
 * v.1.17.2: limitador de intentos propio (daba 429 con la sesión de la
 * empresa abierta) y el "visto" no cuenta cuando lo abre la propia empresa.
 */
class PresupuestoPublicoController extends Controller
{
    public function ver(EmailLog $emailLog)
    {
        $presupuesto = $this->presupuesto($emailLog);

        // Visto solo si lo abre el cliente: no la propia empresa con su sesión
        // abierta, que lo está comprobando (v.1.17.2)
        if (! auth()->check() && ! $emailLog->isExpired()
            && in_array($presupuesto->status, [Estimate::STATUS_SENT, Estimate::STATUS_DRAFT], true)) {
            $presupuesto->forceFill(['status' => Estimate::STATUS_VIEWED])->saveQuietly();
        }

        return $this->pagina($emailLog, $presupuesto);
    }

    public function responder(Request $request, EmailLog $emailLog)
    {
        $presupuesto = $this->presupuesto($emailLog);
        $datos = $request->validate([
            'accion' => ['required', 'in:aceptar,rechazar'],
            'nombre' => ['nullable', 'string', 'max:150'],
            'comentario' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($this->motivoSinBotones($emailLog, $presupuesto) === null) {
            $acepta = $datos['accion'] === 'aceptar';
            $presupuesto->forceFill([
                'status' => $acepta ? Estimate::STATUS_ACCEPTED : Estimate::STATUS_REJECTED,
                'respuesta_at' => now(),
                'respuesta_nombre' => trim((string) ($datos['nombre'] ?? '')) ?: null,
                'respuesta_comentario' => trim((string) ($datos['comentario'] ?? '')) ?: null,
            ])->saveQuietly();

            $this->avisarEmpresa($presupuesto->fresh(), $acepta);
        }

        return redirect('/presupuesto/'.$emailLog->token);
    }

    // ─────────────────────────────────────────────────────────────

    private function presupuesto(EmailLog $emailLog): Estimate
    {
        abort_unless($emailLog->mailable_type === Estimate::class, 404);

        return Estimate::with('customer', 'currency')->findOrFail($emailLog->mailable_id);
    }

    /** Por qué no se enseñan los botones, o null si se pueden usar. */
    private function motivoSinBotones(EmailLog $emailLog, Estimate $p): ?string
    {
        if ($p->respuesta_at || in_array($p->status, [Estimate::STATUS_ACCEPTED, Estimate::STATUS_REJECTED], true)
            || $p->billing_status === EstadoFacturacion::FACTURADO) {
            return 'respondido';
        }
        if ($emailLog->isExpired()) {
            return 'enlace_caducado';
        }
        if ($p->status === Estimate::STATUS_EXPIRED || ($p->expiry_date && Carbon::parse($p->expiry_date)->endOfDay()->isPast())) {
            return 'caducado';
        }

        return null;
    }

    private function pagina(EmailLog $emailLog, Estimate $p)
    {
        $empresa = Company::find($p->company_id);
        $formato = CompanySetting::getSetting('carbon_date_format', $p->company_id) ?: 'd/m/Y';

        return view('presupuesto.publico', [
            'p' => $p,
            'empresa' => $empresa,
            'logo' => $empresa?->logo,
            'total' => number_format($p->total / 100, 2, ',', '.').' '.($p->currency?->symbol ?? '€'),
            'fecha' => Carbon::parse($p->estimate_date)->translatedFormat($formato),
            'validez' => $p->expiry_date ? Carbon::parse($p->expiry_date)->translatedFormat($formato) : null,
            'pdf' => url('/customer/estimates/view/'.$emailLog->token),
            'accion' => url('/presupuesto/'.$emailLog->token),
            'motivo' => $this->motivoSinBotones($emailLog, $p),
            'respondidoEl' => $p->respuesta_at ? Carbon::parse($p->respuesta_at)->timezone('Europe/Madrid')->translatedFormat($formato) : null,
        ]);
    }

    private function avisarEmpresa(Estimate $p, bool $acepta): void
    {
        $para = RemitenteVerificado::correoDeLaEmpresa();
        if (! $para) {
            Log::info('Presupuesto respondido sin correo de empresa al que avisar', ['estimate' => $p->id]);

            return;
        }

        $cliente = $p->customer?->name ?: 'El cliente';
        $verbo = $acepta ? 'aceptado' : 'rechazado';
        $cuerpo = CorreoOnfactu::p(e($cliente).' ha '.$verbo.' el presupuesto <strong>'.e($p->estimate_number).'</strong> desde el enlace del correo.');
        if ($p->respuesta_nombre) {
            $cuerpo .= CorreoOnfactu::p('Lo ha hecho: '.e($p->respuesta_nombre).'.');
        }
        if ($p->respuesta_comentario) {
            $cuerpo .= CorreoOnfactu::p('Comentario: '.nl2br(e($p->respuesta_comentario)));
        }

        try {
            Mail::html(CorreoOnfactu::html([
                'titulo' => 'Presupuesto '.$verbo,
                'cuerpo' => $cuerpo,
                'boton_texto' => 'Ver el presupuesto',
                'boton_url' => url('/admin/estimates/'.$p->id.'/view'),
                'nota_pie' => 'Recibes este aviso porque es el correo de tu empresa en Onfactu.',
                'logo' => Company::find($p->company_id)?->logo ?: CorreoOnfactu::LOGO_GESTORIA,
            ]), function ($m) use ($para, $p, $verbo) {
                $m->to($para)->subject('Presupuesto '.$p->estimate_number.' '.$verbo);
            });
        } catch (\Throwable $e) {
            Log::warning('Aviso de presupuesto respondido no enviado', ['estimate' => $p->id, 'error' => $e->getMessage()]);
        }
    }
}
