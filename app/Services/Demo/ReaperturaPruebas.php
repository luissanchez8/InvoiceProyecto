<?php

namespace App\Services\Demo;

use App\Models\ClosedMonth;
use App\Models\Expense;
use App\Models\User;
use App\Services\ReaperturaMes;

/**
 * Onfactu v.1.16.0 — Caso de prueba de la fase 5 en demos: M-2 se abre para
 * corregirlo y se vuelve a cerrar, como lo haría el cliente.
 *
 * El alquiler de M-2 se crea con el número de factura mal escrito
 * (ALQ-2026-7, GastosPruebas); con el mes abierto se corrige a ALQ-2026-07.
 * Así quedan un registro de cambios, el aviso a la gestoría y la corrección en
 * el portal, sin cambiar ninguna cifra.
 *
 * Va fuera de la transacción de preparar(), después de entregar los meses:
 * escribe en la central de gestorías.
 */
class ReaperturaPruebas
{
    public static function crear(EscenarioPruebas $e, int $empresa, int $usuarioId): string
    {
        $dia = $e->dia(2, 1);
        $mes = ClosedMonth::where('company_id', $empresa)->where('year', $dia->year)->where('month', $dia->month)->first();
        $usuario = User::find($usuarioId);
        if (! $mes || ! $usuario) {
            return 'No se ha podido preparar la corrección de M-2.';
        }

        ReaperturaMes::abrir($mes, $usuario->id, (string) $usuario->name, 'El número de la factura del alquiler estaba mal escrito');

        $alquiler = Expense::where('company_id', $empresa)->whereDate('expense_date', $dia->toDateString())
            ->where('numero_factura', 'ALQ-'.$dia->format('Y-n'))->first();
        $alquiler?->update(['numero_factura' => 'ALQ-'.$dia->format('Y-m')]);

        ReaperturaMes::cerrar($mes->fresh(), $usuario->id);

        return 'M-2 abierto y vuelto a cerrar para corregir el número del alquiler (la gestoría recibe dos avisos más).';
    }
}
