<?php

namespace App\Services\Demo;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Lo que comparten todos los generadores de la demo: identificadores de la
 * instancia, azar reproducible, cálculo de importes e inserción de líneas e
 * impuestos.
 *
 * Los importes van en céntimos, como los guarda Onfactu. Con impuestos a nivel
 * de documento (tax_per_item = NO): el total es la base menos el descuento más
 * los impuestos, y la retención es un impuesto de porcentaje negativo.
 */
class ContextoDemo
{
    public int $empresa = 1;
    public int $moneda;
    public int $usuario;
    public Carbon $hoy;

    /** clave => ['id', 'nombre', 'porcentaje'] */
    public array $impuestos = [];
    /** ids de las formas de pago */
    public array $formasPago = [];
    /** nombre => id */
    public array $unidades = [];
    /** código ISO => id */
    public array $paises = [];

    public string $plantillaFactura = 'invoice4';
    public string $plantillaPresupuesto = 'estimate1';

    public function __construct(Carbon $hoy)
    {
        $this->hoy = $hoy->copy()->startOfDay();

        $this->moneda = (int) DB::table('currencies')->where('code', 'EUR')->value('id');
        $this->usuario = (int) DB::table('users')->where('role', 'super admin')->orderBy('id')->value('id');
        if (! $this->moneda || ! $this->usuario) {
            throw new RuntimeException('Falta la moneda EUR o el usuario administrador');
        }

        $buscar = fn (string $nombre) => DB::table('tax_types')
            ->where('company_id', $this->empresa)->where('name', $nombre)->where('description', 'Ventas')
            ->first(['id', 'name', 'percent']);

        foreach (['iva21' => 'IVA 21%', 'ret15' => 'Retención 15%', 'intracom' => 'IVA Intracomunitario Servicio'] as $clave => $nombre) {
            $t = $buscar($nombre);
            if (! $t) {
                throw new RuntimeException("No existe el tipo de impuesto de ventas '{$nombre}'");
            }
            $this->impuestos[$clave] = ['id' => (int) $t->id, 'nombre' => $t->name, 'porcentaje' => (float) $t->percent];
        }

        $this->formasPago = DB::table('payment_methods')->where('company_id', $this->empresa)
            ->orderBy('id')->pluck('id')->map(fn ($v) => (int) $v)->all();
        $this->unidades = DB::table('units')->where('company_id', $this->empresa)
            ->pluck('id', 'name')->map(fn ($v) => (int) $v)->all();
        $this->paises = DB::table('countries')->whereIn('code', ['ES', 'PT', 'FR'])
            ->pluck('id', 'code')->map(fn ($v) => (int) $v)->all();

        // Plantilla de PDF: la profesional de Onfactu si existe
        if (! file_exists(resource_path('views/app/pdf/invoice/invoice4.blade.php'))) {
            $this->plantillaFactura = 'invoice1';
        }
    }

    // ── Azar reproducible (sembrado por fecha en la orden) ─────────────────

