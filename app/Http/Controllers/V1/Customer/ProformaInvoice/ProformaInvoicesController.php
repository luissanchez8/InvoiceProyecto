<?php
namespace App\Http\Controllers\V1\Customer\ProformaInvoice;
use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\InvoiceResource;
use App\Models\ProformaInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class ProformaInvoicesController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->has('limit') ? $request->limit : 10;
        $customerId = Auth::guard('customer')->id();
        $proformas = ProformaInvoice::with(['items', 'customer', 'taxes'])
            ->where('status', '<>', 'DRAFT')
            ->where('customer_id', $customerId)
            ->applyFilters($request->all())
            ->latest()
            ->paginateData($limit);
        return InvoiceResource::collection($proformas)
            ->additional(['meta' => [
                'proformaTotalCount' => ProformaInvoice::where('status', '<>', 'DRAFT')
                    ->where('customer_id', $customerId)->count(),
            ]]);
    }
    public function show(Request $request, $company, $id)
    {
        $proforma = ProformaInvoice::where('customer_id', Auth::guard('customer')->id())
            ->where('id', $id)
            ->first();
        if (! $proforma) {
            return response()->json(['error' => 'proforma_not_found'], 404);
        }
        return new InvoiceResource($proforma);
    }
}
