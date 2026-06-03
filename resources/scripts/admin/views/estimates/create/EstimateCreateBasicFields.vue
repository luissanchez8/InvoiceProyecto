<template>
  <div class="md:grid-cols-12 grid-cols-1 md:gap-x-6 mt-6 mb-8 grid gap-y-5">
    <BaseCustomerSelectPopup
      v-model="estimateStore.newEstimate.customer"
      :valid="v.customer_id"
      :content-loading="isLoading"
      type="estimate"
      class="col-span-5 pr-0"
    />

    <BaseInputGrid class="col-span-7">
      <BaseInputGroup
        :label="$t('reports.estimates.estimate_date')"
        :content-loading="isLoading"
        required
        :error="v.estimate_date.$error && v.estimate_date.$errors[0].$message"
      >
        <BaseDatePicker
          v-model="estimateStore.newEstimate.estimate_date"
          :content-loading="isLoading"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('estimates.expiry_date')"
        :content-loading="isLoading"
      >
        <BaseDatePicker
          v-model="estimateStore.newEstimate.expiry_date"
          :content-loading="isLoading"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('estimates.estimate_number')"
        :content-loading="isLoading"
        :help-text="$t('estimates.estimate_number_help')"
        :error="
          v.estimate_number.$error && v.estimate_number.$errors[0].$message
        "
      >
        <BaseInput
          v-model="estimateStore.newEstimate.estimate_number"
          :content-loading="isLoading"
          :placeholder="$t('estimates.estimate_number_placeholder')"
        >
        </BaseInput>
      </BaseInputGroup>

      <!-- v.1.9.5 — Onfactu: selector de forma de pago -->
      <BaseInputGroup
        :label="$t('invoices.payment_method')"
        :content-loading="isLoading"
      >
        <BaseMultiselect
          v-model="estimateStore.newEstimate.payment_method_id"
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
        <!-- v.1.9.5 — Onfactu: aviso cuando la forma de pago seleccionada NO tiene texto -->
        <div
          v-else-if="hasPaymentMethodSelectedWithoutText"
          class="mt-1 text-gray-400"
          style="font-size: 11px; line-height: 1.5;"
        >
          <p>{{ $t('invoices.payment_method_no_text_hint') }}</p>
          <router-link
            to="/admin/settings/payment-mode"
            class="text-primary-500 hover:text-primary-700 hover:underline"
          >
            {{ $t('invoices.payment_method_no_text_link') }}
          </router-link>
        </div>
      </BaseInputGroup>

      <!-- <BaseInputGroup
        :label="$t('estimates.ref_number')"
        :content-loading="isLoading"
        :error="
          v.reference_number.$error && v.reference_number.$errors[0].$message
        "
      >
        <BaseInput
          v-model="estimateStore.newEstimate.reference_number"
          :content-loading="isLoading"
          @input="v.reference_number.$touch()"
        >
          <template #left="slotProps">
            <BaseIcon name="HashtagIcon" :class="slotProps.class" />
          </template>
        </BaseInput>
      </BaseInputGroup> -->
      <ExchangeRateConverter
        :store="estimateStore"
        store-prop="newEstimate"
        :v="v"
        :is-loading="isLoading"
        :is-edit="isEdit"
        :customer-currency="estimateStore.newEstimate.currency_id"
      />
    </BaseInputGrid>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useEstimateStore } from '@/scripts/admin/stores/estimate'
import { usePaymentStore } from '@/scripts/admin/stores/payment'
import ExchangeRateConverter from '@/scripts/admin/components/estimate-invoice-common/ExchangeRateConverter.vue'

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

const estimateStore = useEstimateStore()
const paymentStore = usePaymentStore()
const { t } = useI18n()

// v.1.9.5 — Onfactu: cargar las formas de pago para el selector
onMounted(() => {
  paymentStore.fetchPaymentModes({ limit: 'all' })
})

// v.1.9.5 — Onfactu: texto de la forma de pago seleccionada (mensaje tipo Holded)
const selectedPaymentMethodText = computed(() => {
  const id = estimateStore.newEstimate.payment_method_id
  if (!id) return ''
  const pm = (paymentStore.paymentModes || []).find(p => p.id === id)
  return pm && pm.document_text ? pm.document_text : ''
})
// v.1.9.5 — Onfactu: hay forma de pago elegida pero SIN texto configurado
const hasPaymentMethodSelectedWithoutText = computed(() => {
  const id = estimateStore.newEstimate.payment_method_id
  if (!id) return false
  const pm = (paymentStore.paymentModes || []).find(p => p.id === id)
  return pm && (!pm.document_text || pm.document_text.trim() === '')
})

// v.1.9.5 — Onfactu: opciones con "Sin forma de pago" al principio
const paymentMethodOptions = computed(() => {
  return [
    { id: null, name: t('invoices.payment_method_none') },
    ...(paymentStore.paymentModes || []),
  ]
})
</script>
