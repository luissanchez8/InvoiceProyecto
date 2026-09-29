<?php

namespace App\Observers;

use App\Models\ClosedMonth;
use App\Models\ClosedMonthChange;
use App\Models\DeliveryNote;
use App\Models\Estimate;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Onfactu v.1.16.0 — Registro de lo que se cambia en un mes cerrado mientras
 * está abierto para corregir.
 *
 * Se engancha a los documentos que se pueden tocar con el mes abierto (gastos,
 * cobros, presupuestos, proformas y albaranes; las facturas nunca). Cada vez
 * que uno se crea, se edita o se borra con fecha en un mes abierto (o sale de
 * él al cambiarle la fecha), deja una línea en closed_month_changes. Es lo que
 * se le cuenta a la gestoría al volver a cerrar el mes.
 *
 * Los cambios hechos con consultas masivas (sin cargar el modelo) no pasan por
 * aquí; por eso la gestión de proveedores no toca los meses cerrados.
 */
class CambiosMesAbierto
{
    /** modelo => [tipo, nombre, columna de fecha, columna de importe, columna de número] */
    private const DOCUMENTOS = [
        Expense::class         => ['gasto', 'Gasto', 'expense_date', 'amount', 'numero_factura'],
        Payment::class         => ['cobro', 'Cobro', 'payment_date', 'amount', 'payment_number'],
        Estimate::class        => ['presupuesto', 'Presupuesto', 'estimate_date', 'total', 'estimate_number'],
        ProformaInvoice::class => ['proforma', 'Proforma', 'proforma_invoice_date', 'total', 'proforma_invoice_number'],
        DeliveryNote::class    => ['albaran', 'Albarán', 'delivery_note_date', 'total', 'delivery_note_number'],
    ];

    public static function registrar(): void
    {
        foreach (array_keys(self::DOCUMENTOS) as $modelo) {
            $modelo::observe(self::class);
        }
    }

    public function created(Model $m): void
    {
        $this->anotar($m, 'creado', $m->getAttribute($this->cfg($m)[2]));
    }

    public function updated(Model $m): void
    {
        [, , $fecha, $importe] = $this->cfg($m);
        $cambios = array_intersect(array_keys($m->getChanges()), [$fecha, $importe, 'notes', 'proveedor_nombre',
            'proveedor_nif', 'numero_factura', 'expense_category_id', 'customer_id', 'con_desglose', 'retencion']);
        if (! $cambios) {
            return; // nada que interese a la gestoría (p. ej. solo updated_at)
        }

        // Cuenta en el mes de antes y en el de ahora, por si se ha movido
        $antes = $m->getOriginal($fecha);
        $ahora = $m->getAttribute($fecha);
        $this->anotar($m, 'editado', $ahora, $this->detalle($m, $cambios));
        if (ClosedMonth::toPeriod($antes) !== ClosedMonth::toPeriod($ahora)) {
            $this->anotar($m, 'editado', $antes, 'se ha movido al '.Carbon::parse($ahora)->format('d/m/Y'));
        }
    }

    public function deleted(Model $m): void
    {
        $this->anotar($m, 'borrado', $m->getAttribute($this->cfg($m)[2]));
    }

    // ─────────────────────────────────────────────────────────────

    private function anotar(Model $m, string $accion, $fecha, ?string $detalle = null): void
    {
        $empresa = (int) $m->getAttribute('company_id');
        if (! $fecha || ! $empresa || ! ClosedMonth::isReopened($empresa, $fecha)) {
            return;
        }

        $f = Carbon::parse($fecha);
        $mes = ClosedMonth::where('company_id', $empresa)->where('year', $f->year)->where('month', $f->month)->first();
        if (! $mes) {
            return;
        }

        [$tipo] = $this->cfg($m);
        ClosedMonthChange::create([
            'company_id' => $empresa, 'closed_month_id' => $mes->id, 'year' => $mes->year, 'month' => $mes->month,
            'reapertura' => $mes->reopen_count, 'user_id' => auth()->id() ?? $mes->reopened_by,
            'accion' => $accion, 'tipo' => $tipo, 'documento_id' => $m->getKey(),
            'descripcion' => mb_substr($this->nombre($m).' '.$accion.($detalle ? ': '.$detalle : ''), 0, 500),
        ]);
    }

    /** "Gasto T-118 de Restaurante El Puerto (55,00 €)" */
    private function nombre(Model $m): string
    {
        [, $nombre, , $importe, $numero] = $this->cfg($m);
        $texto = $nombre;
        $num = $m->getOriginal($numero) ?: $m->getAttribute($numero);
        if ($num) {
            $texto .= ' '.$num;
        }
        if ($m instanceof Expense && $m->proveedor_nombre) {
            $texto .= ' de '.$m->proveedor_nombre;
        }

        return $texto.' ('.self::euros($m->getOriginal($importe) ?? $m->getAttribute($importe)).')';
    }

    private function detalle(Model $m, array $cambios): string
    {
        [, , $fecha, $importe, $numero] = $this->cfg($m);
        $partes = [];
        if (in_array($numero, $cambios, true)) {
            $partes[] = 'número '.($m->getOriginal($numero) ?: '(vacío)').' → '.($m->getAttribute($numero) ?: '(vacío)');
        }
        if (in_array('proveedor_nombre', $cambios, true)) {
            $partes[] = 'proveedor '.($m->getOriginal('proveedor_nombre') ?: '(vacío)').' → '.($m->getAttribute('proveedor_nombre') ?: '(vacío)');
        }
        if (in_array($importe, $cambios, true)) {
            $partes[] = 'importe '.self::euros($m->getOriginal($importe)).' → '.self::euros($m->getAttribute($importe));
        }
        if (in_array($fecha, $cambios, true)) {
            $partes[] = 'fecha '.Carbon::parse($m->getOriginal($fecha))->format('d/m/Y').' → '.Carbon::parse($m->getAttribute($fecha))->format('d/m/Y');
        }
        $otros = array_diff($cambios, [$importe, $fecha, $numero, 'proveedor_nombre']);
        if ($otros) {
            $partes[] = 'otros datos';
        }

        return implode(', ', $partes);
    }

    private function cfg(Model $m): array
    {
        return self::DOCUMENTOS[get_class($m)];
    }

    public static function euros($centimos): string
    {
        return number_format(((int) $centimos) / 100, 2, ',', '.').' €';
    }
}
