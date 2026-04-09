<!--
  Vista: Listado de Albaranes (Index)

  Muestra la lista paginada de albaranes de la empresa actual.
  Usa BaseTable con fetchData async (mismo patrón que invoices/Index.vue).
-->
<template>
  <SendInvoiceModal />
  <BasePage>
    <!-- Cabecera con título y botón de crear -->
    <BasePageHeader :title="$t('delivery_notes')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('delivery_notes')" to="#" active />
      </BaseBreadcrumb>

      <template #actions>
        <BaseButton
          v-show="deliveryNoteStore.deliveryNoteTotalCount"
          variant="primary-outline"
          @click="toggleFilter"
        >
          {{ $t('general.filter') }}
          <template #right="slotProps">
            <BaseIcon
              v-if="!showFilters"
              name="FunnelIcon"
              :class="slotProps.class"
            />
            <BaseIcon v-else name="XMarkIcon" :class="slotProps.class" />
          </template>
        </BaseButton>

        <router-link to="delivery-notes/create">
          <BaseButton variant="primary" class="ml-4">
            <template #left="slotProps">
              <BaseIcon name="PlusIcon" :class="slotProps.class" />
            </template>
            {{ $t('new_delivery_note') }}
          </BaseButton>
        </router-link>
      </template>
    </BasePageHeader>

    <!-- Filtros -->
    <BaseFilterWrapper
      v-show="showFilters"
      :row-on-xl="true"
      @clear="clearFilter"
    >
      <BaseInputGroup :label="$t('customers.customer', 1)">
        <BaseCustomerSelectInput
          v-model="filters.customer_id"
          :placeholder="$t('customers.type_or_click')"
          value-prop="id"
          label="name"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('invoices.status')">
        <BaseMultiselect
          v-model="filters.status"
          :options="statusOptions"
          searchable
          :placeholder="$t('general.select_a_status')"
          @update:modelValue="setActiveTab"
          @remove="clearStatusSearch()"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('general.from')">
        <BaseDatePicker
          v-model="filters.from_date"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <div
        class="hidden w-8 h-0 mx-4 border border-gray-400 border-solid xl:block"
        style="margin-top: 1.5rem"
      />

      <BaseInputGroup :label="$t('general.to')" class="mt-2">
        <BaseDatePicker
          v-model="filters.to_date"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('delivery_notes') + ' Nº'">
        <BaseInput v-model="filters.delivery_note_number">
          <template #left="slotProps">
            <BaseIcon name="HashtagIcon" :class="slotProps.class" />
          </template>
        </BaseInput>
      </BaseInputGroup>
    </BaseFilterWrapper>

    <!-- Placeholder si no hay datos -->
    <BaseEmptyPlaceholder
      v-show="showEmptyScreen"
      :title="$t('delivery_notes')"
      :description="$t('delivery_notes')"
    >
      <ObservatoryIcon class="mt-5 mb-4" />
    </BaseEmptyPlaceholder>

    <!-- Tabla de datos -->
    <div v-show="!showEmptyScreen" class="relative table-container">
      <!-- Tabs de estado -->
      <div class="relative flex items-center justify-between h-10 mt-5 list-none border-b-2 border-gray-200 border-solid">
        <BaseTabGroup class="-mb-5" @change="setStatusFilter">
          <BaseTab :title="$t('general.all')" filter="" />
          <BaseTab :title="$t('general.draft')" filter="DRAFT" />
          <BaseTab :title="$t('general.sent')" filter="SENT" />
          <BaseTab :title="$t('status_delivered')" filter="DELIVERED" />
        </BaseTabGroup>
      </div>

      <!-- BaseTable con fetchData async -->
      <BaseTable
        ref="table"
        :data="fetchData"
        :columns="deliveryNoteColumns"
        :placeholder-count="5"
        :key="tableKey"
        class="mt-10"
      >
        <!-- Número de albarán (enlace a vista detalle) -->
        <template #cell-delivery_note_number="{ row }">
          <router-link
            :to="{ path: `delivery-notes/${row.data.id}/view` }"
            class="font-medium text-primary-500"
          >
            {{ row.data.delivery_note_number }}
          </router-link>
        </template>

        <!-- Nombre del cliente -->
        <template #cell-name="{ row }">
          <BaseText :text="row.data.customer?.name || '-'" />
        </template>

        <!-- Fecha del albarán -->
        <template #cell-delivery_note_date="{ row }">
          {{ row.data.formattedDeliveryNoteDate }}
        </template>

        <!-- Estado con badge -->
        <template #cell-status="{ row }">
          <BaseInvoiceStatusBadge :status="row.data.status" class="px-3 py-1">
            <BaseInvoiceStatusLabel :status="row.data.status" />
          </BaseInvoiceStatusBadge>
        </template>

        <!-- Total formateado (oculto si show_prices = false) -->
        <template #cell-total="{ row }">
          <template v-if="row.data.show_prices">
            <BaseFormatMoney
              :amount="row.data.total"
              :currency="row.data.customer?.currency"
            />
          </template>
          <span v-else class="text-gray-400">—</span>
        </template>

        <!-- Acciones (dropdown 3 puntos) -->
        <template #cell-actions="{ row }">
          <DeliveryNoteDropdown :row="row.data" :table="table" />
        </template>
      </BaseTable>
    </div>
  </BasePage>
