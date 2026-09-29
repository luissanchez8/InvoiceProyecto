<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Onfactu v.1.15.0 — Tipos de IVA de los gastos y cálculo del desglose.
 *
 * Es una lista fija del programa, no los tipos de impuesto de cada instancia:
 * esos están duplicados y cambian de una instancia a otra, y el informe de
 * impuestos no cuadraría. Los importes van en céntimos, como en todo Onfactu.
 *
 * Cómo se calcula un gasto:
 *   - cada línea: cuota = base x porcentaje, redondeada al céntimo;
 *   - la retención se calcula sobre la suma de las bases;
 *   - total a pagar = bases + cuotas - retención, SIN las cuotas de las líneas
 *     con autoliquidación: la factura de Google o de Meta llega sin IVA, y ese
 *     IVA no se paga al proveedor, se declara en ventas y en gastos a la vez.
 *
 * El servidor siempre recalcula con esta clase. Lo que llega del formulario
 * solo aporta los tipos, las bases y la casilla de no deducible.
 */
class IvaGastos
{
    /** clave => [nombre, porcentaje, autoliquidación] */
    public const TIPOS = [
        'iva21'   => ['IVA 21 %', 21, false],
        'iva10'   => ['IVA 10 %', 10, false],
        'iva5'    => ['IVA 5 %', 5, false],
        'iva4'    => ['IVA 4 %', 4, false],
        'iva2'    => ['IVA 2 %', 2, false],
        'iva0'    => ['IVA 0 %', 0, false],
        'exento'  => ['Exento o no sujeto', 0, false],
        'intra21' => ['Intracomunitaria 21 % (autoliquidación)', 21, true],
        'intra10' => ['Intracomunitaria 10 % (autoliquidación)', 10, true],
        'intra4'  => ['Intracomunitaria 4 % (autoliquidación)', 4, true],
    ];

    public const RETENCIONES = [0, 7, 15, 19];

    public const MAX_LINEAS = 10;

    public static function nombre(string $tipo): string
    {
        return self::TIPOS[$tipo][0] ?? $tipo;
    }

    /** Para el formulario: la lista en el orden en que se enseña. */
    public static function catalogo(): array
    {
        $lista = [];
        foreach (self::TIPOS as $clave => [$nombre, $porcentaje, $autoliquidacion]) {
            $lista[] = compact('clave', 'nombre', 'porcentaje', 'autoliquidacion');
        }

        return ['tipos' => $lista, 'retenciones' => self::RETENCIONES];
    }

    /**
     * Valida y calcula el desglose.
     *
     * @param  array  $lineas  [['tipo' => 'iva21', 'base' => 10000, 'deducible' => true], ...]
     * @return array{lineas: array, base_imponible: int, cuota_iva: int, cuota_deducible: int,
     *               cuota_autoliquidada: int, retencion_porcentaje: float, retencion: int, total: int}
     */
    public static function calcular(array $lineas, $retencionPorcentaje = 0): array
    {
        if (count($lineas) === 0) {
            self::error('Añade al menos un tipo de IVA.');
        }
        if (count($lineas) > self::MAX_LINEAS) {
            self::error('Un gasto admite como mucho '.self::MAX_LINEAS.' tipos de IVA.');
        }

        $retencionPorcentaje = (float) ($retencionPorcentaje ?: 0);
        if (! in_array((int) $retencionPorcentaje, self::RETENCIONES, true) || fmod($retencionPorcentaje, 1) != 0) {
            self::error('La retención tiene que ser del 0, 7, 15 o 19 %.');
        }

        $salida = [];
        $base = $cuota = $deducible = $autoliquidada = 0;

        foreach (array_values($lineas) as $n => $l) {
            $tipo = (string) ($l['tipo'] ?? '');
            if (! isset(self::TIPOS[$tipo])) {
                self::error('Tipo de IVA no válido.');
            }
            if (! isset($l['base']) || ! is_numeric($l['base'])) {
                self::error('Falta la base de '.self::nombre($tipo).'.');
            }

            [, $porcentaje, $esAutoliquidacion] = self::TIPOS[$tipo];
            $b = (int) round((float) $l['base']);
            if ($b < 0) {
                self::error('La base no puede ser negativa.');
            }
            $c = (int) round($b * $porcentaje / 100);
            $esDeducible = filter_var($l['deducible'] ?? true, FILTER_VALIDATE_BOOLEAN);

            $base += $b;
            if ($esAutoliquidacion) {
                $autoliquidada += $c;
            } else {
                $cuota += $c;
            }
            if ($esDeducible) {
                $deducible += $c;
            }

            $salida[] = [
                'tipo' => $tipo, 'porcentaje' => $porcentaje, 'base' => $b, 'cuota' => $c,
                'deducible' => $esDeducible, 'autoliquidacion' => $esAutoliquidacion, 'orden' => $n,
            ];
        }

        $retencion = (int) round($base * $retencionPorcentaje / 100);
        $total = $base + $cuota - $retencion;

        if ($total <= 0) {
            self::error('El total del gasto tiene que ser mayor que cero.');
        }

        return [
            'lineas' => $salida,
            'base_imponible' => $base,
            'cuota_iva' => $cuota,
            'cuota_deducible' => $deducible,
            'cuota_autoliquidada' => $autoliquidada,
            'retencion_porcentaje' => $retencionPorcentaje,
            'retencion' => $retencion,
            'total' => $total,
        ];
    }

    /** Base a partir del total de una línea con IVA incluido (el camino inverso del formulario). */
    public static function baseDesdeTotal(int $total, string $tipo): int
    {
        [, $porcentaje, $esAutoliquidacion] = self::TIPOS[$tipo] ?? [null, 0, false];

        return $esAutoliquidacion ? $total : (int) round($total * 100 / (100 + $porcentaje));
    }

    private static function error(string $mensaje): void
    {
        throw ValidationException::withMessages(['lineas_iva' => $mensaje]);
    }
}
