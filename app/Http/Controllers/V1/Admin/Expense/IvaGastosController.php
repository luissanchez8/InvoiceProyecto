<?php

namespace App\Http\Controllers\V1\Admin\Expense;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Support\IvaGastos;
use Illuminate\Http\Request;

/**
 * Onfactu v.1.15.0 — Lo que necesita el formulario de gastos para el IVA:
 * los tipos que se pueden elegir y los proveedores ya usados.
 */
class IvaGastosController extends Controller
{
    /** GET /api/v1/expenses/iva/catalogo */
    public function catalogo()
    {
        $this->authorize('viewAny', Expense::class);

        return response()->json(IvaGastos::catalogo());
    }

    /**
     * GET /api/v1/expenses/iva/proveedores?search=
     *
     * Los proveedores escritos en gastos anteriores, con el último NIF usado,
     * para sugerirlos al escribir. No hay lista de proveedores aparte.
     */
    public function proveedores(Request $request)
    {
        $this->authorize('viewAny', Expense::class);

        $search = trim((string) $request->query('search', ''));

        $filas = Expense::query()
            ->where('company_id', $request->header('company'))
            ->whereNotNull('proveedor_nombre')
            ->when($search !== '', fn ($q) => $q->where('proveedor_nombre', 'ILIKE', '%'.$search.'%'))
            ->orderByDesc('expense_date')->orderByDesc('id')
            ->limit(200)
            ->get(['proveedor_nombre', 'proveedor_nif']);

        // Uno por nombre, con el NIF del gasto más reciente que lo tenga
        $proveedores = [];
        foreach ($filas as $f) {
            $clave = mb_strtolower($f->proveedor_nombre);
            if (! isset($proveedores[$clave])) {
                $proveedores[$clave] = ['nombre' => $f->proveedor_nombre, 'nif' => $f->proveedor_nif];
            } elseif (! $proveedores[$clave]['nif'] && $f->proveedor_nif) {
                $proveedores[$clave]['nif'] = $f->proveedor_nif;
            }
        }

        return response()->json(['data' => array_slice(array_values($proveedores), 0, 20)]);
    }
}
