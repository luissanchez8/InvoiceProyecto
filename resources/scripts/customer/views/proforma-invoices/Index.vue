<template>
  <BasePage>
    <BasePageHeader :title="'Facturas Proforma'">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" :to="`/${globalStore.companySlug}/customer/dashboard`" />
        <BaseBreadcrumbItem :title="'Facturas Proforma'" to="#" active />
      </BaseBreadcrumb>
    </BasePageHeader>
    <BaseEmptyPlaceholder v-if="showEmptyScreen" :title="'No hay facturas proforma'" :description="'Aquí aparecerán tus facturas proforma.'">
      <MoonwalkerIcon class="mt-5 mb-4" />
    </BaseEmptyPlaceholder>
    <div v-show="!showEmptyScreen" class="relative table-container">
      <BaseTable ref="table" :data="fetchData" :columns="itemColumns" :placeholder-count="5" class="mt-10">
        <template #cell-created_at="{ row }">{{ row.data.formatted_created_at }}</template>
        <template #cell-number="{ row }">
          <router-link :to="{ path: `proforma-invoices/${row.data.id}/view` }" class="font-medium text-primary-500">
            {{ row.data.proforma_invoice_number || row.data.invoice_number }}
          </router-link>
        </template>
        <template #cell-total="{ row }">
          <BaseFormatMoney :amount="row.data.total" :currency="row.data.customer ? row.data.customer.currency : null" />
        </template>
        <template #cell-status="{ row }">
          <BaseInvoiceStatusBadge :status="row.data.status" class="px-3 py-1">
            <BaseInvoiceStatusLabel :status="row.data.status" />
          </BaseInvoiceStatusBadge>
        </template>
        <template #cell-actions="{ row }">
          <BaseDropdown>
            <template #activator><BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-gray-500" /></template>
            <router-link :to="`proforma-invoices/${row.data.id}/view`">
              <BaseDropdownItem><BaseIcon name="EyeIcon" class="h-5 mr-3 text-gray-600" />{{ $t('general.view') }}</BaseDropdownItem>
            </router-link>
          </BaseDropdown>
        </template>
      </BaseTable>
    </div>
  </BasePage>
</template>
<script setup>
import { useProformaInvoiceStore } from '@/scripts/customer/stores/proforma-invoice'
import BaseTable from '@/scripts/components/base/base-table/BaseTable.vue'
import { ref, computed } from 'vue'
import { useGlobalStore } from '@/scripts/customer/stores/global'
import MoonwalkerIcon from '@/scripts/components/icons/empty/MoonwalkerIcon.vue'
import { useI18n } from 'vue-i18n'
const { t } = useI18n()
const table = ref(null)
let isFetchingInitialData = ref(true)
const proformaStore = useProformaInvoiceStore()
const globalStore = useGlobalStore()
const itemColumns = computed(() => [
  { key: 'created_at', label: 'Fecha', tdClass: 'font-medium text-gray-900' },
  { key: 'number', label: 'Número' },
  { key: 'status', label: t('invoices.status') },
  { key: 'total', label: t('invoices.total') },
  { key: 'actions', thClass: 'text-right', tdClass: 'text-right text-sm font-medium', sortable: false },
])
const showEmptyScreen = computed(() => !proformaStore.totalProformas && !isFetchingInitialData.value)
async function fetchData({ page, sort }) {
  isFetchingInitialData.value = true
  let response = await proformaStore.fetchProformas({ orderByField: sort.fieldName || 'created_at', orderBy: sort.order || 'desc', page }, globalStore.companySlug)
  isFetchingInitialData.value = false
  return { data: response.data.data, pagination: { totalPages: response.data.meta.last_page, currentPage: page, totalCount: response.data.meta.total, limit: 10 } }
}
</script>
