<template>
  <SelectTemplateModal />
  <ItemModal />
  <TaxTypeModal />
  <ApproveInvoiceDialog
    :visible="showApproveDialog"
    :loading="isApproving"
    @approve="confirmApprove"
    @save-draft="onApproveSaveDraft"
    @cancel="onApproveCancelled"
  />
  <NumberWarningDialog
    :visible="showNumberWarning"
    :loading="isSaving || isSavingDraft"
    :title="$t('invoices.number_warning_title')"
    :messages="numberWarningMessages"
    @confirm="onConfirmNumberWarning"
    @cancel="onCancelNumberWarning"
  />
  <SalesTax
    v-if="salesTaxEnabled && (!isLoadingContent || route.query.customer)"
    :store="invoiceStore"
    :is-edit="isEdit"
    store-prop="newInvoice"
    :customer="invoiceStore.newInvoice.customer"
  />

  <BasePage class="relative invoice-create-page">
    <form @submit.prevent="submitForm">
      <BasePageHeader :title="pageTitle">
        <BaseBreadcrumb>
          <BaseBreadcrumbItem
            :title="$t('general.home')"
            to="/admin/dashboard"
          />
          <BaseBreadcrumbItem
            :title="$t('invoices.invoice', 2)"
            to="/admin/invoices"
          />
          <BaseBreadcrumbItem
            v-if="$route.name === 'invoices.edit'"
            :title="$t('invoices.edit_invoice')"
            to="#"
            active
          />
          <BaseBreadcrumbItem
            v-else
            :title="$t('invoices.new_invoice')"
            to="#"
            active
          />
        </BaseBreadcrumb>

        <template #actions>
          <router-link
            v-if="$route.name === 'invoices.edit'"
            :to="`/invoices/pdf/${invoiceStore.newInvoice.unique_hash}`"
            target="_blank"
          >
            <BaseButton class="mr-3" variant="primary-outline" type="button">
              <span class="flex">
                {{ $t('general.view_pdf') }}
              </span>
            </BaseButton>
          </router-link>

          <!--
            Onfactu: dos botones separados.
             - "Guardar como borrador" (gris/outline): status=DRAFT, sin número
               de serie asignado. Solo visible para facturas nuevas o borradores.
             - "Guardar" (verde primary): status=SAVED y se asigna número de serie.
            El botón "Aprobar VeriFactu" existente se mantiene como estaba.
          -->
          <BaseButton
            v-if="showDraftButton"
            :loading="isSavingDraft"
            :disabled="isSavingDraft || isSaving || isApproving"
            variant="primary-outline"
            type="button"
            class="mr-3"
            @click="saveAsDraft"
          >
            <template #left="slotProps">
              <BaseIcon
                v-if="!isSavingDraft"
                name="DocumentIcon"
                :class="slotProps.class"
              />
            </template>
            {{ $t('invoices.save_as_draft') }}
          </BaseButton>

          <BaseButton
            :loading="isSaving"
            :disabled="isSaving || isSavingDraft || isApproving"
            variant="primary"
            type="submit"
            class="mr-3"
          >
            <template #left="slotProps">
              <BaseIcon
                v-if="!isSaving"
                name="ArrowDownOnSquareIcon"
                :class="slotProps.class"
              />
            </template>
            {{ $t('invoices.save_invoice') }}
          </BaseButton>

          <BaseButton
            v-if="verifactuEnabled"
            :loading="isApproving"
            :disabled="isSaving || isSavingDraft || isApproving"
            variant="primary"
            type="button"
            @click="approveForm"
          >
            <template #left="slotProps">
              <BaseIcon
                v-if="!isApproving"
                name="CheckCircleIcon"
                :class="slotProps.class"
              />
            </template>
            {{ $t('verifactu.approve_invoice') }}
          </BaseButton>
        </template>
      </BasePageHeader>

      <!-- Select Customer & Basic Fields  -->
      <InvoiceBasicFields
        :v="v$"
        :is-loading="isLoadingContent"
        :is-edit="isEdit"
      />

      <BaseScrollPane>
        <!-- Invoice Items -->
        <InvoiceItems
          :currency="invoiceStore.newInvoice.selectedCurrency"
          :is-loading="isLoadingContent"
          :item-validation-scope="invoiceValidationScope"
          :store="invoiceStore"
          store-prop="newInvoice"
        />

        <!-- Invoice Footer Section -->
        <div
          class="
            block
            mt-10
            invoice-foot
            lg:flex lg:justify-between lg:items-start
          "
        >
          <div class="relative w-full lg:w-1/2 lg:mr-4">
            <!-- Invoice Custom Notes -->
            <NoteFields
              :store="invoiceStore"
              store-prop="newInvoice"
              :fields="invoiceNoteFieldList"
              type="Invoice"
            />

            <!-- Invoice Custom Fields -->
            <InvoiceCustomFields
              type="Invoice"
              :is-edit="isEdit"
              :is-loading="isLoadingContent"
              :store="invoiceStore"
              store-prop="newInvoice"
              :custom-field-scope="invoiceValidationScope"
              class="mb-6"
            />

            <!-- Invoice Template Button-->
            <SelectTemplate
              :store="invoiceStore"
              store-prop="newInvoice"
              component-name="InvoiceTemplate"
              :is-mark-as-default="isMarkAsDefault"
            />
          </div>

          <InvoiceTotal
            :currency="invoiceStore.newInvoice.selectedCurrency"
            :is-loading="isLoadingContent"
            :store="invoiceStore"
            store-prop="newInvoice"
            tax-popup-type="invoice"
          />
        </div>
      </BaseScrollPane>
    </form>
  </BasePage>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  required,
  maxLength,
  helpers,
  requiredIf,
  decimal,
} from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { cloneDeep } from 'lodash'