    public function azar(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    public function probable(float $p): bool
    {
        return mt_rand(1, 10000) <= (int) round($p * 10000);
    }

    public function elegir(array $lista)
    {
        return $lista[mt_rand(0, count($lista) - 1)];
    }

    public function fechaEntre(Carbon $desde, Carbon $hasta): Carbon
    {
        $dias = max(0, $desde->diffInDays($hasta));

        return $desde->copy()->addDays(mt_rand(0, (int) $dias));
    }

    public function formaPago(): int
    {
        // La transferencia es lo habitual entre empresas
        return $this->probable(0.7) ? end($this->formasPago) : $this->elegir($this->formasPago);
    }

    // ── Cálculo ────────────────────────────────────────────────────────────

    /** Impuestos que corresponden según el tipo de cliente y el de las líneas. */
    public function impuestosPara(string $tipoCliente, bool $soloServicios): array
    {
        if ($tipoCliente === 'ue') {
            return ['intracom'];
        }

        return ($tipoCliente === 'empresa' && $soloServicios) ? ['iva21', 'ret15'] : ['iva21'];
    }

    /**
     * @param array $lineas [['nombre','descripcion','precio','cantidad','unidad','item_id']]
     * @param int   $signo  -1 para rectificativas
     */
    public function calcular(array $lineas, array $claves, float $descuentoPct = 0, int $signo = 1): array
    {
        $sub = 0;
        foreach ($lineas as &$l) {
            $l['precio'] = $signo * abs((int) $l['precio']);
            $l['total'] = (int) round($l['precio'] * (float) $l['cantidad']);
            $sub += $l['total'];
        }
        unset($l);

        $descuento = (int) round($sub * $descuentoPct / 100);
        $base = $sub - $descuento;

        $imp = [];
        $tax = 0;
        foreach ($claves as $c) {
            $t = $this->impuestos[$c];
            $importe = (int) round($base * $t['porcentaje'] / 100);
            $imp[] = $t + ['importe' => $importe];
            $tax += $importe;
        }

        return [
            'lineas' => $lineas, 'sub_total' => $sub, 'descuento_pct' => $descuentoPct,
            'descuento' => $descuento, 'impuestos' => $imp, 'tax' => $tax, 'total' => $base + $tax,
        ];
    }

    // ── Inserción ──────────────────────────────────────────────────────────

    public function insertarLineas(string $tabla, string $fk, int $idDoc, array $lineas, string $fecha): void
    {
        $filas = [];
        foreach ($lineas as $l) {
            $filas[] = [
                'name' => $l['nombre'], 'description' => $l['descripcion'], 'discount_type' => 'fixed',
                'price' => $l['precio'], 'quantity' => $l['cantidad'], 'discount' => 0, 'discount_val' => 0,
                'tax' => 0, 'total' => $l['total'], 'unit_name' => $l['unidad'], 'item_id' => $l['item_id'],
                'company_id' => $this->empresa, 'exchange_rate' => 1, 'base_price' => $l['precio'],
                'base_discount_val' => 0, 'base_tax' => 0, 'base_total' => $l['total'],
                'created_at' => $fecha, 'updated_at' => $fecha, $fk => $idDoc,
            ];
        }
        DB::table($tabla)->insert($filas);
    }

    public function insertarImpuestos(string $fk, int $idDoc, array $impuestos, string $fecha): void
    {
        $filas = [];
        foreach ($impuestos as $t) {
            $filas[] = [
                'tax_type_id' => $t['id'], $fk => $idDoc, 'company_id' => $this->empresa,
                'name' => $t['nombre'], 'amount' => $t['importe'], 'percent' => $t['porcentaje'],
                'compound_tax' => 0, 'exchange_rate' => 1, 'base_amount' => $t['importe'],
                'currency_id' => $this->moneda, 'calculation_type' => 'percentage', 'fixed_amount' => 0,
                'created_at' => $fecha, 'updated_at' => $fecha,
            ];
        }
        DB::table('taxes')->insert($filas);
    }

    /** Campos de importes comunes a todas las cabeceras de documento. */
    public function importes(array $calc): array
    {
        return [
            'tax_per_item' => 'NO', 'discount_per_item' => 'NO',
            'discount_type' => $calc['descuento_pct'] > 0 ? 'percentage' : 'fixed',
            'discount' => $calc['descuento_pct'], 'discount_val' => $calc['descuento'],
            'sub_total' => $calc['sub_total'], 'tax' => $calc['tax'], 'total' => $calc['total'],
            'base_discount_val' => $calc['descuento'], 'base_sub_total' => $calc['sub_total'],
            'base_tax' => $calc['tax'], 'base_total' => $calc['total'],
            'currency_id' => $this->moneda, 'exchange_rate' => 1,
            'company_id' => $this->empresa, 'creator_id' => $this->usuario,
        ];
    }

    /** El hash público de los PDF, igual que lo genera Onfactu al crear el documento. */
    public function hash(string $modelo, string $tabla, int $id): void
    {
        DB::table($tabla)->where('id', $id)->update([
            'unique_hash' => Hashids::connection($modelo)->encode($id),
        ]);
    }

    public static function numero(string $serie, int $n): string
    {
        return sprintf('%s-%06d', $serie, $n);
    }
}
