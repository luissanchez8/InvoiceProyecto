<template>
  <BasePage>
    <BasePageHeader :title="proforma.proforma_invoice_number || proforma.invoice_number || ''">
      <template #actions>
        <BaseButton variant="primary-outline" tag="a" :href="`/proforma-invoices/pdf/${proforma.unique_hash}`" download>
          <template #left="slotProps"><BaseIcon name="ArrowDownTrayIcon" :class="slotProps.class" /></template>
          Descargar PDF
        </BaseButton>
      </template>
    </BasePageHeader>
    <div v-if="proforma.id" class="mt-6 bg-white rounded-lg shadow p-6">
      <div class="grid grid-cols-2 gap-4">
        <div><p class="text-sm text-gray-500">Número</p><p class="font-medium">{{ proforma.proforma_invoice_number || proforma.invoice_number }}</p></div>
        <div><p class="text-sm text-gray-500">Estado</p><BaseInvoiceStatusBadge :status="proforma.status" class="px-3 py-1"><BaseInvoiceStatusLabel :status="proforma.status" /></BaseInvoiceStatusBadge></div>
        <div><p class="text-sm text-gray-500">Fecha</p><p class="font-medium">{{ proforma.formatted_created_at }}</p></div>
        <div><p class="text-sm text-gray-500">Total</p><p class="font-medium"><BaseFormatMoney :amount="proforma.total" :currency="proforma.currency" /></p></div>
      </div>
    </div>
  </BasePage>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useProformaInvoiceStore } from '@/scripts/customer/stores/proforma-invoice'
import { useGlobalStore } from '@/scripts/customer/stores/global'
import { useRoute } from 'vue-router'
const proformaStore = useProformaInvoiceStore()
const globalStore = useGlobalStore()
const route = useRoute()
const proforma = ref({})
onMounted(async () => {
  await proformaStore.fetchViewProforma({ id: route.params.id }, globalStore.companySlug)
  proforma.value = proformaStore.selectedViewProforma
})
</script>
