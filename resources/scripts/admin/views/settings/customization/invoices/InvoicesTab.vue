<template>
  <InvoicesTabInvoiceNumber />

  <BaseDivider class="my-8" />

  <InvoicesTabDueDate />

  <BaseDivider class="my-8" />

  <InvoicesTabRetrospective />

  <BaseDivider class="my-8" />

  <!-- Formatos por defecto: SOLO asistencia -->
  <InvoicesTabDefaultFormats v-if="isAsistencia" />

  <!-- Adjuntar factura: SOLO asistencia (sin líneas sueltas) -->
  <template v-if="isAsistencia">
    <BaseDivider class="mt-6 mb-2" />
    <ul class="divide-y divide-gray-200">
      <BaseSwitchSection
        v-model="sendAsAttachmentField"
        :title="$t('settings.customization.invoices.invoice_email_attachment')"
        :description="$t('settings.customization.invoices.invoice_email_attachment_setting_description')"
      />
    </ul>
  </template>
</template>

<script setup>
import { computed, reactive, inject, watch } from 'vue'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useUserStore } from '@/scripts/admin/stores/user'
import InvoicesTabInvoiceNumber from './InvoicesTabInvoiceNumber.vue'
import InvoicesTabRetrospective from './InvoicesTabRetrospective.vue'
import InvoicesTabDueDate from './InvoicesTabDueDate.vue'
import InvoicesTabDefaultFormats from './InvoicesTabDefaultFormats.vue'

const companyStore = useCompanyStore()
const userStore = useUserStore()
const utils = inject('utils')

const isAsistencia = computed(() =>
  ((userStore.currentUser?.role || '') + '').trim().toLowerCase() === 'asistencia'
)

const invoiceSettings = reactive({
  invoice_email_attachment: null,
})

// Mezcla inicial desde settings de compañía
utils.mergeSettings(invoiceSettings, { ...companyStore.selectedCompanySettings })

// Si llega vacío/indefinido desde el store, fuerza 'YES' (solo cuando sea null/undefined)
watch(
  () => companyStore.selectedCompanySettings?.invoice_email_attachment,
  (val) => {
    if (val === null || val === undefined || val === '') {
      invoiceSettings.invoice_email_attachment = 'YES'
    } else {
      invoiceSettings.invoice_email_attachment = String(val).toUpperCase()
    }
  },
  { immediate: true }
)

// El switch trabaja en booleano, pero persistimos 'YES'/'NO'
const sendAsAttachmentField = computed({
  get: () => (invoiceSettings.invoice_email_attachment ?? 'YES').toUpperCase() !== 'NO',
  set: async (checked) => {
    const value = checked ? 'YES' : 'NO'
    invoiceSettings.invoice_email_attachment = value
    await companyStore.updateCompanySettings({
      data: { settings: { invoice_email_attachment: value } },
      message: 'general.setting_updated',
    })
  },
})
</script>
