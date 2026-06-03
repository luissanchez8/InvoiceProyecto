<template>
  <div class="col-span-5 pr-0">
    <BaseCustomerSelectPopup
      v-model="recurringInvoiceStore.newRecurringInvoice.customer"
      :valid="v.customer_id"
      :content-loading="isLoading"
      type="recurring-invoice"
    />

    <div class="flex mt-7">
      <div class="relative w-20 mt-8">
        <BaseSwitch
          v-model="recurringInvoiceStore.newRecurringInvoice.send_automatically"
          class="absolute -top-4"
        />
      </div>

      <div class="ml-2">
        <p class="p-0 mb-1 leading-snug text-left text-black">
          {{ $t('recurring_invoices.send_automatically') }}
        </p>
        <p
          class="p-0 m-0 text-xs leading-tight text-left text-gray-500"
          style="max-width: 480px"
        >
          {{ $t('recurring_invoices.send_automatically_desc') }}
        </p>
      </div>
    </div>
  </div>

  <div
    class="
      grid grid-cols-1
      col-span-7
      gap-4
      mt-8
      lg:gap-6 lg:mt-0 lg:grid-cols-2
    "
  >
    <BaseInputGroup
      :label="$t('recurring_invoices.starts_at')"
      :content-loading="isLoading"
      required
      :error="v.starts_at.$error && v.starts_at.$errors[0].$message"
    >
      <BaseDatePicker
        v-model="recurringInvoiceStore.newRecurringInvoice.starts_at"
        :content-loading="isLoading"
        :calendar-button="true"
        calendar-button-icon="calendar"
        :invalid="v.starts_at.$error"
        @change="getNextInvoiceDate()"
      />
    </BaseInputGroup>

    <BaseInputGroup
      :label="$t('recurring_invoices.next_invoice_date')"
      :content-loading="isLoading"
      required
    >
      <BaseDatePicker
        v-model="recurringInvoiceStore.newRecurringInvoice.next_invoice_at"
        :content-loading="isLoading"
        :calendar-button="true"
        :disabled="true"
        :loading="isLoadingNextDate"
        calendar-button-icon="calendar"
      />
    </BaseInputGroup>

    <BaseInputGroup
      :label="$t('recurring_invoices.limit_by')"
      :content-loading="isLoading"
      class="lg:mt-0"
      required
      :error="v.limit_by.$error && v.limit_by.$errors[0].$message"
    >
      <BaseMultiselect
        v-model="recurringInvoiceStore.newRecurringInvoice.limit_by"
        :content-loading="isLoading"
        :options="limits"
        label="label"
        :invalid="v.limit_by.$error"
        value-prop="value"
      />
    </BaseInputGroup>

    <BaseInputGroup
      v-if="hasLimitBy('DATE')"
      :label="$t('recurring_invoices.limit_date')"
      :content-loading="isLoading"
      :required="hasLimitBy('DATE')"
      :error="v.limit_date.$error && v.limit_date.$errors[0].$message"
    >
      <BaseDatePicker
        v-model="recurringInvoiceStore.newRecurringInvoice.limit_date"
        :content-loading="isLoading"
        :invalid="v.limit_date.$error"
        calendar-button-icon="calendar"
      />
    </BaseInputGroup>

    <BaseInputGroup
      v-if="hasLimitBy('COUNT')"
      :label="$t('recurring_invoices.count')"
      :content-loading="isLoading"
      :required="hasLimitBy('COUNT')"
      :error="v.limit_count.$error && v.limit_count.$errors[0].$message"
    >
      <BaseInput
        v-model="recurringInvoiceStore.newRecurringInvoice.limit_count"
        :content-loading="isLoading"
        :invalid="v.limit_count.$error"
        type="number"
      />
    </BaseInputGroup>

    <BaseInputGroup
      :label="$t('recurring_invoices.status')"
      required
      :content-loading="isLoading"
      :error="v.status.$error && v.status.$errors[0].$message"
    >
      <BaseMultiselect
        v-model="recurringInvoiceStore.newRecurringInvoice.status"
        :options="getStatusOptions"
        :content-loading="isLoading"
        :invalid="v.status.$error"
        :placeholder="$t('recurring_invoices.select_a_status')"
        value-prop="value"
        label="key"
      />
    </BaseInputGroup>

    <BaseInputGroup
      :label="$t('recurring_invoices.frequency.select_frequency')"
      required
      :content-loading="isLoading"
      :error="
        v.selectedFrequency.$error && v.selectedFrequency.$errors[0].$message
      "
    >
      <BaseMultiselect
        v-model="recurringInvoiceStore.newRecurringInvoice.selectedFrequency"
        :content-loading="isLoading"
        :options="recurringInvoiceStore.frequencies"
        label="label"
        :invalid="v.selectedFrequency.$error"
        object
        @change="getNextInvoiceDate"
      />
    </BaseInputGroup>

    <BaseInputGroup
      v-if="isCustomFrequency"
      :label="$t('recurring_invoices.frequency.title')"
      :content-loading="isLoading"
      required
      :error="v.frequency.$error && v.frequency.$errors[0].$message"
    >
      <BaseInput
        v-model="recurringInvoiceStore.newRecurringInvoice.frequency"
        :content-loading="isLoading"
        :disabled="!isCustomFrequency"
        :invalid="v.frequency.$error"
        :loading="isLoadingNextDate"
        @update:modelValue="debounceNextDate"
      />
    </BaseInputGroup>

    <!-- v.1.9.5 — Onfactu: selector de forma de pago -->
    <BaseInputGroup
      :label="$t('invoices.payment_method')"
      :content-loading="isLoading"
    >
      <BaseMultiselect
        v-model="recurringInvoiceStore.newRecurringInvoice.payment_method_id"
        :content-loading="isLoading"
        :options="paymentMethodOptions"
        value-prop="id"
        label="name"
        track-by="name"
        searchable
        :can-clear="true"
        :placeholder="$t('payments.select_payment_mode')"
      />
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
    <ExchangeRateConverter
      :store="recurringInvoiceStore"
      store-prop="newRecurringInvoice"
      :v="v"
      :is-loading="isLoading"
      :is-edit="isEdit"
      :customer-currency="recurringInvoiceStore.newRecurringInvoice.currency_id"
    />
  </div>
