<!--
  Vista: Listado de Albaranes (Index)

  Muestra la lista paginada de albaranes de la empresa actual.
  Usa BaseTable con fetchData async (mismo patrón que invoices/Index.vue).
-->
<template>
  <BasePage>
    <!-- Cabecera con título y botón de crear -->
    <BasePageHeader :title="$t('delivery_notes')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('delivery_notes')" to="#" active />
      </BaseBreadcrumb>

      <template #actions>
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
          <BaseBadge :variant="getStatusColor(row.data.status)">
            {{ row.data.status }}
          </BaseBadge>
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
      </BaseTable>
    </div>
  </BasePage>
</template>

<script setup>
/**
 * Script del componente Index de albaranes.
 * Mismo patrón que invoices/Index.vue con fetchData async.
 */
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDeliveryNoteStore } from '@/scripts/admin/stores/delivery-note'
import ObservatoryIcon from '@/scripts/components/icons/empty/ObservatoryIcon.vue'

const deliveryNoteStore = useDeliveryNoteStore()
const { t } = useI18n()

const table = ref(null)
const isRequestOngoing = ref(true)
const activeTab = ref('')

let filters = reactive({
  status: '',
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
])

function getStatusColor(status) {
  const colors = {
    DRAFT: 'gray',
    SENT: 'primary',
    DELIVERED: 'success',
  }
  return colors[status] || 'gray'
}

/**
 * Callback async invocado por BaseTable para obtener datos paginados.
 */
async function fetchData({ page, filter, sort }) {
  let data = {
    status: filters.status,
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

function setStatusFilter(val) {
  if (activeTab.value == val.title) return
  activeTab.value = val.title
  filters.status = val.filter || ''
  table.value && table.value.refresh()
}
</script>
