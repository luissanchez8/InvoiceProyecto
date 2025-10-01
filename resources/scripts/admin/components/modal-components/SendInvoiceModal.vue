<template>
  <BaseModal
    :show="modalActive"
    @close="closeSendInvoiceModal"
    @open="setInitialData"
  >
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalTitle }}
        <BaseIcon
          name="XMarkIcon"
          class="w-6 h-6 text-gray-500 cursor-pointer"
          @click="closeSendInvoiceModal"
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
              v-model="invoiceMailForm.from"
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
              v-model="invoiceMailForm.to"
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
              v-model="invoiceMailForm.subject"
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
            <!-- Mantengo el v-model para que el valor viaje -->
            <BaseCustomInput
              v-model="invoiceMailForm.body"
              :fields="invoiceMailFields"
            />
          </BaseInputGroup>
        </BaseInputGrid>
      </div>

      <div class="z-0 flex justify-end p-4 border-t border-gray-200 border-solid">
        <BaseButton
          class="mr-3"
          variant="primary-outline"
          type="button"
          @click="closeSendInvoiceModal"
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
          <template #left="slotProps">
            <BaseIcon
              v-if="!isLoading"
              :class="slotProps.class"
              name="PaperAirplaneIcon"
            />
          </template>
          {{ $t('general.send') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { useModalStore } from '@/scripts/stores/modal'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useI18n } from 'vue-i18n'
import { useInvoiceStore } from '@/scripts/admin/stores/invoice'
import { useVuelidate } from '@vuelidate/core'
import { required, email, helpers } from '@vuelidate/validators'
import { useMailDriverStore } from '@/scripts/admin/stores/mail-driver'

const modalStore = useModalStore()
const companyStore = useCompanyStore()
const notificationStore = useNotificationStore()
const invoiceStore = useInvoiceStore()
const mailDriverStore = useMailDriverStore()

const { t } = useI18n()
let isLoading = ref(false)

const emit = defineEmits(['update'])

const invoiceMailFields = ref([
  'customer',
  'customerCustom',
  'invoice',
  'invoiceCustom',
  'company',
])

const invoiceMailForm = reactive({
  id: null,
  from: null,
  to: null,
  subject: t('invoices.new_invoice'),
  body: null,
})

const modalActive = computed(() => {
  return modalStore.active && modalStore.componentName === 'SendInvoiceModal'
})

const modalTitle = computed(() => modalStore.title)
const modalData  = computed(() => modalStore.data)

const rules = {
  from: {
    // aunque esté oculto, validamos que exista y sea email correcto (ya lo rellenamos en setInitialData)
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
    // lo dejamos requerido; viene del valor por defecto de la empresa
    required: helpers.withMessage(t('validation.required'), required),
  },
}

const v$ = useVuelidate(rules, computed(() => invoiceMailForm))

async function setInitialData() {
  const admin = await companyStore.fetchBasicMailConfig()
  invoiceMailForm.id = modalStore.id

  if (admin?.data) {
    invoiceMailForm.from = admin.data.from_mail
  }
  if (modalData.value) {
    invoiceMailForm.to = modalData.value.customer.email
  }

  // cuerpo por defecto de la empresa
  invoiceMailForm.body = companyStore.selectedCompanySettings.invoice_mail_body
}

async function sendNow() {
  v$.value.$touch()
  if (v$.value.$invalid) return

  try {
    isLoading.value = true
    const response = await invoiceStore.sendInvoice(invoiceMailForm)
    isLoading.value = false

    if (response.data?.success) {
      emit('update', modalStore.id)
      closeSendInvoiceModal()
      return true
    }

    notificationStore.showNotification({
      type: 'error',
      message: t('invoices.something_went_wrong'),
    })
  } catch (error) {
    isLoading.value = false
    notificationStore.showNotification({
      type: 'error',
      message: t('invoices.something_went_wrong'),
    })
  }
}

function closeSendInvoiceModal() {
  modalStore.closeModal()
  setTimeout(() => {
    v$.value.$reset()
  }, 300)
}
</script>
