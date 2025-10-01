<template>
  <BaseModal
    :show="modalActive"
    @close="closeSendPaymentModal"
    @open="setInitialData"
  >
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalTitle }}
        <BaseIcon
          name="XMarkIcon"
          class="w-6 h-6 text-gray-500 cursor-pointer"
          @click="closeSendPaymentModal"
        />
      </div>
    </template>

    <!-- ÚNICO FORM, sin previsualización -->
    <form>
      <div class="px-8 py-8 sm:p-6">
        <BaseInputGrid layout="one-column" class="col-span-7">
          <!-- De (oculto) -->
          <BaseInputGroup
            :label="$t('general.from')"
            class="hidden"
            aria-hidden="true"
            :error="v$.from.$error && v$.from.$errors[0].$message"
          >
            <BaseInput
              v-model="paymentMailForm.from"
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
              v-model="paymentMailForm.to"
              type="text"
              :invalid="v$.to.$error"
              @input="v$.to.$touch()"
            />
          </BaseInputGroup>

          <!-- Asunto -->
          <BaseInputGroup
            :error="v$.subject.$error && v$.subject.$errors[0].$message"
            :label="$t('general.subject')"
            required
          >
            <BaseInput
              v-model="paymentMailForm.subject"
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
              v-model="paymentMailForm.body"
              :fields="paymentMailFields"
            />
          </BaseInputGroup>
        </BaseInputGrid>
      </div>

      <div class="z-0 flex justify-end p-4 border-t border-gray-200 border-solid">
        <BaseButton
          class="mr-3"
          variant="primary-outline"
          type="button"
          @click="closeSendPaymentModal"
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
          <BaseIcon v-if="!isLoading" name="PaperAirplaneIcon" class="h-5 mr-2" />
          {{ $t('general.send') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { required, email, helpers } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { ref, reactive, computed } from 'vue'
import { usePaymentStore } from '@/scripts/admin/stores/payment'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useModalStore } from '@/scripts/stores/modal'
import { useMailDriverStore } from '@/scripts/admin/stores/mail-driver'
import { useDialogStore } from '@/scripts/stores/dialog'

const paymentStore = usePaymentStore()
const companyStore = useCompanyStore()
const modalStore = useModalStore()
const notificationStore = useNotificationStore()
const mailDriversStore = useMailDriverStore()
const dialogStore = useDialogStore()

const { t } = useI18n()
const isLoading = ref(false)

const paymentMailFields = ref([
  'customer',
  'customerCustom',
  'payments',
  'paymentsCustom',
  'company',
])

const paymentMailForm = reactive({
  id: null,
  from: null,
  to: null,
  subject: t('payments.new_payment'),
  body: null,
})

const modalActive = computed(() => {
  return modalStore.active && modalStore.componentName === 'SendPaymentModal'
})

const modalTitle = computed(() => modalStore.title)
const modalData  = computed(() => modalStore.data)

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

const v$ = useVuelidate(rules, paymentMailForm)

async function setInitialData() {
  const admin = await companyStore.fetchBasicMailConfig()
  paymentMailForm.id = modalStore.id

  if (admin?.data) paymentMailForm.from = admin.data.from_mail
  if (modalData.value) paymentMailForm.to = modalData.value.customer.email

  paymentMailForm.body = companyStore.selectedCompanySettings.payment_mail_body
}

async function sendNow() {
  v$.value.$touch()
  if (v$.value.$invalid) return

  try {
    isLoading.value = true
    const response = await paymentStore.sendEmail(paymentMailForm)
    isLoading.value = false

    if (response.data?.success) {
      closeSendPaymentModal()
      return true
    }

    notificationStore.showNotification({
      type: 'error',
      message: t('payments.something_went_wrong'),
    })
  } catch (error) {
    isLoading.value = false
    notificationStore.showNotification({
      type: 'error',
      message: t('payments.something_went_wrong'),
    })
  }
}

function closeSendPaymentModal() {
  // limpiar validaciones/estado y cerrar
  setTimeout(() => {
    v$.value.$reset()
    modalStore.resetModalData()
  }, 300)
}
</script>