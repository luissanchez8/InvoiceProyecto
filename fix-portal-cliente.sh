#!/bin/bash
set -euo pipefail
cd /tmp/InvoiceProyecto
echo "=== Añadiendo proformas y albaranes al portal de cliente ==="

# ============================================================
# 1. CONTROLADORES PHP — ProformaInvoices y DeliveryNotes para el customer
# ============================================================
mkdir -p app/Http/Controllers/V1/Customer/ProformaInvoice
mkdir -p app/Http/Controllers/V1/Customer/DeliveryNote

cat > app/Http/Controllers/V1/Customer/ProformaInvoice/ProformaInvoicesController.php << 'PHPEOF'
<?php
namespace App\Http\Controllers\V1\Customer\ProformaInvoice;
use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\InvoiceResource;
use App\Models\Company;
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
    public function show(Company $company, $id)
    {
        $proforma = $company->proformaInvoices()
            ->where('customer_id', Auth::guard('customer')->id())
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
PHPEOF
echo "1/6 OK - Controladores PHP"

# ============================================================
# 2. RUTAS API del customer
# ============================================================
if ! grep -q "customer/proforma-invoices" routes/api.php; then
    sed -i "/Route::get('payments\/{id}', \[CustomerPaymentsController::class, 'show'\]);/a\\
\\
            // Proformas (portal cliente)\\
            Route::get('proforma-invoices', [\\App\\Http\\Controllers\\V1\\Customer\\ProformaInvoice\\ProformaInvoicesController::class, 'index']);\\
            Route::get('proforma-invoices/{id}', [\\App\\Http\\Controllers\\V1\\Customer\\ProformaInvoice\\ProformaInvoicesController::class, 'show']);\\
\\
            // Albaranes (portal cliente)\\
            Route::get('delivery-notes', [\\App\\Http\\Controllers\\V1\\Customer\\DeliveryNote\\DeliveryNotesController::class, 'index']);\\
            Route::get('delivery-notes/{id}', [\\App\\Http\\Controllers\\V1\\Customer\\DeliveryNote\\DeliveryNotesController::class, 'show']);" routes/api.php
    echo "2/6 OK - Rutas API"
else
    echo "2/6 SKIP"
fi

# ============================================================
# 3. STORES del customer — proforma-invoice.js y delivery-note.js
# ============================================================
cat > resources/scripts/customer/stores/proforma-invoice.js << 'JSEOF'
import { handleError } from '@/scripts/customer/helpers/error-handling'
const { defineStore } = window.pinia
import axios from 'axios'
export const useProformaInvoiceStore = defineStore({
  id: 'customerProformaInvoiceStore',
  state: () => ({
    totalProformas: 0,
    proformas: [],
    selectedViewProforma: [],
  }),
  actions: {
    fetchProformas(params, slug) {
      return new Promise((resolve, reject) => {
        axios
          .get(`/api/v1/${slug}/customer/proforma-invoices`, { params })
          .then((response) => {
            this.proformas = response.data.data
            this.totalProformas = response.data.meta.proformaTotalCount
            resolve(response)
          })
          .catch((err) => { handleError(err); reject(err) })
      })
    },
    fetchViewProforma(params, slug) {
      return new Promise((resolve, reject) => {
        axios
          .get(`/api/v1/${slug}/customer/proforma-invoices/${params.id}`, { params })
          .then((response) => {
            this.selectedViewProforma = response.data.data
            resolve(response)
          })
          .catch((err) => { handleError(err); reject(err) })
      })
    },
  },
})
JSEOF

cat > resources/scripts/customer/stores/delivery-note.js << 'JSEOF'
import { handleError } from '@/scripts/customer/helpers/error-handling'
const { defineStore } = window.pinia
import axios from 'axios'
export const useDeliveryNoteStore = defineStore({
  id: 'customerDeliveryNoteStore',
  state: () => ({
    totalDeliveryNotes: 0,
    deliveryNotes: [],
    selectedViewDeliveryNote: [],
  }),
  actions: {
    fetchDeliveryNotes(params, slug) {
      return new Promise((resolve, reject) => {
        axios
          .get(`/api/v1/${slug}/customer/delivery-notes`, { params })
          .then((response) => {
            this.deliveryNotes = response.data.data
            this.totalDeliveryNotes = response.data.meta.deliveryNoteTotalCount
            resolve(response)
          })
          .catch((err) => { handleError(err); reject(err) })
      })
    },
    fetchViewDeliveryNote(params, slug) {
      return new Promise((resolve, reject) => {
        axios
          .get(`/api/v1/${slug}/customer/delivery-notes/${params.id}`, { params })
          .then((response) => {
            this.selectedViewDeliveryNote = response.data.data
            resolve(response)
          })
          .catch((err) => { handleError(err); reject(err) })
      })
    },
  },
})
JSEOF
echo "3/6 OK - Stores"

# ============================================================
# 4. VISTAS VUE — Copiar de invoices y adaptar
# ============================================================
mkdir -p resources/scripts/customer/views/proforma-invoices
mkdir -p resources/scripts/customer/views/delivery-notes

# Proforma Index — basado en invoices/Index.vue
sed 's/invoices/proforma-invoices/g; s/invoice/proforma/g; s/Invoice/Proforma/g; s/useInvoiceStore/useProformaInvoiceStore/g; s/customerInvoiceStore/customerProformaInvoiceStore/g; s/totalInvoices/totalProformas/g; s/invoiceTotalCount/proformaTotalCount/g; s/invoice_number/proforma_invoice_number/g; s/formatted_invoice_date/formatted_created_at/g' resources/scripts/customer/views/invoices/Index.vue > resources/scripts/customer/views/proforma-invoices/Index.vue

# Proforma View — basado en invoices/View.vue
sed 's/invoices/proforma-invoices/g; s/invoice/proforma/g; s/Invoice/Proforma/g; s/useInvoiceStore/useProformaInvoiceStore/g; s/customerInvoiceStore/customerProformaInvoiceStore/g; s/selectedViewInvoice/selectedViewProforma/g; s/fetchViewInvoice/fetchViewProforma/g; s/invoice_number/proforma_invoice_number/g' resources/scripts/customer/views/invoices/View.vue > resources/scripts/customer/views/proforma-invoices/View.vue

# Delivery Note Index
sed 's/invoices/delivery-notes/g; s/invoice/delivery_note/g; s/Invoice/DeliveryNote/g; s/useInvoiceStore/useDeliveryNoteStore/g; s/customerInvoiceStore/customerDeliveryNoteStore/g; s/totalInvoices/totalDeliveryNotes/g; s/invoiceTotalCount/deliveryNoteTotalCount/g; s/invoice_number/delivery_note_number/g; s/formatted_invoice_date/formatted_created_at/g' resources/scripts/customer/views/invoices/Index.vue > resources/scripts/customer/views/delivery-notes/Index.vue

# Delivery Note View
sed 's/invoices/delivery-notes/g; s/invoice/delivery_note/g; s/Invoice/DeliveryNote/g; s/useInvoiceStore/useDeliveryNoteStore/g; s/customerInvoiceStore/customerDeliveryNoteStore/g; s/selectedViewInvoice/selectedViewDeliveryNote/g; s/fetchViewInvoice/fetchViewDeliveryNote/g; s/invoice_number/delivery_note_number/g' resources/scripts/customer/views/invoices/View.vue > resources/scripts/customer/views/delivery-notes/View.vue

# Fix imports en las vistas generadas
sed -i "s|from '@/scripts/customer/stores/invoice'|from '@/scripts/customer/stores/proforma-invoice'|" resources/scripts/customer/views/proforma-invoices/Index.vue
sed -i "s|from '@/scripts/customer/stores/invoice'|from '@/scripts/customer/stores/proforma-invoice'|" resources/scripts/customer/views/proforma-invoices/View.vue
sed -i "s|from '@/scripts/customer/stores/invoice'|from '@/scripts/customer/stores/delivery-note'|" resources/scripts/customer/views/delivery-notes/Index.vue
sed -i "s|from '@/scripts/customer/stores/invoice'|from '@/scripts/customer/stores/delivery-note'|" resources/scripts/customer/views/delivery-notes/View.vue

echo "4/6 OK - Vistas Vue"

# ============================================================
# 5. RUTAS VUE — customer-router.js
# ============================================================
if ! grep -q "proforma-invoices" resources/scripts/customer/customer-router.js; then
    # Añadir imports
    sed -i "/const PaymentView/a\\
const ProformaInvoice = () => import('@/scripts/customer/views/proforma-invoices/Index.vue')\\
const ProformaInvoiceView = () => import('@/scripts/customer/views/proforma-invoices/View.vue')\\
const DeliveryNote = () => import('@/scripts/customer/views/delivery-notes/Index.vue')\\
const DeliveryNoteView = () => import('@/scripts/customer/views/delivery-notes/View.vue')" resources/scripts/customer/customer-router.js

    # Añadir rutas después de payments
    sed -i "/name: 'customer.payments.view'/,/}/a\\
      {\\
        path: 'proforma-invoices',\\
        component: ProformaInvoice,\\
        name: 'customer.proforma-invoices',\\
      },\\
      {\\
        path: 'proforma-invoices/:id/view',\\
        component: ProformaInvoiceView,\\
        name: 'customer.proforma-invoices.view',\\
      },\\
      {\\
        path: 'delivery-notes',\\
        component: DeliveryNote,\\
        name: 'customer.delivery-notes',\\
      },\\
      {\\
        path: 'delivery-notes/:id/view',\\
        component: DeliveryNoteView,\\
        name: 'customer.delivery-notes.view',\\
      }," resources/scripts/customer/customer-router.js
    echo "5/6 OK - Rutas Vue"
else
    echo "5/6 SKIP"
fi

# ============================================================
# 6. MENÚ DEL PORTAL — Añadir proformas y albaranes con option_key
# ============================================================
if ! grep -q "proforma-invoices" config/invoiceshelf.php; then
    # Añadir después de estimates en customer_menu
    sed -i "/title.*navigation.estimates/,/model.*''/a\\
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
            'option_key' => 'OPCION_MENU_ALBARANES'," config/invoiceshelf.php
    echo "6/6 OK - Menú del portal"
else
    echo "6/6 SKIP"
fi

# ============================================================
# 7. FILTRAR MENÚ DEL PORTAL POR option_key
# ============================================================
# El BootstrapController del customer no filtra por option_key
# Hay que añadir el filtro
if ! grep -q "option_key" app/Http/Controllers/V1/Customer/General/BootstrapController.php; then
    sed -i 's/foreach (\\Menu::get.*customer_portal_menu.*->items->toArray() as \$data) {/foreach (\\Menu::get('\''customer_portal_menu'\'')\->items\->toArray() as $data) {\n                \/\/ Filtrar por option_key (opciones del plan)\n                if (!empty($data->data['\''option_key'\'']) \&\& (int) app_cfg($data->data['\''option_key'\''], 0) !== 1) {\n                    continue;\n                }/' app/Http/Controllers/V1/Customer/General/BootstrapController.php
    echo "7/7 OK - Filtro option_key en bootstrap del customer"
fi

# ============================================================
# 8. Propagar option_key en el menu del customer portal
# ============================================================
# El AppServiceProvider::generateMenu ya propaga option_key y filtra
# Pero el customer_portal_menu se genera con Menu::make en addMenus
# Hay que asegurar que option_key se propaga en generateMenu para customer_menu también
# Ya está hecho en el AppServiceProvider que ya tenemos

echo ""
echo "=== COMPLETADO ==="
echo "Ejecutar:"
echo "  git add . && git commit -m 'Portal cliente: añadir proformas y albaranes con filtro por plan' && git push origin main"
echo "  cd ~/onfactu-produccion-20251125/onfactu && docker build --no-cache --build-arg CACHEBUST=\$(date +%s) -t invoiceshelf-app . 2>&1 | tail -3"
