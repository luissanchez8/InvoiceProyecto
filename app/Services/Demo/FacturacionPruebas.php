<?php

namespace App\Services\Demo;

use App\Models\DeliveryNote;
use App\Models\EmailLog;
use App\Models\Estimate;
use App\Services\Facturacion\ConvertirEnFactura;
use App\Services\Facturacion\FacturarAnticipo;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Onfactu v.1.17.0 — Un caso de cada cosa de la fase 3 en los datos de prueba
 * de demos (presupuestos, proformas y albaranes facturados).
 *
 *   PRE-2  Alfa    Diseño web x2   2.420  aceptado por el cliente desde el
 *                                         enlace, con un anticipo del 30 %
 *                                         (726) en borrador          ANTICIPO
 *   PRE-3  Carmen  Consultoría x4    484  convertido en factura, que queda
 *                                         en borrador                FACTURADO
 *   ALB-2  Alfa    Consultoría x2    242  entregado, para facturarlo junto
 *                                         con ALB-1 (mismo cliente)
 *
 * Las facturas se quedan en borrador: no cambian ninguna cifra de ventas,
 * cobros ni cierres de las que ya había. Solo sube a 3 el número de
 * borradores de la lista de facturas.
 *
 * Además deja un enlace de aceptación para PRE-1 (el que está enviado), como
 * si se le hubiera mandado al cliente: lo enseña la orden al terminar.
 */
class FacturacionPruebas
{
    public ?string $enlacePresupuesto = null;

    public function __construct(private EscenarioPruebas $e, private ContextoDemo $c)
    {
    }

    public function crear(): void
    {
        $hoy = $this->e->hoy();
        $f = $this->e->hora($hoy);

        // PRE-2: aceptado desde el enlace del correo y con un anticipo
        $pre2 = $this->presupuesto('alfa', [['web', 2]], Estimate::STATUS_ACCEPTED, [
            'respuesta_at' => $hoy->copy()->setTime(8, 0)->toDateTimeString(),
            'respuesta_nombre' => 'Responsable de compras',
            'respuesta_comentario' => 'Adelante. Os pagamos el 30 % para empezar.',
        ]);
        FacturarAnticipo::crear($pre2, FacturarAnticipo::PORCENTAJE, 30);

        // PRE-3: ya convertido en factura
        $pre3 = $this->presupuesto('carmen', [['consultoria', 4]], Estimate::STATUS_ACCEPTED);
        ConvertirEnFactura::desde([$pre3]);

        // ALB-2: para facturarlo junto con ALB-1
        $calc = $this->c->calcular($this->e->lineas([['consultoria', 2]]), ['iva21']);
        $cliente = $this->e->cliente('alfa');
        [$numero, $seq, $seqCliente] = $this->e->numero(DeliveryNote::class, 'delivery_notes', 'ALB', $cliente);
        $id = (int) DB::table('delivery_notes')->insertGetId($this->c->importes($calc) + [
            'delivery_note_date' => $hoy->toDateString(), 'delivery_date' => $hoy->toDateString(),
            'delivery_note_number' => $numero, 'sequence_number' => $seq, 'customer_sequence_number' => $seqCliente,
            'status' => DeliveryNote::STATUS_DELIVERED, 'show_prices' => true,
            'template_name' => $this->c->plantillaFactura, 'customer_id' => $cliente, 'sent' => true, 'viewed' => false,
            'payment_method_id' => $this->e->formaPago(), 'created_at' => $f, 'updated_at' => $f,
        ]);
        $this->c->insertarLineas('delivery_note_items', 'delivery_note_id', $id, $calc['lineas'], $f);
        $this->c->insertarImpuestos('delivery_note_id', $id, $calc['impuestos'], $f);
        $this->c->hash(DeliveryNote::class, 'delivery_notes', $id);

        $this->enlaceAceptacion();
    }

    private function presupuesto(string $clave, array $lineas, string $estado, array $extra = []): Estimate
    {
        $dia = $this->e->hoy();
        $f = $this->e->hora($dia);
        $calc = $this->c->calcular($this->e->lineas($lineas), ['iva21']);
        $cliente = $this->e->cliente($clave);
        [$numero, $seq, $seqCliente] = $this->e->numero(Estimate::class, 'estimates', 'PRE', $cliente);

        $id = (int) DB::table('estimates')->insertGetId($this->c->importes($calc) + $extra + [
            'estimate_date' => $dia->toDateString(), 'expiry_date' => $dia->copy()->addDays(15)->toDateString(),
            'estimate_number' => $numero, 'sequence_number' => $seq, 'customer_sequence_number' => $seqCliente,
            'status' => $estado, 'template_name' => $this->c->plantillaPresupuesto,
            'customer_id' => $cliente, 'payment_method_id' => $this->e->formaPago(), 'created_at' => $f, 'updated_at' => $f,
        ]);
        $this->c->insertarLineas('estimate_items', 'estimate_id', $id, $calc['lineas'], $f);
        $this->c->insertarImpuestos('estimate_id', $id, $calc['impuestos'], $f);
        $this->c->hash(Estimate::class, 'estimates', $id);

        return Estimate::findOrFail($id);
    }

    /** El registro de envío de PRE-1, con su token: el enlace que recibiría el cliente. */
    private function enlaceAceptacion(): void
    {
        $pre1 = Estimate::where('company_id', $this->c->empresa)->where('status', Estimate::STATUS_SENT)->orderBy('id')->first();
        if (! $pre1) {
            return;
        }

        $log = EmailLog::create([
            'from' => (string) config('mail.from.address'), 'to' => 'cliente@example.com',
            'subject' => 'Presupuesto '.$pre1->estimate_number, 'body' => 'Datos de prueba de demos',
            'mailable_type' => Estimate::class, 'mailable_id' => $pre1->id,
        ]);
        $log->token = Hashids::connection(EmailLog::class)->encode($log->id);
        $log->save();

        $this->enlacePresupuesto = url('/presupuesto/'.$log->token);
    }
}
