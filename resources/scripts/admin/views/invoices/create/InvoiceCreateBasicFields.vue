<template>
  <div class="grid grid-cols-12 gap-8 mt-6 mb-8">
    <BaseCustomerSelectPopup
      v-model="invoiceStore.newInvoice.customer"
      :valid="v.customer_id"
      :content-loading="isLoading"
      type="invoice"
      class="col-span-12 lg:col-span-5 pr-0"
    />

    <BaseInputGrid class="col-span-12 lg:col-span-7">
      <BaseInputGroup
        :label="$t('invoices.invoice_date')"
        :content-loading="isLoading"
        required
        :error="v.invoice_date.$error && v.invoice_date.$errors[0].$message"
      >
        <BaseDatePicker
          v-model="invoiceStore.newInvoice.invoice_date"
          :content-loading="isLoading"
          :calendar-button="true"
          calendar-button-icon="calendar"
          :enableTime="enableTime"
          :time24hr="time24h"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('invoices.due_date')"
        :content-loading="isLoading"
      >
        <BaseDatePicker
          v-model="invoiceStore.newInvoice.due_date"
          :content-loading="isLoading"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('invoices.invoice_number')"
        :content-loading="isLoading"
        :error="v.invoice_number.$error && v.invoice_number.$errors[0].$message"
        :help-text="$t('invoices.invoice_number_help')"
      >
        <BaseInput
          v-model="invoiceStore.newInvoice.invoice_number"
          :content-loading="isLoading"
          :placeholder="$t('invoices.invoice_number_placeholder')"
          @input="v.invoice_number.$touch()"
        />
      </BaseInputGroup>

      <!-- v.1.9.5 — Onfactu: selector de forma de pago -->
      <BaseInputGroup
        :label="$t('invoices.payment_method')"
        :content-loading="isLoading"
      >
        <BaseMultiselect
          v-model="invoiceStore.newInvoice.payment_method_id"
          :content-loading="isLoading"
          :options="paymentMethodOptions"
          value-prop="id"
          label="name"
          track-by="name"
          searchable
          :can-clear="true"
          :placeholder="$t('payments.select_payment_mode')"
        />
        <!-- v.1.9.5 — Onfactu: mensaje tipo Holded con el texto de la forma de pago -->
        <div
          v-if="selectedPaymentMethodText"
          class="mt-1 text-gray-500"
          style="font-size: 11px; line-height: 1.5;"
        >
          <p class="text-gray-600 mb-0.5">
            {{ $t('invoices.payment_method_doc_text_title') }}
          </p>
          <p class="whitespace-pre-line text-gray-500">{{ selectedPaymentMethodText }}</p>
          <router-link
            to="/admin/settings/payment-mode"
            class="text-primary-500 hover:text-primary-700 hover:underline"
          >
            {{ $t('invoices.payment_method_doc_text_link') }}
          </router-link>
        </div>
      </BaseInputGroup>

      <ExchangeRateConverter
        :store="invoiceStore"
        store-prop="newInvoice"
        :v="v"
        :is-loading="isLoading"
        :is-edit="isEdit"
        :customer-currency="invoiceStore.newInvoice.currency_id"
      />
    </BaseInputGrid>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import ExchangeRateConverter from '@/scripts/admin/components/estimate-invoice-common/ExchangeRateConverter.vue'
import { useInvoiceStore } from '@/scripts/admin/stores/invoice'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { usePaymentStore } from '@/scripts/admin/stores/payment'
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  v: {
    type: Object,
    default: null,
  },
  isLoading: {
    type: Boolean,
    default: false,
  },
  isEdit: {
    type: Boolean,
    default: false,
  },
})

const invoiceStore = useInvoiceStore()
const companyStore = useCompanyStore()
const paymentStore = usePaymentStore()
const { t } = useI18n()

// v.1.9.5 — Onfactu: cargar las formas de pago para el selector
onMounted(() => {
  paymentStore.fetchPaymentModes({ limit: 'all' })
})

// v.1.9.5 — Onfactu: texto de la forma de pago seleccionada (mensaje tipo Holded)
const selectedPaymentMethodText = computed(() => {
  const id = invoiceStore.newInvoice.payment_method_id
  if (!id) return ''
  const pm = (paymentStore.paymentModes || []).find(p => p.id === id)
  return pm && pm.document_text ? pm.document_text : ''
})

// v.1.9.5 — Onfactu: opciones con "Sin forma de pago" al principio
const paymentMethodOptions = computed(() => {
  return [
    { id: null, name: t('invoices.payment_method_none') },
    ...(paymentStore.paymentModes || []),
  ]
})


const enableTime = computed(() => {
  return (
    companyStore.selectedCompanySettings.invoice_use_time === 'YES'
  );
})
const time24h = computed(() => {
  return (
    companyStore.selectedCompanySettings.carbon_time_format.indexOf('H') > -1
  );
})

</script>
