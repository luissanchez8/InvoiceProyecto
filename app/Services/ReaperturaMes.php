<?php

namespace App\Services;

use App\Models\ClosedMonth;
use App\Models\ClosedMonthChange;
use App\Observers\CambiosMesAbierto;

/**
 * Onfactu v.1.16.0 — Abrir un mes cerrado para corregirlo y volver a cerrarlo
 * (fase 5 del plan).
 *
 * Reglas, decididas el 29/09/2026 tras revisar Holded y el Real Decreto
 * 1007/2023 (que solo regula las facturas emitidas):
 *   - Solo lo abre el propietario de la cuenta, con un motivo.
 *   - Abierto, se pueden cambiar gastos, cobros, presupuestos, proformas y
 *     albaranes de ese mes. Las facturas nunca: se corrigen con rectificativa
 *     (ClosedMonth::isLocked y el middleware CheckMonthClosed).
 *   - Se vuelve a cerrar con un botón o solo, a las HORAS horas.
 *   - La gestoría no aprueba nada: se le avisa al abrir y al cerrar, con el
 *     motivo, los cambios y los totales de antes y de después.
 *   - Cada cambio queda en closed_month_changes (CambiosMesAbierto).
 */
class ReaperturaMes
{
    public const HORAS = 24;

    /** Totales que pueden cambiar con el mes abierto (las facturas no cambian). */
    private const COMPARAR = [
        'gastos'           => ['Gastos', false],
        'importe_gastos'   => ['Importe de los gastos', true],
        'gastos_neto'      => ['Neto de los gastos', true],
        'gastos_iva'       => ['IVA de los gastos', true],
        'gastos_iva_deducible' => ['IVA deducible', true],
        'gastos_retencion' => ['Retenciones de los gastos', true],
    ];

    public static function abrir(ClosedMonth $mes, int $usuarioId, string $usuario, string $motivo): void
    {
        $mes->update([
            'reopened_at'       => now(),
            'reopened_by'       => $usuarioId,
            'reopen_reason'     => $motivo,
            'reopen_expires_at' => now()->addHours(self::HORAS),
            'reopen_count'      => $mes->reopen_count + 1,
        ]);
        ClosedMonth::forgetCache($mes->company_id);

        self::anotar($mes, $usuarioId, 'abierto', 'Abierto por '.$usuario.'. Motivo: '.$motivo);

        GestoriaService::reapertura($mes->year, $mes->month, $mes->reopen_count, [
            'motivo' => $motivo, 'usuario' => $usuario, 'abierta_at' => self::local($mes->reopened_at),
            'expira_at' => self::local($mes->reopen_expires_at), 'cerrada_at' => null, 'cambios' => null,
            'totales_antes' => $mes->totals ?? [], 'totales_despues' => null,
        ]);

        $periodo = CierreMes::nombreMes($mes->month).' de '.$mes->year;
        GestoriaService::avisar('Mes abierto para corregir: '.$periodo, [
            'titulo' => GestoriaService::empresa().' ha abierto '.$periodo,
            'cuerpo' => CorreoOnfactu::p('Lo ha abierto para corregirlo. Motivo: <strong>'.e($motivo).'</strong>.')
                .CorreoOnfactu::p('Mientras esté abierto puede cambiar los gastos y cobros de ese mes, pero no las facturas. '
                    .'Se cerrará como muy tarde el '.$mes->reopen_expires_at->timezone('Europe/Madrid')->format('d/m/Y \a \l\a\s H:i')
                    .', y entonces recibirás los cambios.'),
            'boton_texto' => 'Abrir el portal',
            'boton_url' => 'https://gestoria.onfactu.com/',
        ]);
    }

