<?php

namespace App\Services\Demo;

use App\Models\DeliveryNote;
use App\Models\Estimate;
use App\Models\ProformaInvoice;
use Illuminate\Support\Facades\DB;

/**
 * Onfactu v.1.14.6 — La recurrente, el presupuesto, la proforma y el albarán
 * de los datos de prueba de demos (separado de EscenarioPruebas en la v.1.15.0).
 */
class ComercialPruebas
{
    public function __construct(private EscenarioPruebas $e, private ContextoDemo $c)
    {
    }

    /** Mantenimiento mensual a Beta. Empieza el mes que viene: no genera nada hasta entonces. */
    public function crearRecurrente(): void
    {
        $calc = $this->c->calcular($this->e->lineas([['mantenimiento', 1]]), ['iva21']);
        $inicio = $this->e->hoy()->copy()->startOfMonth()->addMonthNoOverflow();
        $f = $this->e->hora($this->e->hoy());

        $id = (int) DB::table('recurring_invoices')->insertGetId([
            'starts_at' => $inicio->toDateTimeString(), 'send_automatically' => false, 'auto_approve' => false,
            'customer_id' => $this->e->cliente('beta'), 'company_id' => $this->c->empresa, 'status' => 'ACTIVE',
            'next_invoice_at' => $inicio->toDateTimeString(), 'creator_id' => $this->c->usuario,
            'frequency' => '0 0 1 * *', 'limit_by' => 'NONE', 'currency_id' => $this->c->moneda,
            'exchange_rate' => 1, 'tax_per_item' => 'NO', 'discount_per_item' => 'NO',
            'notes' => 'Cuota mensual del mantenimiento.', 'discount_type' => 'fixed', 'discount' => 0,
            'discount_val' => 0, 'sub_total' => $calc['sub_total'], 'total' => $calc['total'], 'tax' => $calc['tax'],
            'template_name' => $this->c->plantillaFactura, 'due_amount' => $calc['total'],
            'payment_method_id' => $this->e->formaPago(), 'created_at' => $f, 'updated_at' => $f,
        ]);
        $this->c->insertarLineas('invoice_items', 'recurring_invoice_id', $id, $calc['lineas'], $f);
        $this->c->insertarImpuestos('recurring_invoice_id', $id, $calc['impuestos'], $f);
    }

    /** Un presupuesto enviado, una proforma en borrador y un albarán entregado, de este mes. */
    public function crearComerciales(): void
    {
        $dia = $this->e->hoy()->copy();
        $f = $this->e->hora($dia);

        // Presupuesto a Beta: 10 horas
        $calc = $this->c->calcular($this->e->lineas([['consultoria', 10]]), ['iva21']);
        $cliente = $this->e->cliente('beta');
        [$numero, $seq, $seqCliente] = $this->e->numero(Estimate::class, 'estimates', 'PRE', $cliente);
        $id = (int) DB::table('estimates')->insertGetId($this->c->importes($calc) + [
            'estimate_date' => $dia->toDateString(), 'expiry_date' => $dia->copy()->addDays(15)->toDateString(),
            'estimate_number' => $numero, 'sequence_number' => $seq, 'customer_sequence_number' => $seqCliente,
            'status' => Estimate::STATUS_SENT, 'template_name' => $this->c->plantillaPresupuesto,
            'customer_id' => $cliente, 'payment_method_id' => $this->e->formaPago(), 'created_at' => $f, 'updated_at' => $f,
        ]);
        $this->c->insertarLineas('estimate_items', 'estimate_id', $id, $calc['lineas'], $f);
        $this->c->insertarImpuestos('estimate_id', $id, $calc['impuestos'], $f);
        $this->c->hash(Estimate::class, 'estimates', $id);

        // Proforma a Carmen, en borrador: sin número, como las de pantalla
        $calc = $this->c->calcular($this->e->lineas([['web', 1]]), ['iva21']);
        $id = (int) DB::table('proforma_invoices')->insertGetId($this->c->importes($calc) + [
            'proforma_invoice_date' => $dia->toDateString(), 'expiry_date' => $dia->copy()->addDays(15)->toDateString(),
            'proforma_invoice_number' => null, 'sequence_number' => null, 'customer_sequence_number' => null,
            'status' => ProformaInvoice::STATUS_DRAFT, 'template_name' => $this->c->plantillaFactura,
            'customer_id' => $this->e->cliente('carmen'), 'sent' => false, 'viewed' => false,
            'payment_method_id' => $this->e->formaPago(), 'created_at' => $f, 'updated_at' => $f,
        ]);
        $this->c->insertarLineas('proforma_invoice_items', 'proforma_invoice_id', $id, $calc['lineas'], $f);
        $this->c->insertarImpuestos('proforma_invoice_id', $id, $calc['impuestos'], $f);
        $this->c->hash(ProformaInvoice::class, 'proforma_invoices', $id);

        // Albarán a Alfa, entregado: 3 horas
        $calc = $this->c->calcular($this->e->lineas([['consultoria', 3]]), ['iva21']);
        $cliente = $this->e->cliente('alfa');
        [$numero, $seq, $seqCliente] = $this->e->numero(DeliveryNote::class, 'delivery_notes', 'ALB', $cliente);
        $id = (int) DB::table('delivery_notes')->insertGetId($this->c->importes($calc) + [
            'delivery_note_date' => $dia->toDateString(), 'delivery_date' => $dia->toDateString(),
            'delivery_note_number' => $numero, 'sequence_number' => $seq, 'customer_sequence_number' => $seqCliente,
            'status' => DeliveryNote::STATUS_DELIVERED, 'show_prices' => true,
            'template_name' => $this->c->plantillaFactura, 'customer_id' => $cliente, 'sent' => true, 'viewed' => false,
            'payment_method_id' => $this->e->formaPago(), 'created_at' => $f, 'updated_at' => $f,
        ]);
        $this->c->insertarLineas('delivery_note_items', 'delivery_note_id', $id, $calc['lineas'], $f);
        $this->c->insertarImpuestos('delivery_note_id', $id, $calc['impuestos'], $f);
        $this->c->hash(DeliveryNote::class, 'delivery_notes', $id);
    }
}
