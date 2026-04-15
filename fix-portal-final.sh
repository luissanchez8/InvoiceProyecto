#!/bin/bash
set -euo pipefail
cd /tmp/InvoiceProyecto
echo "=== Fix portal cliente - definitivo ==="

# ============================================================
# 1. CUSTOMER_MENU - Añadir proformas y albaranes
# ============================================================
# Insertar después de la entrada de payments en customer_menu
sed -i "/navigation.payments/,/'model' => ''/{ 
  /'model' => ''/a\\
        ],\\
        [\\
            'title' => 'navigation.proforma_invoices',\\
            'link' => '/customer/proforma-invoices',\\
            'icon' => '',\\
            'name' => '',\\
            'owner_only' => false,\\
            'ability' => '',\\
            'group' => '',\\
            'model' => '',\\
            'option_key' => 'OPCION_MENU_PROFORMAS',\\
        ],\\
        [\\
            'title' => 'navigation.delivery_notes',\\
            'link' => '/customer/delivery-notes',\\
            'icon' => '',\\
            'name' => '',\\
            'owner_only' => false,\\
            'ability' => '',\\
            'group' => '',\\
            'model' => '',\\
            'option_key' => 'OPCION_MENU_ALBARANES',
}" config/invoiceshelf.php
echo "1/4 OK - customer_menu"

# ============================================================
# 2. RUTAS API - Arreglar namespaces rotos
# ============================================================
sed -i 's/AppHttpControllersV1CustomerProformaInvoiceProformaInvoicesController/\\App\\Http\\Controllers\\V1\\Customer\\ProformaInvoice\\ProformaInvoicesController/g' routes/api.php
sed -i 's/AppHttpControllersV1CustomerDeliveryNoteDeliveryNotesController/\\App\\Http\\Controllers\\V1\\Customer\\DeliveryNote\\DeliveryNotesController/g' routes/api.php
echo "2/4 OK - rutas API"

# ============================================================
# 3. COMPANY MODEL - Añadir relaciones proformaInvoices y deliveryNotes
# ============================================================
if ! grep -q "function proformaInvoices" app/Models/Company.php; then
    # Insertar después de la relación invoices()
    sed -i '/public function invoices(): HasMany/,/}/a\
\
    public function proformaInvoices(): HasMany\
    {\
        return $this->hasMany(\\App\\Models\\ProformaInvoice::class);\
    }\
\
    public function deliveryNotes(): HasMany\
    {\
        return $this->hasMany(\\App\\Models\\DeliveryNote::class);\
    }' app/Models/Company.php
    echo "3/4 OK - Company model"
else
    echo "3/4 SKIP - relaciones ya existen"
fi

# ============================================================
# 4. CONTROLADORES - Simplificar show() sin usar company relation
# ============================================================
cat > app/Http/Controllers/V1/Customer/ProformaInvoice/ProformaInvoicesController.php << 'PHPEOF'
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
PHPEOF

cat > app/Http/Controllers/V1/Customer/DeliveryNote/DeliveryNotesController.php << 'PHPEOF'
<?php
namespace App\Http\Controllers\V1\Customer\DeliveryNote;
use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\InvoiceResource;
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
    public function show(Request $request, $company, $id)
    {
        $deliveryNote = DeliveryNote::where('customer_id', Auth::guard('customer')->id())
            ->where('id', $id)
            ->first();
        if (! $deliveryNote) {
            return response()->json(['error' => 'delivery_note_not_found'], 404);
        }
        return new InvoiceResource($deliveryNote);
    }
}
PHPEOF
echo "4/4 OK - Controladores"

echo ""
echo "=== COMPLETADO ==="
echo "git add . && git commit -m 'Fix: portal cliente - menu, rutas, relaciones y controladores' && git push origin main"
echo "cd ~/onfactu-produccion-20251125/onfactu && docker build --no-cache --build-arg CACHEBUST=\$(date +%s) -t invoiceshelf-app . 2>&1 | tail -5"