    /**
     * Vuelve a cerrar el mes: recalcula los totales, los entrega y avisa a la
     * gestoría con los cambios. Devuelve lo mismo que vistaPrevia().
     */
    public static function cerrar(ClosedMonth $mes, ?int $usuarioId, bool $automatico = false): array
    {
        if (! $mes->reopened_at) {
            return self::vistaPrevia($mes);
        }

        $resumen = self::vistaPrevia($mes);
        $numero = $mes->reopen_count;

        $mes->update([
            'totals' => $resumen['despues'],
            'reopened_at' => null, 'reopened_by' => null, 'reopen_reason' => null, 'reopen_expires_at' => null,
        ]);
        ClosedMonth::forgetCache($mes->company_id);

        self::anotar($mes, $usuarioId, 'cerrado', $automatico
            ? 'Cerrado automáticamente a las '.self::HORAS.' horas'
            : 'Cerrado de nuevo');

        // Entrega de los totales nuevos. Si la central no responde, se marca
        // como no entregado y se reintenta solo (CierreMes::reenviarPendientes).
        $entregado = GestoriaService::actualizarTotales($mes->year, $mes->month, $resumen['despues']);
        if (! $entregado) {
            $mes->update(['sent_status' => 'failed', 'sent_error' => 'No se pudieron entregar los totales tras corregir el mes']);
        }
        GestoriaService::reapertura($mes->year, $mes->month, $numero, [
            'cerrada_at' => self::local(now()), 'cambios' => $resumen['cambios'], 'totales_despues' => $resumen['despues'],
        ]);

        $periodo = CierreMes::nombreMes($mes->month).' de '.$mes->year;
        $lista = $resumen['cambios']
            ? '<ul>'.implode('', array_map(fn ($c) => '<li>'.e($c).'</li>', $resumen['cambios'])).'</ul>'
            : CorreoOnfactu::p('No ha cambiado nada.');
        $difs = implode('', array_map(fn ($d) => '<li>'.e($d['etiqueta'].': '.$d['antes'].' → '.$d['despues']).'</li>', $resumen['diferencias']));

        GestoriaService::avisar('Mes corregido: '.$periodo, [
            'titulo' => GestoriaService::empresa().' ha vuelto a cerrar '.$periodo,
            'cuerpo' => CorreoOnfactu::p('Cambios hechos mientras estaba abierto:').$lista
                .($difs ? CorreoOnfactu::p('Totales que han cambiado:').'<ul>'.$difs.'</ul>' : '')
                .CorreoOnfactu::p('En el portal ya tienes el mes con los datos nuevos.'),
            'boton_texto' => 'Abrir el portal',
            'boton_url' => 'https://gestoria.onfactu.com/',
        ]);

        return $resumen;
    }

    /**
     * Lo que se ha cambiado en la reapertura en curso y cómo quedarían los
     * totales al cerrar. Lo enseña la ventana de volver a cerrar.
     */
    public static function vistaPrevia(ClosedMonth $mes): array
    {
        $despues = CierreMes::resumen($mes->company_id, $mes->year, $mes->month)['totales'];

        $cambios = ClosedMonthChange::where('closed_month_id', $mes->id)
            ->where('reapertura', $mes->reopen_count)
            ->whereIn('accion', ['creado', 'editado', 'borrado'])
            ->orderBy('id')->pluck('descripcion')->all();

        return [
            'year' => $mes->year,
            'month' => $mes->month,
            'nombre' => CierreMes::nombreMes($mes->month, true).' de '.$mes->year,
            'motivo' => $mes->reopen_reason,
            'hasta' => $mes->reopen_expires_at?->toIso8601String(),
            'cambios' => $cambios,
            'antes' => $mes->totals ?? [],
            'despues' => $despues,
            'diferencias' => self::diferencias($mes->totals ?? [], $despues),
        ];
    }

    /** Los meses abiertos que ya han pasado su plazo se cierran solos. */
    public static function cerrarCaducados(?int $empresa = null): int
    {
        $caducados = ClosedMonth::whereNotNull('reopened_at')
            ->where('reopen_expires_at', '<=', now())
            ->when($empresa, fn ($q) => $q->where('company_id', $empresa))
            ->get();

        foreach ($caducados as $mes) {
            self::cerrar($mes, null, true);
        }

        return $caducados->count();
    }

    public static function diferencias(array $antes, array $despues): array
    {
        $out = [];
        foreach (self::COMPARAR as $clave => [$etiqueta, $dinero]) {
            $a = (int) ($antes[$clave] ?? 0);
            $d = (int) ($despues[$clave] ?? 0);
            if ($a !== $d) {
                $fmt = fn ($v) => $dinero ? CambiosMesAbierto::euros($v) : (string) $v;
                $out[] = ['etiqueta' => $etiqueta, 'antes' => $fmt($a), 'despues' => $fmt($d)];
            }
        }

        return $out;
    }

    /** La central guarda la hora de Madrid, que es la que enseña el portal. */
    private static function local($fecha): string
    {
        return $fecha->copy()->timezone('Europe/Madrid')->format('Y-m-d H:i:s');
    }

    private static function anotar(ClosedMonth $mes, ?int $usuarioId, string $accion, string $texto): void
    {
        ClosedMonthChange::create([
            'company_id' => $mes->company_id, 'closed_month_id' => $mes->id, 'year' => $mes->year,
            'month' => $mes->month, 'reapertura' => $mes->reopen_count, 'user_id' => $usuarioId,
            'accion' => $accion, 'descripcion' => mb_substr($texto, 0, 500),
        ]);
    }
}
