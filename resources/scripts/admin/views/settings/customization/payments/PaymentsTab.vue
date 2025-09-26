<template>
  <PaymentsTabPaymentNumber />

  <BaseDivider class="my-8" />

  <!-- “Formatos por defecto” solo asistencia -->
  <PaymentsTabDefaultFormats v-if="isAsistencia" />
  <BaseDivider class="my-8" v-if="isAsistencia" />

  <!-- “Enviar pago como adjunto” solo asistencia -->
  <BaseDivider class="mt-6 mb-2" v-if="isAsistencia" />
  <ul class="divide-y divide-gray-200" v-if="isAsistencia">
    <BaseSwitchSection
      v-model="sendAsAttachmentField"
      :title="$t('settings.customization.payments.payment_email_attachment')"
      :description="
        $t(
          'settings.customization.payments.payment_email_attachment_setting_description'
        )
      "
    />
  </ul>
</template>

<script setup>
import { computed, reactive, inject } from 'vue'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useUserStore } from '@/scripts/admin/stores/user'
import PaymentsTabPaymentNumber from './PaymentsTabPaymentNumber.vue'
import PaymentsTabDefaultFormats from './PaymentsTabDefaultFormats.vue'

const utils = inject('utils')
const companyStore = useCompanyStore()
const userStore = useUserStore()

const isAsistencia = computed(() => userStore.currentUser?.role === 'asistencia')

const paymentSettings = reactive({
  // por defecto ACTIVADO
  payment_email_attachment: 'YES',
})

utils.mergeSettings(paymentSettings, {
  ...companyStore.selectedCompanySettings,
})

if (!paymentSettings.payment_email_attachment) {
  paymentSettings.payment_email_attachment = 'YES'
}

const sendAsAttachmentField = computed({
  get: () => paymentSettings.payment_email_attachment === 'YES',
  set: async (newValue) => {
    const value = newValue ? 'YES' : 'NO'
    const data = { settings: { payment_email_attachment: value } }
    paymentSettings.payment_email_attachment = value
    await companyStore.updateCompanySettings({ data, message: 'general.setting_updated' })
  },
})
</script>
