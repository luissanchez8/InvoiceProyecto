<template>
  <BaseModal
    :show="modalActive"
    @close="closeSendEstimateModal"
    @open="setInitialData"
  >
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}
        <BaseIcon
          name="XMarkIcon"
          class="h-6 w-6 text-gray-500 cursor-pointer"
          @click="closeSendEstimateModal"
        />
      </div>
    </template>

    <!-- ÚNICO FORM, sin previsualización -->
    <form>
      <div class="px-8 py-8 sm:p-6">
        <BaseInputGrid layout="one-column">
          <!-- De (oculto) -->
          <BaseInputGroup
            :label="$t('general.from')"
            class="hidden"
            aria-hidden="true"
            :error="v$.from.$error && v$.from.$errors[0].$message"
          >
            <BaseInput
              v-model="estimateMailForm.from"
              type="hidden"
              :invalid="v$.from.$error"
            />
          </BaseInputGroup>

          <!-- A -->
          <BaseInputGroup
            :label="$t('general.to')"
            required
            :error="v$.to.$error && v$.to.$errors[0].$message"
          >
            <BaseInput
              v-model="estimateMailForm.to"
              type="text"
              :invalid="v$.to.$error"
              @input="v$.to.$touch()"
            />
          </BaseInputGroup>

          <!-- Asunto -->
          <BaseInputGroup
            :label="$t('general.subject')"
            required
            :error="v$.subject.$error && v$.subject.$errors[0].$message"
          >
            <BaseInput
              v-model="estimateMailForm.subject"
              type="text"
              :invalid="v$.subject.$error"
              @input="v$.subject.$touch()"
            />
          </BaseInputGroup>

          <!-- Cuerpo (oculto) -->
          <BaseInputGroup
            :label="$t('general.body')"
            class="hidden"
            aria-hidden="true"
            :error="v$.body.$error && v$.body.$errors[0].$message"
          >
            <BaseCustomInput
              v-model="estimateMailForm.body"
              :fields="estimateMailFields"
            />
          </BaseInputGroup>
        </BaseInputGrid>
      </div>

      <div class="z-0 flex justify-end p-4 border-t border-gray-200 border-solid">
        <BaseButton
          class="mr-3"
          variant="primary-outline"
          type="button"
          @click="closeSendEstimateModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>

        <!-- Enviar directo -->
        <BaseButton
          :loading="isLoading"
          :disabled="isLoading"
          variant="primary"
          type="button"
          @click="sendNow"
        >
          <BaseIcon v-if="!isLoading" name="PaperAirplaneIcon" class="mr-2" />
          {{ $t('general.send') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>

<script setup>
import { computed, ref, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, email, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useModalStore } from '@/scripts/stores/modal'
import { useEstimateStore } from '@/scripts/admin/stores/estimate'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useMailDriverStore } from '@/scripts/admin/stores/mail-driver'

const modalStore = useModalStore()
const estimateStore = useEstimateStore()
const notificationStore = useNotificationStore()
const companyStore = useCompanyStore()
const mailDriverStore = useMailDriverStore()

const { t } = useI18n()
const isLoading = ref(false)

const estimateMailFields = ref([
  'customer',
  'customerCustom',
  'estimate',
  'estimateCustom',
  'company',
])

let estimateMailForm = reactive({
  id: null,
  from: null,
  to: null,
  subject: t('estimates.new_estimate'),
  body: null,
})

const emit = defineEmits(['update'])

const modalActive = computed(() => {
  return modalStore.active && modalStore.componentName === 'SendEstimateModal'
})

const modalData = computed(() => modalStore.data)

const rules = {
  from: {
    required: helpers.withMessage(t('validation.required'), required),
    email: helpers.withMessage(t('validation.email_incorrect'), email),
  },
  to: {
    required: helpers.withMessage(t('validation.required'), required),
    email: helpers.withMessage(t('validation.email_incorrect'), email),
  },
  subject: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  body: {
    required: helpers.withMessage(t('validation.required'), required),
  },
}

const v$ = useVuelidate(rules, computed(() => estimateMailForm))

async function setInitialData() {
  const admin = await companyStore.fetchBasicMailConfig()

  estimateMailForm.id = modalStore.id
  if (admin?.data) {
    estimateMailForm.from = admin.data.from_mail
  }
  if (modalData.value) {
    estimateMailForm.to = modalData.value.customer.email
  }

  estimateMailForm.body =
    companyStore.selectedCompanySettings.estimate_mail_body
}

async function sendNow() {
  v$.value.$touch()
  if (v$.value.$invalid) return

  try {
    isLoading.value = true
    const response = await estimateStore.sendEstimate(estimateMailForm)
    isLoading.value = false

    if (response.data?.success) {
      emit('update')
      closeSendEstimateModal()
      return true
    }

    notificationStore.showNotification({
      type: 'error',
      message: t('estimates.something_went_wrong'),
    })
  } catch (error) {
    isLoading.value = false
    notificationStore.showNotification({
      type: 'error',
      message: t('estimates.something_went_wrong'),
    })
  }
}

function closeSendEstimateModal() {
  modalStore.closeModal()
  setTimeout(() => v$.value.$reset(), 300)
}
</script>