</template>

<script setup>
/**
 * Script del componente Index de albaranes.
 * Mismo patrón que invoices/Index.vue con fetchData async y filtros avanzados.
 */
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDeliveryNoteStore } from '@/scripts/admin/stores/delivery-note'
import { debouncedWatch } from '@vueuse/core'
import DeliveryNoteDropdown from '@/scripts/admin/components/dropdowns/DeliveryNoteIndexDropdown.vue'
import ObservatoryIcon from '@/scripts/components/icons/empty/ObservatoryIcon.vue'
import SendInvoiceModal from '@/scripts/admin/components/modal-components/SendInvoiceModal.vue'

const deliveryNoteStore = useDeliveryNoteStore()
const { t } = useI18n()

const table = ref(null)
const tableKey = ref(0)
const isRequestOngoing = ref(true)
const activeTab = ref('')
const showFilters = ref(false)

// Opciones de estado para el multiselect de filtros
const statusOptions = ref([
  { label: t('general.draft'), value: 'DRAFT' },
  { label: t('general.sent'), value: 'SENT' },
  { label: t('status_delivered'), value: 'DELIVERED' },
])

let filters = reactive({
  customer_id: '',
  status: '',
  from_date: '',
  to_date: '',
  delivery_note_number: '',
})

const showEmptyScreen = computed(
  () => !deliveryNoteStore.deliveryNoteTotalCount && !isRequestOngoing.value
)

const deliveryNoteColumns = computed(() => [
  {
    key: 'delivery_note_date',
    label: t('pdf_invoice_date_short'),
    thClass: 'extra',
    tdClass: 'font-medium',
  },
  { key: 'delivery_note_number', label: 'Nº' },
  { key: 'name', label: t('pdf_invoice_customer_data') },
  { key: 'status', label: 'Estado' },
  {
    key: 'total',
    label: 'Total',
    tdClass: 'font-medium text-gray-900',
  },
  {
    key: 'actions',
    label: t('invoices.action'),
    tdClass: 'text-right text-sm font-medium',
    thClass: 'text-right',
    sortable: false,
  },
])

// Observar cambios en filtros con debounce
debouncedWatch(
  filters,
  () => {
    setFilters()
  },
  { debounce: 500 }
)

/**
 * Callback async invocado por BaseTable para obtener datos paginados.
 */
async function fetchData({ page, filter, sort }) {
  let data = {
    customer_id: filters.customer_id,
    status: filters.status,
    from_date: filters.from_date,
    to_date: filters.to_date,
    delivery_note_number: filters.delivery_note_number,
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  isRequestOngoing.value = true
  let response = await deliveryNoteStore.fetchDeliveryNotes(data)
  isRequestOngoing.value = false

  return {
    data: response.data.data,
    pagination: {
      totalPages: response.data.meta?.last_page ?? 1,
      currentPage: page,
      totalCount: response.data.meta?.total ?? 0,
      limit: 10,
    },
  }
}

function refreshTable() {
  table.value && table.value.refresh()
}

function setFilters() {
  tableKey.value += 1
  refreshTable()
}

function setStatusFilter(val) {
  if (activeTab.value == val.title) return
  activeTab.value = val.title
  filters.status = val.filter || ''
  refreshTable()
}

/** Sincroniza el tab activo con el valor seleccionado en el multiselect */
function setActiveTab(val) {
  switch (val) {
    case 'DRAFT':
      activeTab.value = t('general.draft')
      break
    case 'SENT':
      activeTab.value = t('general.sent')
      break
    case 'DELIVERED':
      activeTab.value = t('status_delivered')
      break
    default:
      activeTab.value = t('general.all')
      break
  }
}

function clearStatusSearch() {
  filters.status = ''
  refreshTable()
}

function toggleFilter() {
  if (showFilters.value) {
    clearFilter()
  }
  showFilters.value = !showFilters.value
}

function clearFilter() {
  filters.customer_id = ''
  filters.status = ''
  filters.from_date = ''
  filters.to_date = ''
  filters.delivery_note_number = ''
  activeTab.value = t('general.all')
}
</script>
