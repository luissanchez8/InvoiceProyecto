<template>
  <SelectTemplateModal />
  <ItemModal />
  <TaxTypeModal />
  <ApproveInvoiceDialog
    :visible="showApproveDialog"
    :loading="isApproving"
    :manual-number="numberIsManual ? invoiceStore.newInvoice.invoice_number : ''"
    :suggested-number="invoiceStore.suggestedInvoiceNumber"
    @approve="confirmApprove"
    @save-draft="onApproveSaveDraft"
    @cancel="onApproveCancelled"
  />
  <NumberCollisionDialog
    :visible="showCollisionDialog"
    :details="numberCollision"
    doc-type="invoice"
    @close="numberCollision = null"
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

          <BaseButton
            :loading="isSaving"
            :disabled="isSaving || isApproving"
            variant="primary-outline"
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
            {{ verifactuEnabled ? $t('verifactu.save_as_draft') : $t('invoices.save_invoice') }}
          </BaseButton>

          <BaseButton
            v-if="verifactuEnabled"
            :loading="isApproving"
            :disabled="isSaving || isApproving"
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
import NumberCollisionDialog from '@/scripts/admin/components/modal-components/NumberCollisionDialog.vue'

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
let isApproving = ref(false)
const showApproveDialog = ref(false)
const isMarkAsDefault = ref(false)

// Onfactu — numeración diferida: modal cuando el backend devuelve 409 de
// colisión al aprobar (hay otra factura con el número que tocaba).
const numberCollision = ref(null) // { conflicting_id, conflicting_number, conflicting_status, attempted_number }
const showCollisionDialog = computed({
  get: () => numberCollision.value !== null,
  set: (val) => {
    if (!val) numberCollision.value = null
  },
})

const verifactuEnabled = computed(() =>
  companyStore.selectedCompanySettings.verifactu_enabled === 'YES'
)

// Onfactu — numeración diferida:
// numberIsManual es true cuando el input tiene un valor distinto de la sugerencia
// secuencial actual (el usuario ha escrito un número a mano). Se usa para
// mostrar el aviso "Estás saltando la numeración secuencial" en el diálogo
// de aprobación.
const numberIsManual = computed(() => {
  const val = String(invoiceStore.newInvoice.invoice_number || '').trim()
  const sug = String(invoiceStore.suggestedInvoiceNumber || '').trim()
  if (!val) return false // input vacío: se asignará automático, no es manual
  if (!sug) return false // sin sugerencia cargada aún
  return val !== sug
})

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
    // Onfactu — numeración diferida: invoice_number es OPCIONAL.
    // Si el usuario lo deja vacío, se asignará automáticamente al aprobar.
    // Si lo rellena, se valida backend que sea único en la empresa.
    maxLength: helpers.withMessage(
      t('validation.name_max_length'),
      maxLength(100)
    ),
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

// ── Onfactu — numeración diferida ────────────────────────────────────────────
// Detecta si el invoice_number del input es la sugerencia sin tocar.
// Si lo es, al guardar como borrador lo sustituimos por null para que el
// secuencial quede libre (no "bloqueado" por un borrador sin necesidad).
function isUnchangedSuggestion(numberInForm) {
  const suggestion = invoiceStore.suggestedInvoiceNumber
  if (!suggestion) return false
  return String(numberInForm || '').trim() === String(suggestion).trim()
}

// Aplica regla "descartar si es sugerencia" al payload antes de enviarlo
// al backend en un "Guardar como borrador".
function resolveNumberForDraft(data) {
  if (isEdit.value) {
    // En edición, si la factura ya estaba en borrador SIN número, el input
    // habrá sido pre-rellenado con la sugerencia actual. Mantenemos la lógica:
    // si coincide con la sugerencia, se guarda como null.
    if (isUnchangedSuggestion(data.invoice_number)) {
      data.invoice_number = null
    }
  } else {
    // En creación: si el usuario no tocó la sugerencia, null.
    if (isUnchangedSuggestion(data.invoice_number)) {
      data.invoice_number = null
    }
  }
  return data
}

async function submitForm() {
  v$.value.$touch()

  if (v$.value.$invalid) {
    console.log('Form is invalid:', v$.value.$errors)
    return false
  }

  isSaving.value = true

  let data = cloneDeep({
    ...invoiceStore.newInvoice,
    sub_total: invoiceStore.getSubTotal,
    total: invoiceStore.getTotal,
    tax: invoiceStore.getTotalTax,
  })

  // Numeración diferida: descarta sugerencia no tocada
  data = resolveNumberForDraft(data)

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

  // Onfactu — numeración diferida:
  // Si el input es la sugerencia sin tocar, enviamos null para que el backend
  // active la generación automática (asigna invoice_number + sequence_number).
  // Si el usuario escribió algo distinto, se envía tal cual como número manual
  // y el backend lo respeta sin tocar sequence_number (la secuencia auto sigue
  // por su cuenta).
  data = resolveNumberForDraft(data)

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
    try {
      await invoiceStore.approveInvoice(invoiceId)

      showApproveDialog.value = false
      router.push(`/admin/invoices/${invoiceId}/view`)
    } catch (approveErr) {
      // Onfactu — colisión de número al aprobar
      const status = approveErr?.response?.status
      const errorCode = approveErr?.response?.data?.error_code
      if (status === 409 && errorCode === 'number_collision') {
        numberCollision.value = approveErr.response.data.details || {}
        showApproveDialog.value = false
      } else {
        // Cualquier otro error ya se ha mostrado como toast en el store
        console.error(approveErr)
      }
    }
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

  // Numeración diferida: descarta sugerencia no tocada
  data = resolveNumberForDraft(data)

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
