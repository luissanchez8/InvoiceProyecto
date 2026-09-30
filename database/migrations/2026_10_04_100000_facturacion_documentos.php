<?php

use App\Models\DeliveryNote;
use App\Models\Estimate;
use App\Models\ProformaInvoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Onfactu v.1.17.0 — Fase 3: presupuestos, proformas y albaranes.
 *
 *   documentos_facturados: qué factura sale de qué documento. Una fila por
 *   documento y factura: la factura final ('final') o una de anticipo
 *   ('anticipo'). Varios albaranes en una factura son varias filas con la
 *   misma factura.
 *
 *   billing_status en los tres documentos: PENDIENTE, ANTICIPO o FACTURADO.
 *   Lo mantiene App\Services\Facturacion\EstadoFacturacion.
 *
 *   estimate_id en proformas y albaranes: el presupuesto del que salieron.
 *
 *   respuesta_* en presupuestos: la aceptación o el rechazo del cliente
 *   desde el enlace del correo.
 *
 * Datos que ya existían:
 *   - Las proformas convertidas (converted_invoice_id) quedan enlazadas a su
 *     factura y como facturadas.
 *   - Los documentos sin número (los borradores de antes) se numeran por
 *     orden de fecha: desde ahora se numeran al crearlos.
 *   - Los tres pasan a la plantilla universal (invoice4).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('documentos_facturados')) {
            Schema::create('documentos_facturados', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id');
                $table->integer('invoice_id');
                $table->string('documento_tipo', 20);        // estimate, proforma, delivery_note
                $table->unsignedBigInteger('documento_id');
                $table->string('tipo', 10);                  // final, anticipo
                $table->bigInteger('importe')->default(0);   // total de la factura al enlazarla
                $table->timestamp('created_at')->nullable();

                $table->index(['documento_tipo', 'documento_id']);
                $table->index('invoice_id');
                $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            });
        }

        foreach (['estimates', 'proforma_invoices', 'delivery_notes'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                if (! Schema::hasColumn($tabla, 'billing_status')) {
                    $table->string('billing_status', 20)->default('PENDIENTE');
                }
            });
        }

        foreach (['proforma_invoices', 'delivery_notes'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                if (! Schema::hasColumn($tabla, 'estimate_id')) {
                    $table->integer('estimate_id')->nullable();
                    $table->foreign('estimate_id')->references('id')->on('estimates')->nullOnDelete();
                }
            });
        }

        Schema::table('estimates', function (Blueprint $table) {
            if (! Schema::hasColumn('estimates', 'respuesta_at')) {
                $table->timestamp('respuesta_at')->nullable();
                $table->string('respuesta_nombre', 150)->nullable();
                $table->string('respuesta_comentario', 1000)->nullable();
            }
        });

        $this->enlazarProformasConvertidas();

        foreach (['estimates', 'proforma_invoices', 'delivery_notes'] as $tabla) {
            DB::table($tabla)->where(fn ($q) => $q->whereNull('template_name')->orWhere('template_name', '!=', 'invoice4'))
                ->update(['template_name' => 'invoice4']);
        }

        $this->numerarSinNumero(Estimate::class, 'estimate_number', 'estimate_date');
        $this->numerarSinNumero(ProformaInvoice::class, 'proforma_invoice_number', 'proforma_invoice_date');
        $this->numerarSinNumero(DeliveryNote::class, 'delivery_note_number', 'delivery_note_date');
    }

    private function enlazarProformasConvertidas(): void
    {
        $convertidas = DB::table('proforma_invoices as p')
            ->join('invoices as i', 'i.id', '=', 'p.converted_invoice_id')
            ->whereNotNull('p.converted_invoice_id')
            ->select('p.id', 'p.company_id', 'p.converted_invoice_id', 'i.total')
            ->get();

        foreach ($convertidas as $p) {
            $existe = DB::table('documentos_facturados')
                ->where('documento_tipo', 'proforma')->where('documento_id', $p->id)
                ->where('invoice_id', $p->converted_invoice_id)->exists();
            if (! $existe) {
                DB::table('documentos_facturados')->insert([
                    'company_id' => $p->company_id, 'invoice_id' => $p->converted_invoice_id,
                    'documento_tipo' => 'proforma', 'documento_id' => $p->id, 'tipo' => 'final',
                    'importe' => (int) $p->total, 'created_at' => now(),
                ]);
            }
            DB::table('proforma_invoices')->where('id', $p->id)->update(['billing_status' => 'FACTURADO']);
        }
    }

    private function numerarSinNumero(string $modelo, string $numero, string $fecha): void
    {
        $modelo::query()
            ->where(fn ($q) => $q->whereNull($numero)->orWhere($numero, ''))
            ->whereNotNull('company_id')
            ->orderBy('company_id')->orderBy($fecha)->orderBy('id')
            ->get()
            ->each(fn ($doc) => $doc->assignNumber());
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_facturados');
        foreach (['proforma_invoices', 'delivery_notes'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropForeign(['estimate_id']);
                $table->dropColumn('estimate_id');
            });
        }
        foreach (['estimates', 'proforma_invoices', 'delivery_notes'] as $tabla) {
            Schema::table($tabla, fn (Blueprint $table) => $table->dropColumn('billing_status'));
        }
        Schema::table('estimates', fn (Blueprint $table) => $table->dropColumn(['respuesta_at', 'respuesta_nombre', 'respuesta_comentario']));
    }
};
