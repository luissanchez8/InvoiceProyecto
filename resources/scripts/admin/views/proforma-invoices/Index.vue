<!--
  Vista: Listado de Facturas Proforma (Index)

  Muestra la lista paginada de facturas proforma de la empresa actual.
  Usa BaseTable con fetchData async (mismo patrón que invoices/Index.vue).
-->
<template>
  <BasePage>
    <!-- Cabecera con título y botón de crear -->
    <BasePageHeader :title="$t('proforma_invoices')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('proforma_invoices')" to="#" active />
      </BaseBreadcrumb>

      <template #actions>
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

    <!-- Placeholder si no hay datos -->
    <BaseEmptyPlaceholder
      v-show="showEmptyScreen"
      :title="$t('proforma_invoices')"
      :description="$t('proforma_invoices')"
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
          <BaseTab title="Accepted" filter="ACCEPTED" />
          <BaseTab title="Rejected" filter="REJECTED" />
        </BaseTabGroup>
      </div>

      <!-- BaseTable con fetchData async -->
      <BaseTable
        ref="table"
        :data="fetchData"
        :columns="proformaColumns"
        :placeholder-count="5"
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
          <BaseBadge :variant="getStatusColor(row.data.status)">
            {{ row.data.status }}
          </BaseBadge>
        </template>

        <!-- Total formateado -->
        <template #cell-total="{ row }">
          <BaseFormatMoney
            :amount="row.data.total"
            :currency="row.data.customer?.currency"
          />
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
 * - Columnas con row.data accessor
 */
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useProformaInvoiceStore } from '@/scripts/admin/stores/proforma-invoice'
import ObservatoryIcon from '@/scripts/components/icons/empty/ObservatoryIcon.vue'

const proformaInvoiceStore = useProformaInvoiceStore()
const { t } = useI18n()

// Refs locales
const table = ref(null)
const isRequestOngoing = ref(true)
const activeTab = ref('')

// Filtros reactivos
let filters = reactive({
  status: '',
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
])

/**
 * Devuelve la variante de color del badge según el estado.
 */
function getStatusColor(status) {
  const colors = {
    DRAFT: 'gray',
    SENT: 'primary',
    VIEWED: 'info',
    ACCEPTED: 'success',
    REJECTED: 'danger',
  }
  return colors[status] || 'gray'
}

/**
 * Callback async que BaseTable invoca para obtener datos paginados.
 * Devuelve { data, pagination } con el formato que espera BaseTable.
 */
async function fetchData({ page, filter, sort }) {
  let data = {
    status: filters.status,
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

/** Cambia el filtro de estado al pulsar un tab */
function setStatusFilter(val) {
  if (activeTab.value == val.title) return
  activeTab.value = val.title
  filters.status = val.filter || ''
  table.value && table.value.refresh()
}
</script>