import { useInvoiceStore } from '@/scripts/admin/stores/invoice'
import { useModuleStore } from '@/scripts/admin/stores/module'
import { useNotesStore } from '@/scripts/admin/stores/note'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useCustomFieldStore } from '@/scripts/admin/stores/custom-field'
import { useDialogStore } from '@/scripts/stores/dialog'

import InvoiceItems from '@/scripts/admin/components/estimate-invoice-common/CreateItems.vue'
import InvoiceTotal from '@/scripts/admin/components/estimate-invoice-common/CreateTotal.vue'
import SelectTemplate from '@/scripts/admin/components/estimate-invoice-common/SelectTemplateButton.vue'
import InvoiceBasicFields from './InvoiceCreateBasicFields.vue'
import InvoiceCustomFields from '@/scripts/admin/components/custom-fields/CreateCustomFields.vue'
import NoteFields from '@/scripts/admin/components/estimate-invoice-common/CreateNotesField.vue'
import SelectTemplateModal from '@/scripts/admin/components/modal-components/SelectTemplateModal.vue'
import TaxTypeModal from '@/scripts/admin/components/modal-components/TaxTypeModal.vue'
import ItemModal from '@/scripts/admin/components/modal-components/ItemModal.vue'
import SalesTax from '@/scripts/admin/components/estimate-invoice-common/SalesTax.vue'
import ApproveInvoiceDialog from '@/scripts/admin/components/modal-components/ApproveInvoiceDialog.vue'
import NumberWarningDialog from '@/scripts/admin/components/dialogs/NumberWarningDialog.vue'
import axios from 'axios'

const invoiceStore = useInvoiceStore()
const companyStore = useCompanyStore()
const customFieldStore = useCustomFieldStore()
const moduleStore = useModuleStore()
const notesStore = useNotesStore()
const dialogStore = useDialogStore()

const { t } = useI18n()
let route = useRoute()
let router = useRouter()

const invoiceValidationScope = 'newInvoice'
let isSaving = ref(false)
let isSavingDraft = ref(false)
let isApproving = ref(false)
const showApproveDialog = ref(false)
const isMarkAsDefault = ref(false)

// Onfactu: aviso de inconsistencia en numeración/fecha. Se muestra modal
// antes de guardar si el usuario escribió un invoice_number que deja hueco
// en la serie o si la invoice_date es anterior a la última factura numerada.
const showNumberWarning = ref(false)
const numberWarningMessages = ref([])
const pendingSaveOptions = ref(null)  // { clearNumber: bool }

const verifactuEnabled = computed(() =>
  companyStore.selectedCompanySettings.verifactu_enabled === 'YES'
)

const invoiceNoteFieldList = ref([
  'customer',
  'company',
  'customerCustom',
  'invoice',
  'invoiceCustom',
])

let isLoadingContent = computed(
  () => invoiceStore.isFetchingInvoice || invoiceStore.isFetchingInitialSettings
)

let pageTitle = computed(() =>
  isEdit.value ? t('invoices.edit_invoice') : t('invoices.new_invoice')
)

const salesTaxEnabled = computed(() => {
  return (
    companyStore.selectedCompanySettings.sales_tax_us_enabled === 'YES' &&
    moduleStore.salesTaxUSEnabled
  )
})

let isEdit = computed(() => route.name === 'invoices.edit')

