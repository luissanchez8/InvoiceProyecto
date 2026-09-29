<?php

namespace App\Http\Controllers\V1\Admin\Expense;

use App\Http\Controllers\Controller;
use App\Models\ClosedMonth;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Silber\Bouncer\BouncerFacade;

/**
 * Onfactu v.1.15.3 — Gestión de los proveedores de los gastos, desde una
 * ventana del propio formulario del gasto.
 *
 * No hay tabla de proveedores: el proveedor está escrito en cada gasto
 * (nombre y NIF). "Gestionar" es corregir esos campos en todos los gastos que
 * lo llevan a la vez:
 *   - editar: cambia el nombre y el NIF en todos sus gastos. Si el nombre nuevo
 *     es el de otro proveedor, los dos quedan unidos en uno;
 *   - quitar: deja sin proveedor esos gastos (los gastos no se borran).
 *
 * Los gastos de meses cerrados no se tocan nunca: ya se entregaron a la
 * gestoría así. Se cuentan aparte para decírselo al usuario.
 */
class ProveedoresGastoController extends Controller
{
    /**
     * GET /api/v1/expenses/iva/proveedores/gestion?search=
     *
     * Cada nombre distinto, tal como está escrito, con su NIF más reciente,
     * cuántos gastos lo llevan y cuántos de ellos son de meses cerrados.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Expense::class);

        $empresa = (int) $request->header('company');
        $search = trim((string) $request->query('search', ''));
        $cerrados = array_flip(ClosedMonth::periodsFor($empresa));

        $filas = Expense::query()
            ->where('company_id', $empresa)
            ->whereNotNull('proveedor_nombre')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('proveedor_nombre', 'ILIKE', '%'.$search.'%')
                ->orWhere('proveedor_nif', 'ILIKE', '%'.$search.'%')))
            ->orderByDesc('expense_date')->orderByDesc('id')
            ->get(['proveedor_nombre', 'proveedor_nif', 'expense_date']);

        $proveedores = [];
        foreach ($filas as $f) {
            $p = &$proveedores[$f->proveedor_nombre];
            $p ??= ['nombre' => $f->proveedor_nombre, 'nif' => null, 'gastos' => 0, 'cerrados' => 0];
            $p['nif'] ??= $f->proveedor_nif;
            $p['gastos']++;
            if (isset($cerrados[ClosedMonth::toPeriod($f->expense_date)])) {
                $p['cerrados']++;
            }
            unset($p);
        }

        $lista = array_values($proveedores);
        usort($lista, fn ($a, $b) => strcasecmp($a['nombre'], $b['nombre']));

        return response()->json(['data' => array_slice($lista, 0, 200)]);
    }

    /**
     * PUT /api/v1/expenses/iva/proveedores
     * { nombre, nuevo_nombre, nif }
     */
    public function update(Request $request)
    {
        $this->exigirPermiso();

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:190'],
            'nuevo_nombre' => ['required', 'string', 'max:190'],
            'nif' => ['nullable', 'string', 'max:30'],
        ]);

        $nombre = trim($datos['nuevo_nombre']);
        $nif = strtoupper(preg_replace('/[\s\-\.]/', '', (string) ($datos['nif'] ?? '')));

        $cambiados = $this->abiertos($request, $datos['nombre'])->update([
            'proveedor_nombre' => $nombre,
            'proveedor_nif' => $nif === '' ? null : $nif,
        ]);

        return response()->json([
            'cambiados' => $cambiados,
            'cerrados' => $this->enCerrados($request, $datos['nombre']),
        ]);
    }

    /**
     * POST /api/v1/expenses/iva/proveedores/quitar
     * { nombre }
     */
    public function quitar(Request $request)
    {
        $this->exigirPermiso();

        $datos = $request->validate(['nombre' => ['required', 'string', 'max:190']]);

        $cambiados = $this->abiertos($request, $datos['nombre'])->update([
            'proveedor_nombre' => null,
            'proveedor_nif' => null,
        ]);

        return response()->json([
            'cambiados' => $cambiados,
            'cerrados' => $this->enCerrados($request, $datos['nombre']),
        ]);
    }

    /** Gastos del proveedor que se pueden cambiar: los de meses abiertos. */
    private function abiertos(Request $request, string $nombre): Builder
    {
        return $this->delProveedor($request, $nombre)->whereNotExists($this->mesCerrado());
    }

    private function enCerrados(Request $request, string $nombre): int
    {
        return $this->delProveedor($request, $nombre)->whereExists($this->mesCerrado())->count();
    }

    private function delProveedor(Request $request, string $nombre): Builder
    {
        return Expense::query()
            ->where('company_id', (int) $request->header('company'))
            ->where('proveedor_nombre', $nombre);
    }

    /** El mes del gasto está en closed_months. */
    private function mesCerrado(): \Closure
    {
        return fn ($q) => $q->select(DB::raw(1))
            ->from('closed_months as cm')
            ->whereColumn('cm.company_id', 'expenses.company_id')
            ->whereRaw('cm.year = EXTRACT(YEAR FROM expenses.expense_date)')
            ->whereRaw('cm.month = EXTRACT(MONTH FROM expenses.expense_date)');
    }

    private function exigirPermiso(): void
    {
        abort_unless(BouncerFacade::can('edit-expense', Expense::class), 403);
    }
}
