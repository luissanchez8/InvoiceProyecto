<?php

namespace App\Services\Demo;

use Illuminate\Support\Facades\DB;

/**
 * Gastos de la demo: los fijos de cada mes y algunos puntuales.
 *
 * Onfactu guarda el gasto por su importe total y su categoría, sin desglose
 * de IVA, así que aquí tampoco lo lleva.
 */
class GastosDemo
{
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

        DB::table('expenses')->insert($filas);
    }

    private function fila($fecha, string $concepto, int $importe, string $categoria, ?int $cliente = null): array
    {
        $f = $fecha->toDateString() . ' 10:00:00';

        return [
            'expense_date' => $fecha->toDateString(), 'amount' => $importe, 'base_amount' => $importe,
            'notes' => $concepto, 'expense_category_id' => $this->m['categorias'][$categoria],
            'company_id' => $this->c->empresa, 'creator_id' => $this->c->usuario, 'customer_id' => $cliente,
            'currency_id' => $this->c->moneda, 'exchange_rate' => 1,
            'payment_method_id' => $this->c->formaPago(), 'created_at' => $f, 'updated_at' => $f,
        ];
    }
}
