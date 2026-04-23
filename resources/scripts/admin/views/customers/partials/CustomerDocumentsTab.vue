<template>
  <div>
    <BaseEmptyPlaceholder
      v-if="!isLoading && !rows.length"
      :title="emptyTitle"
      :description="emptyDescription"
    >
      <SatelliteIcon class="mt-5 mb-4" />
    </BaseEmptyPlaceholder>

    <div v-if="rows.length || isLoading">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th
                v-for="col in columns"
                :key="col.key"
                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                :class="col.key === 'total' ? 'text-right' : ''"
              >
                {{ col.label }}
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="row in rows" :key="row.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm text-gray-900">
                {{ row.formatted_date || '-' }}
              </td>
              <td class="px-4 py-3 text-sm">
                <router-link
                  v-if="viewRoute"
                  :to="`/admin/${viewRoute}/${row.id}/view`"
                  class="font-medium text-primary-500 hover:text-primary-700"
                >
                  {{ row.number_label }}
                </router-link>
                <span v-else class="font-medium">{{ row.number_label }}</span>
              </td>
              <td v-if="hasStatusColumn" class="px-4 py-3 text-sm">
                <BaseInvoiceStatusBadge :status="row.status" class="px-3 py-1">
                  <BaseInvoiceStatusLabel :status="row.status" />
                </BaseInvoiceStatusBadge>
              </td>
              <td class="px-4 py-3 text-sm font-medium text-gray-900 text-right">
                <BaseFormatMoney :amount="row.total || row.amount || 0" :currency="row.currency" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Paginación simple -->
      <div v-if="lastPage > 1" class="flex justify-center mt-4 gap-2">
        <BaseButton
          v-for="p in lastPage"
          :key="p"
          size="sm"
          :variant="p === currentPage ? 'primary' : 'gray'"
          @click="loadPage(p)"
        >
          {{ p }}
        </BaseButton>
      </div>
    </div>

    <div v-if="isLoading" class="flex justify-center py-8">
      <LoadingIcon class="h-6 animate-spin text-primary-400" />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'
import LoadingIcon from '@/scripts/components/icons/LoadingIcon.vue'
import SatelliteIcon from '@/scripts/components/icons/empty/SatelliteIcon.vue'

const props = defineProps({
  customerId: { type: [Number, String], required: true },
  // 'invoices', 'estimates', 'proforma-invoices', 'delivery-notes', 'payments', 'expenses'
  docType: { type: String, required: true },
  // Ruta para enlace de vista (ej: 'invoices', 'proforma-invoices')
  viewRoute: { type: String, default: '' },
  emptyTitle: { type: String, default: 'Sin documentos' },
  emptyDescription: { type: String, default: '' },
  // Campo que contiene el número del documento
  numberField: { type: String, default: 'invoice_number' },
})

const rows = ref([])
const isLoading = ref(false)
const currentPage = ref(1)
const lastPage = ref(1)

// Pagos y Gastos no tienen estado → no mostramos esa columna en esas pestañas.
const hasStatusColumn = computed(() =>
  !['payments', 'expenses'].includes(props.docType)
)

// Columnas dinámicas en función del tipo de documento.
const columns = computed(() => {
  const cols = [
    { key: 'date', label: 'Fecha' },
    { key: 'number', label: 'Número' },
  ]
  if (hasStatusColumn.value) cols.push({ key: 'status', label: 'Estado' })
  cols.push({ key: 'total', label: 'Total' })
  return cols
})

// Mapeo docType → nombre del accessor de fecha formateada que devuelve el API.
// Cada tipo de documento usa su propio accessor; si se añaden tipos nuevos en
// el futuro, basta con añadir aquí su mapeo y el componente lo pintará bien.
const DATE_FIELD_BY_TYPE = {
  'invoices':          'formatted_invoice_date',
  'estimates':         'formatted_estimate_date',
  'proforma-invoices': 'formatted_proforma_invoice_date',
  'delivery-notes':    'formatted_delivery_note_date',
  'payments':          'formatted_payment_date',
  'expenses':          'formatted_expense_date',
}

function extractDate(item) {
  const primary = DATE_FIELD_BY_TYPE[props.docType]
  if (primary && item[primary]) return item[primary]
  // Fallbacks por si alguna instancia no devuelve el accessor específico.
  return (
    item.formatted_invoice_date ||
    item.formatted_estimate_date ||
    item.formatted_proforma_invoice_date ||
    item.formatted_delivery_note_date ||
    item.formatted_payment_date ||
    item.formatted_expense_date ||
    item.formatted_created_at ||
    ''
  )
}

async function loadPage(page = 1) {
  isLoading.value = true
  try {
    const response = await axios.get(`/api/v1/${props.docType}`, {
      params: {
        customer_id: props.customerId,
        page,
        limit: 10,
        orderByField: 'created_at',
        orderBy: 'desc',
      },
    })

    const data = response.data.data || []
    rows.value = data.map((item) => ({
      ...item,
      formatted_date: extractDate(item),
      number_label:
        item[props.numberField] ||
        item.invoice_number ||
        item.estimate_number ||
        item.proforma_invoice_number ||
        item.delivery_note_number ||
        item.payment_number ||
        `#${item.id}`,
      currency: item.currency || item.customer?.currency || null,
      total: item.total ?? item.amount ?? 0,
    }))

    currentPage.value = response.data.meta?.current_page || page
    lastPage.value = response.data.meta?.last_page || 1
  } catch (err) {
    console.error(`Error loading ${props.docType}:`, err)
    rows.value = []
  } finally {
    isLoading.value = false
  }
}

watch(() => props.customerId, () => { loadPage(1) })

onMounted(() => { loadPage(1) })
</script>
