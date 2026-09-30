<?php

namespace App\Http\Controllers\V1\Admin\Facturacion;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\Facturacion\ConvertirEnFactura;
use App\Services\Facturacion\Documentos;
use App\Services\Facturacion\FacturarAnticipo;
use App\Services\Facturacion\NoSePuedeFacturar;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Onfactu v.1.17.0 — Facturar varios documentos juntos y facturar anticipos.
 *
 *   POST /facturacion/convertir  {tipo, ids[]}                   una factura con todos
 *   POST /facturacion/anticipo   {tipo, id, modo, valor}          factura de anticipo
 *
 * La conversión de un solo documento sigue en sus rutas de siempre
 * (convert-to-invoice, convert), que usan el mismo servicio.
 */
class FacturacionController extends Controller
{
    public function convertir(Request $request)
    {
        $this->authorize('create', Invoice::class);

        $datos = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(Documentos::TIPOS))],
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
        ]);

        $empresa = (int) $request->header('company');
        $modelo = Documentos::cfg($datos['tipo'])['modelo'];
        $docs = $modelo::where('company_id', $empresa)->whereIn('id', $datos['ids'])
            ->orderBy(Documentos::cfg($datos['tipo'])['fecha'])->orderBy('id')->get();

        if ($docs->count() !== count(array_unique($datos['ids']))) {
            return (new NoSePuedeFacturar('Alguno de los documentos ya no existe.'))->respuesta();
        }

        try {
            $factura = ConvertirEnFactura::desde($docs);
        } catch (NoSePuedeFacturar $e) {
            return $e->respuesta();
        }

        return new InvoiceResource($factura);
    }

    public function anticipo(Request $request)
    {
        $this->authorize('create', Invoice::class);

        $datos = $request->validate([
            'tipo' => ['required', Rule::in(Documentos::CON_ANTICIPO)],
            'id' => ['required', 'integer'],
            'modo' => ['required', Rule::in([FacturarAnticipo::PORCENTAJE, FacturarAnticipo::IMPORTE])],
            'valor' => ['required', 'numeric', 'gt:0', 'max:999999999'],
        ]);

        $doc = Documentos::buscar($datos['tipo'], (int) $datos['id'], (int) $request->header('company'));
        if (! $doc) {
            abort(404);
        }

        try {
            $factura = FacturarAnticipo::crear($doc, $datos['modo'], (float) $datos['valor']);
        } catch (NoSePuedeFacturar $e) {
            return $e->respuesta();
        }

        return new InvoiceResource($factura);
    }
}
