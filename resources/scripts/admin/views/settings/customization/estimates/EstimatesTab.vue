<template>
  <EstimatesTabEstimateNumber />

  <BaseDivider class="my-8" />

  <EstimatesTabExpiryDate />

  <BaseDivider class="my-8" />

  <EstimatesTabConvertEstimate />

  <BaseDivider class="my-8" />

  <!-- Formatos por defecto: SOLO asistencia -->
  <EstimatesTabDefaultFormats v-if="isAsistencia" />

  <!-- Enviar estimación como adjunto: SOLO asistencia -->
  <BaseDivider class="mt-6 mb-2" />
  <ul class="divide-y divide-gray-200" v-if="isAsistencia">
    <BaseSwitchSection
      v-model="sendAsAttachmentField"
      :title="$t('settings.customization.estimates.estimate_email_attachment')"
      :description="$t('settings.customization.estimates.estimate_email_attachment_setting_description')"
    />
  </ul>
</template>

<script setup>
import { computed, reactive, inject } from 'vue'
import { useCompanyStore } from '@/scripts/admin/stores/company'

import EstimatesTabEstimateNumber from './EstimatesTabEstimateNumber.vue'
import EstimatesTabExpiryDate from './EstimatesTabExpiryDate.vue'
import EstimatesTabDefaultFormats from './EstimatesTabDefaultFormats.vue'
import EstimatesTabConvertEstimate from './EstimatesTabConvertEstimate.vue'

const companyStore = useCompanyStore()
const utils = inject('utils')

const isAsistencia = computed(() => companyStore.currentUser?.role === 'asistencia')

const estimateSettings = reactive({
  estimate_email_attachment: null,
})

utils.mergeSettings(estimateSettings, {
  ...companyStore.selectedCompanySettings,
})

// Por defecto ON
const sendAsAttachmentField = computed({
  get: () => estimateSettings.estimate_email_attachment !== 'NO',
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
