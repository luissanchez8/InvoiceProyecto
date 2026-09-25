<?php

namespace App\Services;

use App\Exceptions\AprobacionFacturaException;
use App\Models\ClosedMonth;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Onfactu v.1.13.0 — Aprobar una factura: el único paso para salir del borrador.
 *
 * Al aprobar:
 *  1. Se comprueba que la factura está completa y que su fecha no es de un
 *     mes cerrado.
 *  2. Se le asigna el número. Un borrador numerado de antes de la v.1.13
 *     conserva el suyo.
 *  3. Se comprueba el orden de fechas: una factura no puede tener fecha
 *     anterior a otra aprobada con número menor, ni posterior a otra con
 *     número mayor. Si falla, se puede aprobar con la fecha de hoy.
 *  4. Pasa a Aprobada y queda bloqueada: para corregirla, rectificativa.
 *
 * El envío a VeriFactu va aparte (VerifactuPublicador), después de aprobar.
 *
 * Dos aprobaciones a la vez en la misma empresa podrían llevarse el mismo
 * número, así que cada aprobación bloquea la fila de su empresa hasta acabar.
 */
class AprobarFactura
{
    public static function aprobar(Invoice $factura, bool $usarFechaHoy = false): Invoice
    {
        if ($factura->status !== Invoice::STATUS_DRAFT) {
            throw new AprobacionFacturaException('ya_aprobada', 'Esta factura ya está aprobada.');
        }

        if (! $factura->customer_id || ! $factura->items()->exists()) {
            throw new AprobacionFacturaException('incompleta', 'Para aprobar la factura necesita un cliente y al menos una línea.');
        }

        DB::transaction(function () use ($factura, $usarFechaHoy) {
            DB::table('companies')->where('id', $factura->company_id)->lockForUpdate()->first();
            $factura->refresh();

            if ($factura->status !== Invoice::STATUS_DRAFT) {
                throw new AprobacionFacturaException('ya_aprobada', 'Esta factura ya está aprobada.');
            }

            if ($usarFechaHoy) {
                self::moverAHoy($factura);
            }

            if (ClosedMonth::isClosed((int) $factura->company_id, $factura->invoice_date)) {
                throw new AprobacionFacturaException(
                    'mes_cerrado',
                    'La fecha de la factura es de un mes cerrado. Puedes aprobarla con la fecha de hoy.',
                    ['puede_usar_hoy' => true],
                );
            }

            self::numerar($factura);

            $factura->status = Invoice::STATUS_APPROVED;
            $factura->approved_at = now();
            $factura->save();
        });

        return $factura->fresh();
    }

    /**
     * Qué pasaría al aprobar, sin aprobar nada: el número que recibiría y,
     * si no se puede, el motivo. Si con la fecha de hoy sí se podría, también
     * el número que recibiría así. Lo usa el diálogo de aprobar al abrirse.
     */
    public static function previsualizar(Invoice $factura): array
    {
        $resultado = ['numero' => null, 'error' => null, 'numero_hoy' => null];

        try {
            $resultado['numero'] = self::simular(clone $factura, false);
        } catch (AprobacionFacturaException $e) {
            $resultado['error'] = [
                'codigo'         => $e->codigo,
                'mensaje'        => $e->getMessage(),
                'puede_usar_hoy' => (bool) ($e->datos['puede_usar_hoy'] ?? false),
            ];
            if ($resultado['error']['puede_usar_hoy']) {
                try {
                    $resultado['numero_hoy'] = self::simular(clone $factura, true);
                } catch (AprobacionFacturaException $e2) {
                    $resultado['error']['puede_usar_hoy'] = false;
                }
            }
        }

        return $resultado;
    }

    /** Recorre los pasos de aprobar sobre una copia, sin guardar. */
    private static function simular(Invoice $copia, bool $usarFechaHoy): string
    {
        if ($copia->status !== Invoice::STATUS_DRAFT) {
            throw new AprobacionFacturaException('ya_aprobada', 'Esta factura ya está aprobada.');
        }
        if (! $copia->customer_id || ! $copia->items()->exists()) {
            throw new AprobacionFacturaException('incompleta', 'Para aprobar la factura necesita un cliente y al menos una línea.');
        }
        if ($usarFechaHoy) {
            self::moverAHoy($copia);
        }
        if (ClosedMonth::isClosed((int) $copia->company_id, $copia->invoice_date)) {
            throw new AprobacionFacturaException(
                'mes_cerrado',
                'La fecha de la factura es de un mes cerrado. Puedes aprobarla con la fecha de hoy.',
                ['puede_usar_hoy' => true],
            );
        }
        self::numerar($copia);

        return (string) $copia->invoice_number;
    }