// Onfactu: el botón "Guardar como borrador" solo aparece si aún no hay número
// de serie asignado (factura nueva o borrador-sin-número). Si ya tiene número,
// no se puede volver a borrador-sin-número.
const showDraftButton = computed(() => {
  if (!isEdit.value) return true
  return !invoiceStore.newInvoice.invoice_number
})

const rules = {
  invoice_date: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  reference_number: {
    maxLength: helpers.withMessage(
      t('validation.price_maxlength'),
      maxLength(255)
    ),
  },
  customer_id: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  invoice_number: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  exchange_rate: {
    required: requiredIf(function () {
      helpers.withMessage(t('validation.required'), required)
      return invoiceStore.showExchangeRate
    }),
    decimal: helpers.withMessage(t('validation.valid_exchange_rate'), decimal),
  },
}

const v$ = useVuelidate(
  rules,
  computed(() => invoiceStore.newInvoice),
  { $scope: invoiceValidationScope }
)

customFieldStore.resetCustomFields()
v$.value.$reset
invoiceStore.resetCurrentInvoice()
invoiceStore.fetchInvoiceInitialSettings(isEdit.value)

watch(
  () => invoiceStore.newInvoice.customer,
  (newVal) => {
    if (newVal && newVal.currency) {
      invoiceStore.newInvoice.selectedCurrency = newVal.currency
    } else {
      invoiceStore.newInvoice.selectedCurrency =
        companyStore.selectedCompanyCurrency
    }
  }
)

async function submitForm() {
  // Onfactu: comprobar avisos antes de guardar. Si hay warning se muestra
  // modal; si no, guarda directamente.
  await preSave({ clearNumber: false })
}

// Onfactu: "Guardar como borrador" → envía invoice_number vacío para que
// el backend NO consuma número de serie. Sigue en status=DRAFT.
async function saveAsDraft() {
  // Borrador nunca muestra warning porque no tiene número.
  await doSave({ clearNumber: true })
}

// Onfactu: valida consistencia y, si hay avisos, muestra modal. Si no, guarda.
async function preSave({ clearNumber }) {
  v$.value.$touch()

  if (v$.value.$invalid) {
    console.log('Form is invalid:', v$.value.$errors)
    return false
  }

  // Comprobar contra el backend: último número y última fecha.
  let info = null
  try {
    const resp = await axios.get('/api/v1/invoices/next-number-info')
    info = resp.data
  } catch (err) {
    // Si falla la comprobación, seguimos sin warning (no bloqueamos al usuario).
    console.warn('Could not fetch next-number-info:', err)
    return doSave({ clearNumber })
  }

  const warnings = []
  const currentNumber = invoiceStore.newInvoice.invoice_number
  const currentDate = invoiceStore.newInvoice.invoice_date

  // Onfactu: next_expected_sequence viene calculado por el backend como el
  // primer hueco libre desde 1. Usamos ese valor para detectar saltos. Así,
  // aunque el usuario haya metido un INV-000099 manualmente, si todavía no
  // se ha usado el 5 (por ejemplo), escribir INV-000050 avisa (salto de 5 a 50).
  if (info.next_expected_sequence !== null && info.next_expected_sequence !== undefined && currentNumber) {
    const expectedSeq = parseInt(info.next_expected_sequence)
    // Intentar extraer el número del invoice_number actual (FAC-000003 -> 3).
    const match = String(currentNumber).match(/(\d+)$/)
    if (match) {
      const enteredSeq = parseInt(match[1], 10)
      if (enteredSeq > expectedSeq) {
        // Construir el número esperado con el mismo prefijo que el usuario escribió.
        const prefixMatch = String(currentNumber).match(/^(.*?)(\d+)$/)
        const prefix = prefixMatch ? prefixMatch[1] : ''
        const width = match[1].length
        const expectedNumber = prefix + String(expectedSeq).padStart(width, '0')
        warnings.push(
          t('invoices.warning_number_skip', {
            expected: expectedNumber,
            entered: currentNumber,
          })
        )
      } else if (enteredSeq < expectedSeq && !isEdit.value) {
        // Número ya ocupado (por debajo del siguiente libre) en factura nueva.
        warnings.push(
          t('invoices.warning_number_below', {
            entered: currentNumber,
            last: info.last_number,
          })
        )
      }
    }
  }

  // Aviso 2: fecha anterior a la última factura numerada.
  if (info.last_numbered_date && currentDate) {
    const lastDate = new Date(info.last_numbered_date)
    const curDate = new Date(currentDate)
    if (curDate < lastDate) {
      warnings.push(
        t('invoices.warning_date_earlier', {
          date: info.last_numbered_date,
        })
      )
    }
  }

  if (warnings.length > 0) {
    numberWarningMessages.value = warnings
    pendingSaveOptions.value = { clearNumber }
    showNumberWarning.value = true
    return
  }

  // Sin avisos → guardar directamente.
  return doSave({ clearNumber })
}

