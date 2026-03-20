<?php

/**
 * Controller DeliveryNotesController — CRUD de albaranes
 *
 * Gestiona listado, creación, visualización, actualización y eliminación
 * de albaranes. Sigue el mismo patrón que InvoicesController.
 */

namespace App\Http\Controllers\V1\Admin\DeliveryNote;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeliveryNotesRequest;
use App\Models\DeliveryNote;
use Illuminate\Http\Request;

class DeliveryNotesController extends Controller
{
    /** Listado paginado de albaranes de la empresa actual */
    public function index(Request $request)
    {
        $this->authorize('viewAny', DeliveryNote::class);

        $limit = $request->input('limit', 10);

        // paginateData() devuelve LengthAwarePaginator que Laravel serializa
        // con data[], links y meta (last_page, total, per_page, etc.)
        $deliveryNotes = DeliveryNote::whereCompany()
            ->applyFilters($request->all())
            ->with(['customer', 'currency'])
            ->latest()
            ->paginateData($limit);

        $response = $deliveryNotes->toArray();
        $response['delivery_note_total_count'] = DeliveryNote::whereCompany()->count();

        return response()->json($response);
    }

    /** Crea un nuevo albarán */
    public function store(DeliveryNotesRequest $request)
    {
        $this->authorize('create', DeliveryNote::class);

        $deliveryNote = DeliveryNote::createDeliveryNote($request);

        return response()->json([
            'data' => $deliveryNote,
            'success' => true,
        ]);
    }

    /** Muestra un albarán individual */
    public function show(Request $request, DeliveryNote $deliveryNote)
    {
        $this->authorize('view', $deliveryNote);

        $deliveryNote->load([
            'items', 'items.fields', 'items.fields.customField',
            'customer', 'taxes', 'creator', 'company', 'currency',
            'fields', 'fields.customField',
        ]);

        return response()->json(['data' => $deliveryNote]);
    }

    /** Actualiza un albarán existente */
    public function update(DeliveryNotesRequest $request, DeliveryNote $deliveryNote)
    {
        $this->authorize('update', $deliveryNote);

        $deliveryNote = $deliveryNote->updateDeliveryNote($request);

        return response()->json([
            'data' => $deliveryNote,
            'success' => true,
        ]);
    }

    /** Eliminación masiva de albaranes por IDs */
    public function delete(Request $request)
    {
        $this->authorize('delete multiple delivery notes');

        DeliveryNote::deleteDeliveryNotes($request->ids);

        return response()->json(['success' => true]);
    }
}
