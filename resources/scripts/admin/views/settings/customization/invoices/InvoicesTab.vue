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

// rol desde userStore
const isAsistencia = computed(() =>
  ((userStore.currentUser?.role || '') + '').trim().toLowerCase() === 'asistencia'
)

const estimateSettings = reactive({
  estimate_email_attachment: null,
})

utils.mergeSettings(estimateSettings, {
  ...companyStore.selectedCompanySettings,
})

// ✅ Por defecto ON si viene vacío
if (estimateSettings.estimate_email_attachment == null) {
  estimateSettings.estimate_email_attachment = 'YES'
}

const sendAsAttachmentField = computed({
  // ON salvo que sea explícitamente 'NO'
  get: () => (estimateSettings.estimate_email_attachment ?? 'YES') === 'YES',
  set: async (newValue) => {
    const value = newValue ? 'YES' : 'NO'
    estimateSettings.estimate_email_attachment = value
    await companyStore.updateCompanySettings({
      data: { settings: { estimate_email_attachment: value } },
      message: 'general.setting_updated',
    })
  },
})
</script>
