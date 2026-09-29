<?php

namespace App\Services\Demo;

use App\Support\IvaGastos;
use Illuminate\Support\Facades\DB;

/**
 * Gastos de la demo: los fijos de cada mes y algunos puntuales.
 *
 * Desde la v.1.15.0 llevan el IVA desglosado según su categoría (ver
 * PROVEEDORES): la cuota de autónomos y la formación exentas, el tren al 10 %,
 * la publicidad como compra intracomunitaria (autoliquidación), la asesoría
 * con retención del 15 % y lo demás al 21 %. El importe del catálogo es el
 * total con IVA, y la base se saca de él.
 */
class GastosDemo
{
    /** categoría => [tipo de IVA, proveedor, NIF, retención %]. Proveedores inventados. */
    private const PROVEEDORES = [
        'Cuota de autónomos'       => ['exento', 'Tesorería General de la Seguridad Social', null, 0],
        'Telefonía e internet'     => ['iva21', 'Telecomunicaciones del Centro S.A.', 'A00000017', 0],
        'Software y suscripciones' => ['iva21', 'Programas Creativos S.L.', 'B00000023', 0],
        'Material de oficina'      => ['iva21', 'Papelería Prado S.L.', 'B00000031', 0],
        'Equipos informáticos'     => ['iva21', 'Informática Castellana S.L.', 'B00000049', 0],
        'Publicidad'               => ['intra21', 'Anuncios Online Europa Ltd', 'IE0000005X', 0],
        'Formación'                => ['exento', 'Escuela de Imagen S.L.', 'B00000064', 0],
        'Asesoría y gestoría'      => ['iva21', 'Asesores Madrid S.L.', 'B00000072', 15],
        'Desplazamientos'          => ['iva10', 'Ferrocarriles Viajeros S.A.', 'A00000089', 0],
    ];

    public function __construct(private ContextoDemo $c, private array $m)
    {
    }

    public function crear(): void
    {
        $filas = [];

        for ($meses = 10; $meses >= 0; $meses--) {
            $mes = $this->c->hoy->copy()->startOfMonth()->subMonths($meses);

            foreach (CatalogoDemo::gastosMensuales() as [$dia, $concepto, $importe, $categoria]) {
                $fecha = $mes->copy()->day(min($dia, $mes->daysInMonth));
                if ($fecha->lte($this->c->hoy)) {
                    $filas[] = $this->fila($fecha, $concepto, $importe, $categoria);
                }
            }
        }

        foreach (CatalogoDemo::gastosPuntuales() as [$meses, $dia, $concepto, $importe, $categoria]) {
            $mes = $this->c->hoy->copy()->startOfMonth()->subMonths($meses);
            $fecha = $mes->copy()->day(min($dia, $mes->daysInMonth));
            if ($fecha->lte($this->c->hoy)) {
                // El desplazamiento se repercute a un cliente, para enseñar esa opción
                $cliente = str_starts_with($concepto, 'Desplazamiento')
                    ? collect($this->m['clientes'])->firstWhere('tipo', 'empresa')['id'] : null;
                $filas[] = $this->fila($fecha, $concepto, $importe, $categoria, $cliente);
            }
        }

        foreach ($filas as $fila) {
            $lineas = $fila['_lineas'];
            unset($fila['_lineas']);
            $id = (int) DB::table('expenses')->insertGetId($fila);
            foreach ($lineas as $l) {
                DB::table('expense_iva_lineas')->insert($l + [
                    'expense_id' => $id, 'company_id' => $this->c->empresa,
                    'created_at' => $fila['created_at'], 'updated_at' => $fila['created_at'],
                ]);
            }
        }
    }

    private function fila($fecha, string $concepto, int $importe, string $categoria, ?int $cliente = null): array
    {
        $f = $fecha->toDateString() . ' 10:00:00';

        // Onfactu v.1.15.0: IVA desglosado; el importe del catálogo lleva el IVA incluido
        [$tipo, $proveedor, $nif, $ret] = self::PROVEEDORES[$categoria] ?? ['iva21', null, null, 0];
        $calc = IvaGastos::calcular([['tipo' => $tipo, 'base' => IvaGastos::baseDesdeTotal($importe, $tipo)]], $ret);
        $importe = $calc['total'];

        return [
            '_lineas' => $calc['lineas'],
            'proveedor_nombre' => $proveedor, 'proveedor_nif' => $nif,
            'numero_factura' => $nif ? sprintf('F-%s-%03d', $fecha->format('ym'), $fecha->day) : null,
            'con_desglose' => true, 'base_imponible' => $calc['base_imponible'], 'cuota_iva' => $calc['cuota_iva'],
            'cuota_deducible' => $calc['cuota_deducible'], 'cuota_autoliquidada' => $calc['cuota_autoliquidada'],
            'retencion_porcentaje' => $calc['retencion_porcentaje'], 'retencion' => $calc['retencion'],
            'expense_date' => $fecha->toDateString(), 'amount' => $importe, 'base_amount' => $importe,
            'notes' => $concepto, 'expense_category_id' => $this->m['categorias'][$categoria],
            'company_id' => $this->c->empresa, 'creator_id' => $this->c->usuario, 'customer_id' => $cliente,
            'currency_id' => $this->c->moneda, 'exchange_rate' => 1,
            'payment_method_id' => $this->c->formaPago(), 'created_at' => $f, 'updated_at' => $f,
        ];
    }
}
