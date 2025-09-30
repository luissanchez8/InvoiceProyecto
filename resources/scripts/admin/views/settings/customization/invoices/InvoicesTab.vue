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

// Mezcla inicial (si ya está cargado)
utils.mergeSettings(invoiceSettings, {
  ...companyStore.selectedCompanySettings,
})

/**
 * Mantén sincronizado el valor y aplica por defecto 'YES' cuando venga vacío.
 * Evita que el switch pinte OFF en el primer render.
 */
watch(
  () => companyStore.selectedCompanySettings?.invoice_email_attachment,
  async (val) => {
    const normalized = (val ?? '').toString().toUpperCase()

    if (!normalized) {
      // Forzamos ON localmente
      invoiceSettings.invoice_email_attachment = 'YES'

      // (Opcional) persiste en BD la primera vez:
      // await companyStore.updateCompanySettings({
      //   data: { settings: { invoice_email_attachment: 'YES' } },
      //   message: 'general.setting_updated',
      // })
    } else {
      invoiceSettings.invoice_email_attachment = normalized
    }
  },
  { immediate: true }
)

const sendAsAttachmentField = computed({
  // Todo lo que NO sea 'NO' => ON
  get: () =>
    String(invoiceSettings.invoice_email_attachment || 'YES')
      .toUpperCase() !== 'NO',
  set: async (newValue) => {
    const value = newValue ? 'YES' : 'NO'
    invoiceSettings.invoice_email_attachment = value
    await companyStore.updateCompanySettings({
      data: { settings: { invoice_email_attachment: value } },
      message: 'general.setting_updated',
    })
  },
})
</script>
