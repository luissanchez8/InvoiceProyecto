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
        v-model="sendAsAttachment"
        :title="$t('settings.customization.invoices.invoice_email_attachment')"
        :description="$t('settings.customization.invoices.invoice_email_attachment_setting_description')"
      />
    </ul>
  </template>
</template>

<script setup>
import { computed, reactive, inject, watch, ref, onMounted } from 'vue'
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

/** Booleano fuente de verdad para el switch (por defecto: ON) */
const sendAsAttachment = ref(true)

/** Sincroniza con settings cuando lleguen; si viene vacío => ON */
watch(
  () => companyStore.selectedCompanySettings?.invoice_email_attachment,
  (val) => {
    const normalized = String(val ?? '').toUpperCase()
    sendAsAttachment.value = normalized === '' ? true : normalized !== 'NO'
    invoiceSettings.invoice_email_attachment = sendAsAttachment.value ? 'YES' : 'NO'
  },
  { immediate: true }
)

/** Al cambiar el switch, persiste en BD si difiere */
watch(
  sendAsAttachment,
  async (on) => {
    const value = on ? 'YES' : 'NO'
    if (invoiceSettings.invoice_email_attachment !== value) {
      invoiceSettings.invoice_email_attachment = value
      await companyStore.updateCompanySettings({
        data: { settings: { invoice_email_attachment: value } },
        message: 'general.setting_updated',
      })
    }
  }
)

/** (Opcional) Guardar 'YES' en BD la primera vez si no había valor */
onMounted(async () => {
  const raw = companyStore.selectedCompanySettings?.invoice_email_attachment
  if (raw == null || raw === '') {
    await companyStore.updateCompanySettings({
      data: { settings: { invoice_email_attachment: 'YES' } },
      message: 'general.setting_updated',
    })
  }
})
</script>
