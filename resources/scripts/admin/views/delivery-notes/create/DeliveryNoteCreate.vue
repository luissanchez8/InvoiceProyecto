<!--
  Vista: Crear/Editar Albarán

  Formulario completo replicando el patrón de InvoiceCreate.vue.
  Incluye un toggle "Mostrar precios" que controla si los precios
  se muestran en el PDF generado (campo show_prices en el modelo).
-->
<template>
  <SelectTemplateModal />
  <ItemModal />
  <TaxTypeModal />
  <NumberWarningDialog
    :visible="showNumberWarning"
    :loading="isSaving || isSavingDraft"
    :title="$t('delivery_notes.number_warning_title')"
    :messages="numberWarningMessages"
    @confirm="onConfirmNumberWarning"
    @cancel="onCancelNumberWarning"
  />

  <BasePage class="relative invoice-create-page">
    <form @submit.prevent="submitForm">
      <BasePageHeader :title="pageTitle">
        <BaseBreadcrumb>
          <BaseBreadcrumbItem :title="$t('general.home')" to="/admin/dashboard" />
          <BaseBreadcrumbItem :title="$t('delivery_notes')" to="/admin/delivery-notes" />
          <BaseBreadcrumbItem :title="pageTitle" to="#" active />
        </BaseBreadcrumb>

        <template #actions>
          <BaseButton
            v-if="showDraftButton"
            :loading="isSavingDraft"
            :disabled="isSavingDraft || isSaving"
            variant="primary-outline"
            type="button"
            class="mr-3"
            @click="saveAsDraft"
          >
            <template #left="slotProps">
              <BaseIcon v-if="!isSavingDraft" name="DocumentIcon" :class="slotProps.class" />
            </template>
            {{ $t('delivery_notes.save_as_draft') }}
          </BaseButton>

          <BaseButton
            :loading="isSaving"
            :disabled="isSaving || isSavingDraft"
            variant="primary"
            type="submit"
          >
            <template #left="slotProps">
              <BaseIcon v-if="!isSaving" name="ArrowDownOnSquareIcon" :class="slotProps.class" />
            </template>
            {{ $t('general.save') }}
          </BaseButton>
        </template>
      </BasePageHeader>

      <!-- Campos básicos: cliente, fechas, número, toggle precios -->
      <div class="grid grid-cols-12 gap-8 mt-6 mb-8">
        <!-- Selector de cliente -->
        <BaseCustomerSelectPopup
          v-model="deliveryNoteStore.newDeliveryNote.customer"
          :valid="v$.customer_id"
          :content-loading="isLoadingContent"
          type="delivery-note"
          class="col-span-12 lg:col-span-5 pr-0"
        />

        <BaseInputGrid class="col-span-12 lg:col-span-7">
          <!-- Fecha del albarán -->
          <BaseInputGroup
            :label="$t('pdf_delivery_note_date')"
            :content-loading="isLoadingContent"
            required
            :error="v$.delivery_note_date.$error && v$.delivery_note_date.$errors[0].$message"
          >
            <BaseDatePicker
              v-model="deliveryNoteStore.newDeliveryNote.delivery_note_date"
              :content-loading="isLoadingContent"
              :calendar-button="true"
              calendar-button-icon="calendar"
            />
          </BaseInputGroup>

          <!-- Fecha de entrega prevista -->
          <BaseInputGroup
            :label="$t('pdf_delivery_date')"
            :content-loading="isLoadingContent"
          >
            <BaseDatePicker
              v-model="deliveryNoteStore.newDeliveryNote.delivery_date"
              :content-loading="isLoadingContent"
              :calendar-button="true"
              calendar-button-icon="calendar"
            />
          </BaseInputGroup>

          <!-- Número de albarán -->
          <BaseInputGroup
            :label="$t('pdf_delivery_note_number')"
            :content-loading="isLoadingContent"
            :error="v$.delivery_note_number.$error && v$.delivery_note_number.$errors[0].$message"
            required
          >
            <BaseInput
              v-model="deliveryNoteStore.newDeliveryNote.delivery_note_number"
              :content-loading="isLoadingContent"
              @input="v$.delivery_note_number.$touch()"
            />
          </BaseInputGroup>

          <!-- Toggle: Mostrar precios en el PDF -->
          <BaseInputGroup :label="$t('show_prices')" :content-loading="isLoadingContent">
            <BaseSwitch
              v-model="deliveryNoteStore.newDeliveryNote.show_prices"
              :content-loading="isLoadingContent"
            />
          </BaseInputGroup>
        </BaseInputGrid>
      </div>

      <BaseScrollPane>
        <!-- Líneas de ítems -->
        <CreateItems
          :currency="deliveryNoteStore.newDeliveryNote.selectedCurrency"
          :is-loading="isLoadingContent"
          :item-validation-scope="validationScope"
          :store="deliveryNoteStore"
          store-prop="newDeliveryNote"
        />

        <!-- Pie: notas + custom fields + template + totales -->
        <div class="block mt-10 invoice-foot lg:flex lg:justify-between lg:items-start">
          <div class="relative w-full lg:w-1/2 lg:mr-4">
            <NoteFields
              :store="deliveryNoteStore"
              store-prop="newDeliveryNote"
              :fields="noteFieldList"
              type="Invoice"
            />

            <CreateCustomFields
              type="Invoice"
              :is-edit="isEdit"
              :is-loading="isLoadingContent"
              :store="deliveryNoteStore"
              store-prop="newDeliveryNote"
              :custom-field-scope="validationScope"
              class="mb-6"
            />

            <SelectTemplate
              :store="deliveryNoteStore"
              store-prop="newDeliveryNote"
              component-name="InvoiceTemplate"
            />
          </div>

          <CreateTotal
            :currency="deliveryNoteStore.newDeliveryNote.selectedCurrency"
            :is-loading="isLoadingContent"
            :store="deliveryNoteStore"
            store-prop="newDeliveryNote"
            tax-popup-type="invoice"
          />
        </div>
      </BaseScrollPane>
    </form>
  </BasePage>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { required, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { cloneDeep } from 'lodash'

import { useDeliveryNoteStore } from '@/scripts/admin/stores/delivery-note'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useCustomFieldStore } from '@/scripts/admin/stores/custom-field'

