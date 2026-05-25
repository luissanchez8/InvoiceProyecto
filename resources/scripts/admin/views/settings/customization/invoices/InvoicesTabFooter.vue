<template>
  <form @submit.prevent="submitForm">
    <h6 class="text-gray-900 text-lg font-medium">
      {{ $t('settings.customization.pdf_footer.title') }}
    </h6>
    <p class="mt-1 text-sm text-gray-500 mb-4">
      {{ $t('settings.customization.pdf_footer.description') }}
    </p>

    <BaseInputGroup
      :label="$t('settings.customization.pdf_footer.footer_text_label')"
      :help-text="$t('settings.customization.pdf_footer.footer_text_help')"
      class="mt-2 mb-4"
    >
      <BaseTextarea
        v-model="footerSettings.invoice_pdf_footer_text"
        rows="2"
      />
    </BaseInputGroup>

    <BaseInputGroup
      :label="$t('settings.customization.pdf_footer.legal_notice_label')"
      :help-text="$t('settings.customization.pdf_footer.legal_notice_help')"
      class="mt-2 mb-4"
    >
      <BaseTextarea
        v-model="footerSettings.invoice_pdf_legal_notice_text"
        rows="4"
      />
    </BaseInputGroup>

    <BaseButton
      :loading="isSaving"
      :disabled="isSaving"
      variant="primary"
      type="submit"
      class="mt-4"
    >
      <template #left="slotProps">
        <BaseIcon v-if="!isSaving" :class="slotProps.class" name="ArrowDownOnSquareIcon" />
      </template>
      {{ $t('settings.customization.save') }}
    </BaseButton>
  </form>
</template>

<script setup>
import { ref, reactive, inject } from 'vue'
import { useCompanyStore } from '@/scripts/admin/stores/company'

const companyStore = useCompanyStore()
const utils = inject('utils')

const isSaving = ref(false)

const footerSettings = reactive({
  invoice_pdf_footer_text: '',
  invoice_pdf_legal_notice_text: '',
})

utils.mergeSettings(footerSettings, {
  ...companyStore.selectedCompanySettings,
})

async function submitForm() {
  isSaving.value = true

  const data = {
    settings: {
      ...footerSettings,
    },
  }

  await companyStore.updateCompanySettings({
    data,
    message: 'settings.customization.invoices.invoice_settings_updated',
  })

  isSaving.value = false
}
</script>
