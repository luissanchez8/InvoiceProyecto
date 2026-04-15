<?php
namespace App\Http\Controllers\V1\Customer\DeliveryNote;
use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\InvoiceResource;
use App\Models\Company;
use App\Models\DeliveryNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class DeliveryNotesController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->has('limit') ? $request->limit : 10;
        $customerId = Auth::guard('customer')->id();
        $deliveryNotes = DeliveryNote::with(['items', 'customer', 'taxes'])
            ->where('status', '<>', 'DRAFT')
            ->where('customer_id', $customerId)
            ->applyFilters($request->all())
            ->latest()
            ->paginateData($limit);
        return InvoiceResource::collection($deliveryNotes)
            ->additional(['meta' => [
                'deliveryNoteTotalCount' => DeliveryNote::where('status', '<>', 'DRAFT')
                    ->where('customer_id', $customerId)->count(),
            ]]);
    }
    public function show(Company $company, $id)
    {
        $deliveryNote = $company->deliveryNotes()
            ->where('customer_id', Auth::guard('customer')->id())
            ->where('id', $id)
            ->first();
        if (! $deliveryNote) {
            return response()->json(['error' => 'delivery_note_not_found'], 404);
        }
        return new InvoiceResource($deliveryNote);
    }
}