import CreateItems from '@/scripts/admin/components/estimate-invoice-common/CreateItems.vue'
import CreateTotal from '@/scripts/admin/components/estimate-invoice-common/CreateTotal.vue'
import SelectTemplate from '@/scripts/admin/components/estimate-invoice-common/SelectTemplateButton.vue'
import NoteFields from '@/scripts/admin/components/estimate-invoice-common/CreateNotesField.vue'
import CreateCustomFields from '@/scripts/admin/components/custom-fields/CreateCustomFields.vue'
import SelectTemplateModal from '@/scripts/admin/components/modal-components/SelectTemplateModal.vue'
import TaxTypeModal from '@/scripts/admin/components/modal-components/TaxTypeModal.vue'
import ItemModal from '@/scripts/admin/components/modal-components/ItemModal.vue'
import NumberWarningDialog from '@/scripts/admin/components/dialogs/NumberWarningDialog.vue'
import axios from 'axios'

const deliveryNoteStore = useDeliveryNoteStore()
const companyStore = useCompanyStore()
const customFieldStore = useCustomFieldStore()

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const validationScope = 'newDeliveryNote'
let isSaving = ref(false)
let isSavingDraft = ref(false)

// Onfactu: modal de aviso
const showNumberWarning = ref(false)
const numberWarningMessages = ref([])
const pendingSaveOptions = ref(null)

const noteFieldList = ref(['customer', 'company', 'customerCustom', 'invoice', 'invoiceCustom'])

let isEdit = computed(() => route.name === 'deliveryNotes.edit')

// Onfactu: botón borrador solo si aún no hay número.
const showDraftButton = computed(() => {
  if (!isEdit.value) return true
  return !deliveryNoteStore.newDeliveryNote.delivery_note_number
})

let isLoadingContent = computed(
  () => deliveryNoteStore.isFetchingDeliveryNote || deliveryNoteStore.isFetchingInitialSettings
)

let pageTitle = computed(() =>
  isEdit.value ? t('delivery_note') : t('new_delivery_note')
)

const rules = {
  delivery_note_date: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  customer_id: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  delivery_note_number: {
    required: helpers.withMessage(t('validation.required'), required),
  },
}

const v$ = useVuelidate(
  rules,
  computed(() => deliveryNoteStore.newDeliveryNote),
  { $scope: validationScope }
)

customFieldStore.resetCustomFields()
v$.value.$reset
deliveryNoteStore.resetCurrentDeliveryNote()
deliveryNoteStore.fetchDeliveryNoteInitialSettings(isEdit.value)

