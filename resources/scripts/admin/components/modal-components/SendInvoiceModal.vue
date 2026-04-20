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
import { useProformaInvoiceStore } from '@/scripts/admin/stores/proforma-invoice'
import { useDeliveryNoteStore } from '@/scripts/admin/stores/delivery-note'
import { useEstimateStore } from '@/scripts/admin/stores/estimate'
import { useVuelidate } from '@vuelidate/core'
import { required, email, helpers } from '@vuelidate/validators'
import { useMailDriverStore } from '@/scripts/admin/stores/mail-driver'

const modalStore = useModalStore()
const companyStore = useCompanyStore()
const notificationStore = useNotificationStore()
const invoiceStore = useInvoiceStore()
const proformaInvoiceStore = useProformaInvoiceStore()
const deliveryNoteStore = useDeliveryNoteStore()
const estimateStore = useEstimateStore()
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
  subject: null,
  body: null,
})

const modalActive = computed(() => {
  return modalStore.active && modalStore.componentName === 'SendInvoiceModal'
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

const v$ = useVuelidate(rules, computed(() => invoiceMailForm))

async function setInitialData() {
  const admin = await companyStore.fetchBasicMailConfig()
  invoiceMailForm.id = modalStore.id

  if (admin?.data) {
    invoiceMailForm.from = admin.data.from_mail
  }
  if (modalData.value) {
    invoiceMailForm.to = modalData.value.customer?.email || ''
  }

  // Asunto personalizado según tipo de documento
  const docType = modalStore.docType || 'invoice'
  const docData = modalData.value
  let subject = ''

  switch (docType) {
    case 'proforma_invoice':
      subject = 'Nueva ' + t('proforma_invoice') + ' ' + (docData?.proforma_invoice_number || '')
      break
    case 'delivery_note':
      subject = 'Nuevo ' + t('delivery_note') + ' ' + (docData?.delivery_note_number || '')
      break
    case 'estimate':
      subject = 'Nuevo ' + t('estimates.estimate') + ' ' + (docData?.estimate_number || '')
      break
    case 'invoice':
    default:
      subject = 'Nueva ' + t('invoices.invoice') + ' ' + (docData?.invoice_number || '')
      break
  }

  invoiceMailForm.subject = subject

  // cuerpo por defecto según tipo de documento
  const settings = companyStore.selectedCompanySettings
  switch (docType) {
    case 'proforma_invoice':
      invoiceMailForm.body = settings.proforma_mail_body || settings.invoice_mail_body || ' '
      break
    case 'delivery_note':
      invoiceMailForm.body = settings.delivery_note_mail_body || settings.invoice_mail_body || ' '
      break
    case 'estimate':
      invoiceMailForm.body = settings.estimate_mail_body || settings.invoice_mail_body || ' '
      break
    case 'invoice':
    default:
      invoiceMailForm.body = settings.invoice_mail_body || ' '
      break
  }
}

async function sendNow() {
  v$.value.$touch()
  if (v$.value.$invalid) return

  try {
    isLoading.value = true

    const docType = modalStore.docType || 'invoice'
    let response

    switch (docType) {
      case 'proforma_invoice':
        response = await proformaInvoiceStore.sendProformaInvoice(invoiceMailForm)
        break
      case 'delivery_note':
        response = await deliveryNoteStore.sendDeliveryNote(invoiceMailForm)
        break
      case 'estimate':
        response = await estimateStore.sendEstimate(invoiceMailForm)
        break
      case 'invoice':
      default:
        response = await invoiceStore.sendInvoice(invoiceMailForm)
        break
    }

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
