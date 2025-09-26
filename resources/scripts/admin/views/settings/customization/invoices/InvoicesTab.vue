<template>
  <InvoicesTabInvoiceNumber />

  <BaseDivider class="my-8" />

  <InvoicesTabDueDate />

  <BaseDivider class="my-8" />

  <InvoicesTabRetrospective />

  <BaseDivider class="my-8" />

  <!-- “Formatos por defecto” solo asistencia -->
  <InvoicesTabDefaultFormats v-if="isAsistencia" />
  <BaseDivider class="my-8" v-if="isAsistencia" />

  <!-- “Enviar factura como adjunto” solo asistencia -->
  <BaseDivider class="mt-6 mb-2" v-if="isAsistencia" />
  <ul class="divide-y divide-gray-200" v-if="isAsistencia">
    <BaseSwitchSection
      v-model="sendAsAttachmentField"
      :title="$t('settings.customization.invoices.invoice_email_attachment')"
      :description="
        $t(
          'settings.customization.invoices.invoice_email_attachment_setting_description'
        )
      "
    />
  </ul>
</template>

<script setup>
import { computed, reactive, inject } from 'vue'
import { useCompanyStore } from '@/scripts/admin/stores/company'

const companyStore = useCompanyStore()
const utils = inject('utils')

const isAsistencia = computed(() => companyStore.currentUser?.role === 'asistencia')

const invoiceSettings = reactive({ invoice_email_attachment: null })
utils.mergeSettings(invoiceSettings, { ...companyStore.selectedCompanySettings })

// por defecto: ON (YES)
const sendAsAttachmentField = computed({
  get: () => invoiceSettings.invoice_email_attachment !== 'NO',
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

<template>
  <!-- … tus secciones … -->

  <!-- SOLO asistencia -->
  <BaseDivider class="mt-6 mb-2" />
  <ul class="divide-y divide-gray-200" v-if="isAsistencia">
    <BaseSwitchSection
      v-model="sendAsAttachmentField"
      :title="$t('settings.customization.invoices.invoice_email_attachment')"
      :description="$t('settings.customization.invoices.invoice_email_attachment_setting_description')"
    />
  </ul>
</template>
