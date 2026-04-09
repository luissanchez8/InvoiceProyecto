<!--
  Vista: Listado de Facturas Proforma (Index)

  Muestra la lista paginada de facturas proforma de la empresa actual.
  Usa BaseTable con fetchData async (mismo patrón que invoices/Index.vue).
-->
<template>
  <SendInvoiceModal />
  <BasePage>
    <!-- Cabecera con título y botón de crear -->
    <BasePageHeader :title="$t('proforma_invoices')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('proforma_invoices')" to="#" active />
      </BaseBreadcrumb>

      <template #actions>
        <BaseButton
          v-show="proformaInvoiceStore.proformaInvoiceTotalCount"
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

        <router-link to="proforma-invoices/create">
          <BaseButton variant="primary" class="ml-4">
            <template #left="slotProps">
              <BaseIcon name="PlusIcon" :class="slotProps.class" />
            </template>
            {{ $t('new_proforma_invoice') }}
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

      <BaseInputGroup :label="$t('proforma_invoices') + ' Nº'">
        <BaseInput v-model="filters.proforma_invoice_number">
          <template #left="slotProps">
            <BaseIcon name="HashtagIcon" :class="slotProps.class" />
          </template>
        </BaseInput>
      </BaseInputGroup>
    </BaseFilterWrapper>

    <!-- Placeholder si no hay datos -->
    <BaseEmptyPlaceholder
      v-show="showEmptyScreen"
      :title="$t('no_proforma_invoices')"
      :description="$t('list_of_proforma_invoices')"
    >
      <ObservatoryIcon class="mt-5 mb-4" />

      <template #actions>
        <BaseButton
          variant="primary-outline"
          @click="$router.push('/admin/proforma-invoices/create')"
        >
          <template #left="slotProps">
            <BaseIcon name="PlusIcon" :class="slotProps.class" />
          </template>
          {{ $t('add_new_proforma_invoice') }}
        </BaseButton>
      </template>
    </BaseEmptyPlaceholder>

    <!-- Tabla de datos -->
    <div v-show="!showEmptyScreen" class="relative table-container">
      <!-- Tabs de estado -->
      <div class="relative flex items-center justify-between h-10 mt-5 list-none border-b-2 border-gray-200 border-solid">
        <BaseTabGroup class="-mb-5" @change="setStatusFilter">
          <BaseTab :title="$t('general.all')" filter="" />
          <BaseTab :title="$t('general.draft')" filter="DRAFT" />
          <BaseTab :title="$t('general.sent')" filter="SENT" />
          <BaseTab :title="$t('estimates.accepted')" filter="ACCEPTED" />
          <BaseTab :title="$t('estimates.rejected')" filter="REJECTED" />
        </BaseTabGroup>
      </div>

      <!-- BaseTable con fetchData async -->
      <BaseTable
        ref="table"
        :data="fetchData"
        :columns="proformaColumns"
        :placeholder-count="5"
        :key="tableKey"
        class="mt-10"
      >
        <!-- Número de proforma (enlace a vista detalle) -->
        <template #cell-proforma_invoice_number="{ row }">
          <router-link
            :to="{ path: `proforma-invoices/${row.data.id}/view` }"
            class="font-medium text-primary-500"
          >
            {{ row.data.proforma_invoice_number }}
          </router-link>
        </template>

        <!-- Nombre del cliente -->
        <template #cell-name="{ row }">
          <BaseText :text="row.data.customer?.name || '-'" />
        </template>

        <!-- Fecha de la proforma -->
        <template #cell-proforma_invoice_date="{ row }">
          {{ row.data.formattedProformaInvoiceDate }}
        </template>

        <!-- Estado con badge -->
        <template #cell-status="{ row }">
          <BaseInvoiceStatusBadge :status="row.data.status" class="px-3 py-1">
            <BaseInvoiceStatusLabel :status="row.data.status" />
          </BaseInvoiceStatusBadge>
        </template>

        <!-- Total formateado -->
        <template #cell-total="{ row }">
          <BaseFormatMoney
            :amount="row.data.total"
            :currency="row.data.customer?.currency"
          />
        </template>

        <!-- Acciones (dropdown 3 puntos) -->
        <template #cell-actions="{ row }">
          <ProformaInvoiceDropdown :row="row.data" :table="table" />
        </template>
      </BaseTable>
    </div>
  </BasePage>
</template>

<script setup>
/**
 * Script del componente Index de facturas proforma.
 * Sigue el patrón exacto de invoices/Index.vue:
 * - fetchData como función async para BaseTable
 * - Tabs de filtro por estado
 * - Filtros avanzados (cliente, estado, fechas, número)
 * - Columnas con row.data accessor
 */
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useProformaInvoiceStore } from '@/scripts/admin/stores/proforma-invoice'
import { debouncedWatch } from '@vueuse/core'
import ProformaInvoiceDropdown from '@/scripts/admin/components/dropdowns/ProformaInvoiceIndexDropdown.vue'
import ObservatoryIcon from '@/scripts/components/icons/empty/ObservatoryIcon.vue'
import SendInvoiceModal from '@/scripts/admin/components/modal-components/SendInvoiceModal.vue'

const proformaInvoiceStore = useProformaInvoiceStore()
const { t } = useI18n()

// Refs locales
const table = ref(null)
const tableKey = ref(0)
const isRequestOngoing = ref(true)
const activeTab = ref('')
const showFilters = ref(false)

// Opciones de estado para el multiselect de filtros
const statusOptions = ref([
  { label: t('general.draft'), value: 'DRAFT' },
  { label: t('general.sent'), value: 'SENT' },
  { label: t('estimates.accepted'), value: 'ACCEPTED' },
  { label: t('estimates.rejected'), value: 'REJECTED' },
])

// Filtros reactivos
let filters = reactive({
  customer_id: '',
  status: '',
  from_date: '',
  to_date: '',
  proforma_invoice_number: '',
})

// Mostrar placeholder cuando no hay datos
const showEmptyScreen = computed(
  () => !proformaInvoiceStore.proformaInvoiceTotalCount && !isRequestOngoing.value
)

/**
 * Definición de columnas de la tabla.
 * Cada key se corresponde con un slot #cell-{key} del template.
 */
const proformaColumns = computed(() => [
  {
    key: 'proforma_invoice_date',
    label: t('pdf_invoice_date_short'),
    thClass: 'extra',
    tdClass: 'font-medium',
  },
  { key: 'proforma_invoice_number', label: 'Nº' },
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
 * Callback async que BaseTable invoca para obtener datos paginados.
 * Devuelve { data, pagination } con el formato que espera BaseTable.
 */
async function fetchData({ page, filter, sort }) {
  let data = {
    customer_id: filters.customer_id,
    status: filters.status,
    from_date: filters.from_date,
    to_date: filters.to_date,
    proforma_invoice_number: filters.proforma_invoice_number,
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  isRequestOngoing.value = true
  let response = await proformaInvoiceStore.fetchProformaInvoices(data)
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

/** Cambia el filtro de estado al pulsar un tab */
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
    case 'ACCEPTED':
      activeTab.value = t('estimates.accepted')
      break
    case 'REJECTED':
      activeTab.value = t('estimates.rejected')
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
  filters.proforma_invoice_number = ''
  activeTab.value = t('general.all')
}
</script>
