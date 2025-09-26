<template>
  <PaymentsTabPaymentNumber />

  <BaseDivider class="my-8" />

  <!-- Formatos por defecto: SOLO asistencia -->
  <PaymentsTabDefaultFormats v-if="isAsistencia" />

  <!-- Enviar pago como adjunto: SOLO asistencia -->
  <BaseDivider class="mt-6 mb-2" />
  <ul class="divide-y divide-gray-200" v-if="isAsistencia">
    <BaseSwitchSection
      v-model="sendAsAttachmentField"
      :title="$t('settings.customization.payments.payment_email_attachment')"
      :description="$t('settings.customization.payments.payment_email_attachment_setting_description')"
    />
  </ul>
</template>

<script setup>
import { computed, reactive, inject } from 'vue'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import PaymentsTabPaymentNumber from './PaymentsTabPaymentNumber.vue'
import PaymentsTabDefaultFormats from './PaymentsTabDefaultFormats.vue'

const companyStore = useCompanyStore()
const utils = inject('utils')

const isAsistencia = computed(() => companyStore.currentUser?.role === 'asistencia')

const paymentSettings = reactive({
  payment_email_attachment: null,
})

utils.mergeSettings(paymentSettings, {
  ...companyStore.selectedCompanySettings,
})

// Por defecto ON
const sendAsAttachmentField = computed({
  get: () => paymentSettings.payment_email_attachment !== 'NO',
  set: async (newValue) => {
    const value = newValue ? 'YES' : 'NO'
    paymentSettings.payment_email_attachment = value
    await companyStore.updateCompanySettings({
      data: { settings: { payment_email_attachment: value } },
      message: 'general.setting_updated',
    })
  },
})
</script>
