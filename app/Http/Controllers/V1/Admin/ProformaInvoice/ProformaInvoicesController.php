<?php

/**
 * Controller ProformaInvoicesController — CRUD de facturas proforma
 *
 * Gestiona listado, creación, visualización, actualización y eliminación
 * de facturas proforma. Sigue el mismo patrón que InvoicesController.
 *
 * El método index() devuelve la paginación de Laravel directamente
 * para que BaseTable en el frontend reciba meta.last_page, meta.total, etc.
 */

namespace App\Http\Controllers\V1\Admin\ProformaInvoice;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProformaInvoicesRequest;
use App\Models\ProformaInvoice;
use Illuminate\Http\Request;

class ProformaInvoicesController extends Controller
{
    /**
     * Listado paginado de facturas proforma de la empresa actual.
     * Devuelve la respuesta paginada de Laravel directamente
     * (incluye data, meta con current_page, last_page, total, etc.)
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', ProformaInvoice::class);

        $limit = $request->input('limit', 10);

        // paginateData() devuelve LengthAwarePaginator que Laravel serializa
        // correctamente con data[], links y meta (last_page, total, per_page, etc.)
        $proformaInvoices = ProformaInvoice::whereCompany()
            ->applyFilters($request->all())
            ->with(['customer', 'currency'])
            ->latest()
            ->paginateData($limit);

        // Añadir el contador total al meta de la respuesta paginada
        $response = $proformaInvoices->toArray();
        $response['proforma_invoice_total_count'] = ProformaInvoice::whereCompany()->count();

        return response()->json($response);
    }

    /**
     * Crea una nueva factura proforma.
     */
    public function store(ProformaInvoicesRequest $request)
    {
        $this->authorize('create', ProformaInvoice::class);

        $proformaInvoice = ProformaInvoice::createProformaInvoice($request);

        return response()->json([
            'data' => $proformaInvoice,
            'success' => true,
        ]);
    }

    /** Muestra una factura proforma individual con todas sus relaciones */
    public function show(Request $request, ProformaInvoice $proformaInvoice)
    {
        $this->authorize('view', $proformaInvoice);

        $proformaInvoice->load([
            'items', 'items.fields', 'items.fields.customField',
            'customer', 'taxes', 'creator', 'company', 'currency',
            'fields', 'fields.customField',
        ]);

        return response()->json(['data' => $proformaInvoice]);
    }

    /** Actualiza una factura proforma existente */
    public function update(ProformaInvoicesRequest $request, ProformaInvoice $proformaInvoice)
    {
        $this->authorize('update', $proformaInvoice);

        $proformaInvoice = $proformaInvoice->updateProformaInvoice($request);

        return response()->json([
            'data' => $proformaInvoice,
            'success' => true,
        ]);
    }

    /** Eliminación masiva de facturas proforma por IDs */
    public function delete(Request $request)
    {
        $this->authorize('delete multiple proforma invoices');

        ProformaInvoice::deleteProformaInvoices($request->ids);

        return response()->json(['success' => true]);
    }
}
