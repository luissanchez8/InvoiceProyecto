<template>
  <EstimatesTabEstimateNumber />

  <BaseDivider class="my-8" />

  <EstimatesTabExpiryDate />

  <BaseDivider class="my-8" />

  <EstimatesTabConvertEstimate />

  <BaseDivider class="my-8" />

  <!-- “Formatos por defecto” solo asistencia -->
  <EstimatesTabDefaultFormats v-if="isAsistencia" />
  <BaseDivider class="my-8" v-if="isAsistencia" />

  <!-- “Enviar cotización como adjunto” solo asistencia -->
  <BaseDivider class="mt-6 mb-2" v-if="isAsistencia" />
  <ul class="divide-y divide-gray-200" v-if="isAsistencia">
    <BaseSwitchSection
      v-model="sendAsAttachmentField"
      :title="$t('settings.customization.estimates.estimate_email_attachment')"
      :description="
        $t(
          'settings.customization.estimates.estimate_email_attachment_setting_description'
        )
      "
    />
  </ul>
</template>

<script setup>
import { computed, reactive, inject } from 'vue'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useUserStore } from '@/scripts/admin/stores/user'
import EstimatesTabEstimateNumber from './EstimatesTabEstimateNumber.vue'
import EstimatesTabExpiryDate from './EstimatesTabExpiryDate.vue'
import EstimatesTabDefaultFormats from './EstimatesTabDefaultFormats.vue'
import EstimatesTabConvertEstimate from './EstimatesTabConvertEstimate.vue'

const utils = inject('utils')
const companyStore = useCompanyStore()
const userStore = useUserStore()

const isAsistencia = computed(() => userStore.currentUser?.role === 'asistencia')

const estimateSettings = reactive({
  // por defecto ACTIVADO
  estimate_email_attachment: 'YES',
})

utils.mergeSettings(estimateSettings, {
  ...companyStore.selectedCompanySettings,
})

if (!estimateSettings.estimate_email_attachment) {
  estimateSettings.estimate_email_attachment = 'YES'
}

const sendAsAttachmentField = computed({
  get: () => estimateSettings.estimate_email_attachment === 'YES',
  set: async (newValue) => {
    const value = newValue ? 'YES' : 'NO'
    const data = { settings: { estimate_email_attachment: value } }
    estimateSettings.estimate_email_attachment = value
    await companyStore.updateCompanySettings({ data, message: 'general.setting_updated' })
  },
})
</script>