// Onfactu: dado un string tipo "FAC-000003" devuelve "FAC-000004".
function incrementNumberString(str) {
  const match = String(str).match(/^(.*?)(\d+)$/)
  if (!match) return str
  const prefix = match[1]
  const num = parseInt(match[2], 10) + 1
  const width = match[2].length
  return prefix + String(num).padStart(width, '0')
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
  v$.value.$touch()

  // Al guardar como borrador no validamos invoice_number (el required es
  // para el otro botón). Si el usuario pulsa "Guardar como borrador" y el
  // resto de campos están OK, seguimos adelante aunque invoice_number
  // tenga error de validación.
  if (v$.value.$invalid) {
    const onlyInvoiceNumberError = clearNumber
      && v$.value.$errors.every((e) => e.$property === 'invoice_number')
    if (!onlyInvoiceNumberError) {
      console.log('Form is invalid:', v$.value.$errors)
      return false
    }
  }

  if (clearNumber) {
    isSavingDraft.value = true
  } else {
    isSaving.value = true
  }

  let data = cloneDeep({
    ...invoiceStore.newInvoice,
    sub_total: invoiceStore.getSubTotal,
    total: invoiceStore.getTotal,
    tax: invoiceStore.getTotalTax,
  })

  if (clearNumber) {
    // Guardar como borrador → vaciar número para que el backend no lo use.
    data.invoice_number = null
    data.sequence_number = null
    data.status = 'DRAFT'
  }

  if (data.discount_per_item === 'YES') {
    data.items.forEach((item, index) => {
      if (item.discount_type === 'fixed'){
        data.items[index].discount = item.discount * 100
      }
    })
  }
  else {
    if (data.discount_type === 'fixed'){
      data.discount = data.discount * 100
    }
  }
    if (
    !invoiceStore.newInvoice.tax_per_item === 'YES'
    && data.taxes.length
  ){
    data.tax_type_ids = data.taxes.map(_t => _t.tax_type_id)
  }

  try {
    const action = isEdit.value
      ? invoiceStore.updateInvoice
      : invoiceStore.addInvoice

    const response = await action(data)

    router.push(`/admin/invoices/${response.data.data.id}/view`)
  } catch (err) {
    console.error(err)
  }

  isSaving.value = false
  isSavingDraft.value = false
}

function approveForm() {
  v$.value.$touch()
  if (v$.value.$invalid) {
    return false
  }
  showApproveDialog.value = true
}

async function confirmApprove() {
  isApproving.value = true

  let data = cloneDeep({
    ...invoiceStore.newInvoice,
    sub_total: invoiceStore.getSubTotal,
    total: invoiceStore.getTotal,
    tax: invoiceStore.getTotalTax,
    verifactu_approve: true,
  })

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

  if (
    !invoiceStore.newInvoice.tax_per_item === 'YES' &&
    data.taxes.length
  ) {
    data.tax_type_ids = data.taxes.map((_t) => _t.tax_type_id)
  }

  try {
    const action = isEdit.value
      ? invoiceStore.updateInvoice
      : invoiceStore.addInvoice

    const response = await action(data)
    const invoiceId = response.data.data.id

    // Después de guardar, aprobar (enviar a VeriFactu)
    await invoiceStore.approveInvoice(invoiceId)

    showApproveDialog.value = false
    router.push(`/admin/invoices/${invoiceId}/view`)
  } catch (err) {
    console.error(err)
  }

  isApproving.value = false
}

async function onApproveSaveDraft() {
  showApproveDialog.value = false

  v$.value.$touch()
  if (v$.value.$invalid) return

  isSaving.value = true

  let data = cloneDeep({
    ...invoiceStore.newInvoice,
    sub_total: invoiceStore.getSubTotal,
    total: invoiceStore.getTotal,
    tax: invoiceStore.getTotalTax,
  })

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

  if (!invoiceStore.newInvoice.tax_per_item === 'YES' && data.taxes.length) {
    data.tax_type_ids = data.taxes.map((_t) => _t.tax_type_id)
  }

  try {
    const action = isEdit.value
      ? invoiceStore.updateInvoice
      : invoiceStore.addInvoice

    await action(data)
    router.push('/admin/invoices')
  } catch (err) {
    console.error(err)
  }

  isSaving.value = false
}

function onApproveCancelled() {
  showApproveDialog.value = false
}
</script>
