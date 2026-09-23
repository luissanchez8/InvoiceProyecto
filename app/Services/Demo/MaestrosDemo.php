<?php

namespace App\Services\Demo;

use Illuminate\Support\Facades\DB;

/**
 * Datos maestros de la demo: unidades, categorías de gasto, artículos y clientes.
 */
class MaestrosDemo
{
    public function __construct(private ContextoDemo $c)
    {
    }

    /**
     * @return array{clientes: array, articulos: array, categorias: array}
     */
    public function crear(): array
    {
        $this->asegurarUnidades();

        return [
            'categorias' => $this->categorias(),
            'articulos'  => $this->articulos(),
            'clientes'   => $this->clientes(),
        ];
    }

    private function asegurarUnidades(): void
    {
        $ahora = now();
        foreach (['un', 'h', 'mes'] as $u) {
            if (! isset($this->c->unidades[$u])) {
                $this->c->unidades[$u] = (int) DB::table('units')->insertGetId([
                    'name' => $u, 'company_id' => $this->c->empresa, 'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
            }
        }
    }

    private function categorias(): array
    {
        $ids = [];
        $ahora = now();
        foreach (CatalogoDemo::categoriasGasto() as $nombre) {
            $ids[$nombre] = (int) DB::table('expense_categories')->insertGetId([
                'name' => $nombre, 'company_id' => $this->c->empresa, 'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
        }

        return $ids;
    }

    /** @return array lista de ['id','nombre','descripcion','precio','unidad','tipo'] */
    private function articulos(): array
    {
        $lista = [];
        $alta = $this->c->hoy->copy()->subMonths(12)->toDateTimeString();
        foreach (CatalogoDemo::articulos() as [$nombre, $desc, $precio, $unidad, $tipo]) {
            $id = (int) DB::table('items')->insertGetId([
                'name' => $nombre, 'description' => $desc, 'price' => $precio,
                'company_id' => $this->c->empresa, 'unit_id' => $this->c->unidades[$unidad],
                'creator_id' => $this->c->usuario, 'currency_id' => $this->c->moneda, 'tax_per_item' => false,
                'created_at' => $alta, 'updated_at' => $alta,
            ]);
            $lista[] = compact('id', 'nombre', 'precio', 'unidad', 'tipo') + ['descripcion' => $desc];
        }

        return $lista;
    }

    /** @return array lista de ['id','nombre','tipo'] */
    private function clientes(): array
    {
        $lista = [];
        foreach (CatalogoDemo::clientes() as $i => $d) {
            $alta = $this->c->hoy->copy()->subMonths(12)->addDays($i * 3)->toDateTimeString();

            $id = (int) DB::table('customers')->insertGetId([
                'name' => $d['nombre'], 'email' => $d['email'], 'phone' => sprintf('600 000 %03d', 100 + $i),
                'contact_name' => $d['contacto'], 'company_name' => $d['tipo'] === 'particular' ? null : $d['nombre'],
                'enable_portal' => false, 'currency_id' => $this->c->moneda, 'company_id' => $this->c->empresa,
                'creator_id' => $this->c->usuario, 'tax_id' => $d['nif'],
                'created_at' => $alta, 'updated_at' => $alta,
            ]);

            // Las direcciones de clientes no llevan company_id: así la relación
            // Company::address() solo encuentra la de la propia empresa.
            DB::table('addresses')->insert([
                'name' => $d['nombre'], 'address_street_1' => $d['direccion'], 'city' => $d['ciudad'],
                'state' => $d['provincia'], 'zip' => $d['cp'],
                'country_id' => $this->c->paises[$d['pais']] ?? $this->c->paises['ES'],
                'type' => 'billing', 'customer_id' => $id, 'created_at' => $alta, 'updated_at' => $alta,
            ]);

            $lista[] = ['id' => $id, 'nombre' => $d['nombre'], 'tipo' => $d['tipo']];
        }

        return $lista;
    }
}
