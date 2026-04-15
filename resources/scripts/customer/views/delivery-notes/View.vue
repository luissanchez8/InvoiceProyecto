<template>
  <BasePage>
    <BasePageHeader :title="deliveryNote.delivery_note_number || deliveryNote.invoice_number || ''">
      <template #actions>
        <BaseButton variant="primary-outline" tag="a" :href="`/delivery-notes/pdf/${deliveryNote.unique_hash}`" download>
          <template #left="slotProps"><BaseIcon name="ArrowDownTrayIcon" :class="slotProps.class" /></template>
          Descargar PDF
        </BaseButton>
      </template>
    </BasePageHeader>
    <div v-if="deliveryNote.id" class="mt-6 bg-white rounded-lg shadow p-6">
      <div class="grid grid-cols-2 gap-4">
        <div><p class="text-sm text-gray-500">Número</p><p class="font-medium">{{ deliveryNote.delivery_note_number || deliveryNote.invoice_number }}</p></div>
        <div><p class="text-sm text-gray-500">Estado</p><BaseInvoiceStatusBadge :status="deliveryNote.status" class="px-3 py-1"><BaseInvoiceStatusLabel :status="deliveryNote.status" /></BaseInvoiceStatusBadge></div>
        <div><p class="text-sm text-gray-500">Fecha</p><p class="font-medium">{{ deliveryNote.formatted_created_at }}</p></div>
        <div><p class="text-sm text-gray-500">Total</p><p class="font-medium"><BaseFormatMoney :amount="deliveryNote.total" :currency="deliveryNote.currency" /></p></div>
      </div>
    </div>
  </BasePage>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useDeliveryNoteStore } from '@/scripts/customer/stores/delivery-note'
import { useGlobalStore } from '@/scripts/customer/stores/global'
import { useRoute } from 'vue-router'
const deliveryNoteStore = useDeliveryNoteStore()
const globalStore = useGlobalStore()
const route = useRoute()
const deliveryNote = ref({})
onMounted(async () => {
  await deliveryNoteStore.fetchViewDeliveryNote({ id: route.params.id }, globalStore.companySlug)
  deliveryNote.value = deliveryNoteStore.selectedViewDeliveryNote
})
</script>