</template>

<script setup>
import { useGlobalStore } from '@/scripts/admin/stores/global'
import { useDebounceFn } from '@vueuse/core'
import { useRecurringInvoiceStore } from '@/scripts/admin/stores/recurring-invoice'
import { usePaymentStore } from '@/scripts/admin/stores/payment'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'

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

const route = useRoute()
const recurringInvoiceStore = useRecurringInvoiceStore()
const paymentStore = usePaymentStore()
const globalStore = useGlobalStore()
const { t } = useI18n()

const isLoadingNextDate = ref(false)

const limits = reactive([
  { label: t('recurring_invoices.limit.none'), value: 'NONE' },
  { label: t('recurring_invoices.limit.date'), value: 'DATE' },
  { label: t('recurring_invoices.limit.count'), value: 'COUNT' },
])

const isCustomFrequency = computed(() => {
  return (
    recurringInvoiceStore.newRecurringInvoice.selectedFrequency &&
    recurringInvoiceStore.newRecurringInvoice.selectedFrequency.value ===
      'CUSTOM'
  )
})

const getStatusOptions = computed(() => {
  if (props.isEdit) {
    return globalStore.config.recurring_invoice_status.update_status.map((item) => {
      return Object.assign({}, item, {
        key: t(item.key),
      })
    })
  }
  return globalStore.config.recurring_invoice_status.create_status.map((item) => {
    return Object.assign({}, item, {
      key: t(item.key),
    })
  })
})

watch(
  () => recurringInvoiceStore.newRecurringInvoice.selectedFrequency,
  (newValue) => {
    if (!recurringInvoiceStore.isFetchingInitialSettings) {
      if (newValue && newValue.value !== 'CUSTOM') {
        recurringInvoiceStore.newRecurringInvoice.frequency = newValue.value
      } else {
        recurringInvoiceStore.newRecurringInvoice.frequency = null
      }
    }
  }
)

onMounted(() => {
  // on create
  if (!route.params.id) {
    getNextInvoiceDate()
  }
  // v.1.9.5 — Onfactu: cargar formas de pago
  paymentStore.fetchPaymentModes({ limit: 'all' })
})

// v.1.9.5 — Onfactu: opciones con "Sin forma de pago"
const paymentMethodOptions = computed(() => {
  return [
    { id: null, name: t('invoices.payment_method_none') },
    ...(paymentStore.paymentModes || []),
  ]
})

// v.1.9.5 — Onfactu: texto de la forma de pago seleccionada
const selectedPaymentMethodText = computed(() => {
  const id = recurringInvoiceStore.newRecurringInvoice.payment_method_id
  if (!id) return ''
  const pm = (paymentStore.paymentModes || []).find(p => p.id === id)
  return pm && pm.document_text ? pm.document_text : ''
})
// v.1.9.5 — Onfactu: hay forma de pago elegida pero SIN texto configurado
const hasPaymentMethodSelectedWithoutText = computed(() => {
  const id = recurringInvoiceStore.newRecurringInvoice.payment_method_id
  if (!id) return false
  const pm = (paymentStore.paymentModes || []).find(p => p.id === id)
  return pm && (!pm.document_text || pm.document_text.trim() === '')
})

function hasLimitBy(LimitBy) {
  return recurringInvoiceStore.newRecurringInvoice.limit_by === LimitBy
}

const debounceNextDate = useDebounceFn(() => {
  getNextInvoiceDate()
}, 500)

async function getNextInvoiceDate() {
  const val = recurringInvoiceStore.newRecurringInvoice.frequency

  if (!val) {
    return
  }

  isLoadingNextDate.value = true

  let data = {
    starts_at: recurringInvoiceStore.newRecurringInvoice.starts_at,
    frequency: val,
  }

  try {
    await recurringInvoiceStore.fetchRecurringInvoiceFrequencyDate(data)
  } catch (error) {
    console.error(error)
    isLoadingNextDate.value = false
  }

  isLoadingNextDate.value = false
}
</script>