// Sincronizar customer_id, moneda y currency_id cuando se selecciona un cliente.
// BaseCustomerSelectPopup actualiza el objeto customer pero no el customer_id
// del store de albaranes (solo conoce invoiceStore/estimateStore internamente).
watch(
  () => deliveryNoteStore.newDeliveryNote.customer,
  (newVal) => {
    if (newVal) {
      deliveryNoteStore.newDeliveryNote.customer_id = newVal.id
      deliveryNoteStore.newDeliveryNote.selectedCurrency = newVal.currency || companyStore.selectedCompanyCurrency
      deliveryNoteStore.newDeliveryNote.currency_id = newVal.currency_id
    } else {
      deliveryNoteStore.newDeliveryNote.customer_id = null
      deliveryNoteStore.newDeliveryNote.selectedCurrency = companyStore.selectedCompanyCurrency
    }
  }
)

async function submitForm() {
  await preSave({ clearNumber: false })
}

async function saveAsDraft() {
  await doSave({ clearNumber: true })
}

async function preSave({ clearNumber }) {
  v$.value.$touch()
  if (v$.value.$invalid) return false

  let info = null
  try {
    const resp = await axios.get('/api/v1/delivery-notes/next-number-info')
    info = resp.data
  } catch (err) {
    console.warn('Could not fetch delivery-notes next-number-info:', err)
    return doSave({ clearNumber })
  }

  const warnings = []
  const currentNumber = deliveryNoteStore.newDeliveryNote.delivery_note_number
  const currentDate = deliveryNoteStore.newDeliveryNote.delivery_note_date

  if (info.next_expected_sequence !== null && info.next_expected_sequence !== undefined && currentNumber) {
    const expectedSeq = parseInt(info.next_expected_sequence)
    const match = String(currentNumber).match(/(\d+)$/)
    if (match) {
      const enteredSeq = parseInt(match[1], 10)
      if (enteredSeq > expectedSeq) {
        const prefixMatch = String(currentNumber).match(/^(.*?)(\d+)$/)
        const prefix = prefixMatch ? prefixMatch[1] : ''
        const width = match[1].length
        const expectedNumber = prefix + String(expectedSeq).padStart(width, '0')
        warnings.push(t('delivery_notes.warning_number_skip', { expected: expectedNumber, entered: currentNumber }))
      } else if (enteredSeq < expectedSeq && !isEdit.value) {
        warnings.push(t('delivery_notes.warning_number_below', { entered: currentNumber, last: info.last_number }))
      }
    }
  }

  if (info.last_numbered_date && currentDate) {
    if (new Date(currentDate) < new Date(info.last_numbered_date)) {
      warnings.push(t('delivery_notes.warning_date_earlier', { date: info.last_numbered_date }))
    }
  }

  if (currentDate) {
    const entered = new Date(currentDate)
    if (!isNaN(entered.getTime())) {
      const enteredYear = entered.getFullYear()
      const currentYear = new Date().getFullYear()
      if (enteredYear !== currentYear) {
        warnings.push(t('delivery_notes.warning_year_mismatch', { entered: enteredYear, current: currentYear }))
      }
    }
  }

  if (warnings.length > 0) {
    numberWarningMessages.value = warnings
    pendingSaveOptions.value = { clearNumber }
    showNumberWarning.value = true
    return
  }

  return doSave({ clearNumber })
}

function onConfirmNumberWarning() {
  showNumberWarning.value = false
  const opts = pendingSaveOptions.value || { clearNumber: false }
  pendingSaveOptions.value = null
  doSave(opts)
}

function onCancelNumberWarning() {
  showNumberWarning.value = false
  pendingSaveOptions.value = null
  isSaving.value = false
  isSavingDraft.value = false
}

async function doSave({ clearNumber }) {
  if (clearNumber) {
    isSavingDraft.value = true
  } else {
    isSaving.value = true
  }

  let data = cloneDeep({
    ...deliveryNoteStore.newDeliveryNote,
    sub_total: deliveryNoteStore.getSubTotal,
    total: deliveryNoteStore.getTotal,
    tax: deliveryNoteStore.getTotalTax,
  })

  if (clearNumber) {
    data.delivery_note_number = null
    data.sequence_number      = null
    data.status               = 'DRAFT'
  }

  if (data.discount_per_item === 'YES') {
    data.items.forEach((item, index) => {
      if (item.discount_type === 'fixed') {
        data.items[index].discount = item.discount * 100
      }
    })
  } else {
    if (data.discount_type === 'fixed') {
      data.discount = data.discount * 100
    }
  }

  try {
    const action = isEdit.value
      ? deliveryNoteStore.updateDeliveryNote
      : deliveryNoteStore.addDeliveryNote

    const response = await action(data)
    router.push(`/admin/delivery-notes/${response.data.data.id}/view`)
  } catch (err) {
    console.error(err)
  }

  isSaving.value = false
  isSavingDraft.value = false
}
</script>