    /** Asigna número y serie comprobando el orden de fechas. No guarda. */
    private static function numerar(Invoice $f): void
    {
        $fecha = self::dia($f->invoice_date);

        // Borrador numerado de antes de la v.1.13: conserva su número.
        if (! empty($f->invoice_number) && $f->sequence_number) {
            self::comprobarFechas($f, (int) $f->sequence_number, $fecha);

            return;
        }

        $serie = (new SerialNumberFormatter)
            ->setModel($f)
            ->setCompany($f->company_id)
            ->setCustomer($f->customer_id)
            ->setNextNumbers();

        // El primer número libre puede ser un hueco entre facturas antiguas. Si
        // la fecha no encaja ahí, se usa el siguiente al más alto.
        $numero = (int) $serie->nextSequenceNumber;
        if (self::errorDeFechas($f, $numero, $fecha) !== null) {
            $numero = (int) Invoice::where('company_id', $f->company_id)->max('sequence_number') + 1;
        }
        self::comprobarFechas($f, $numero, $fecha);

        $serie->nextSequenceNumber = $numero;
        $f->sequence_number = $numero;
        $f->invoice_number = $serie->getNextNumber();
        if (empty($f->customer_sequence_number)) {
            $f->customer_sequence_number = $serie->nextCustomerSequenceNumber;
        }
    }

    private static function comprobarFechas(Invoice $f, int $numero, string $fecha): void
    {
        $error = self::errorDeFechas($f, $numero, $fecha);
        if ($error === null) {
            return;
        }

        $hoy = Carbon::today()->toDateString();
        throw new AprobacionFacturaException($error['codigo'], $error['mensaje'], [
            'puede_usar_hoy' => $fecha !== $hoy && self::errorDeFechas($f, $numero, $hoy) === null,
        ]);
    }

    /**
     * Compara la fecha con las facturas aprobadas de la misma serie (sin
     * rectificativas, que tienen la suya): ninguna con número menor puede
     * tener fecha posterior, ni ninguna con número mayor fecha anterior.
     */
    private static function errorDeFechas(Invoice $f, int $numero, string $fecha): ?array
    {
        $aprobadas = Invoice::where('company_id', $f->company_id)
            ->where('status', Invoice::STATUS_APPROVED)
            ->whereNull('rectifies_invoice_id')
            ->whereNotNull('sequence_number')
            ->where('id', '<>', $f->id);

        $anterior = (clone $aprobadas)->where('sequence_number', '<', $numero)
            ->whereDate('invoice_date', '>', $fecha)
            ->orderByDesc('invoice_date')->first(['invoice_number', 'invoice_date']);
        if ($anterior) {
            return [
                'codigo'  => 'fecha_anterior',
                'mensaje' => 'La fecha es anterior a la de la factura '.$anterior->invoice_number
                    .', del '.self::legible($anterior->invoice_date).'. Las facturas tienen que seguir el orden de sus fechas.',
            ];
        }

        $posterior = (clone $aprobadas)->where('sequence_number', '>', $numero)
            ->whereDate('invoice_date', '<', $fecha)
            ->orderBy('invoice_date')->first(['invoice_number', 'invoice_date']);
        if ($posterior) {
            return [
                'codigo'  => 'fecha_posterior',
                'mensaje' => 'La fecha es posterior a la de la factura '.$posterior->invoice_number
                    .', del '.self::legible($posterior->invoice_date).', que tiene un número mayor. Cambia la fecha para que encaje.',
            ];
        }

        return null;
    }

    /** Pasa la factura a hoy, conservando los días hasta el vencimiento. */
    private static function moverAHoy(Invoice $f): void
    {
        $hoy = Carbon::today();
        $dias = null;
        if ($f->invoice_date && $f->due_date) {
            $dias = Carbon::parse(self::dia($f->invoice_date))->diffInDays(Carbon::parse(self::dia($f->due_date)), false);
        }

        $f->invoice_date = $hoy->toDateString();
        if ($dias !== null && $dias >= 0) {
            $f->due_date = $hoy->copy()->addDays((int) $dias)->toDateString();
        }
    }

    private static function dia($fecha): string
    {
        return Carbon::parse($fecha)->toDateString();
    }

    private static function legible($fecha): string
    {
        return Carbon::parse($fecha)->format('d/m/Y');
    }
}
