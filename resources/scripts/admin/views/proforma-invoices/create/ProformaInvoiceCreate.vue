<!--
  Vista: Crear/Editar Factura Proforma

  Formulario completo replicando el patrón de InvoiceCreate.vue.
  Reutiliza los componentes compartidos de estimate-invoice-common/:
  - CreateItems (gestión de líneas de ítems)
  - CreateTotal (cálculos de subtotal, descuento, impuestos, total)
  - SelectTemplateButton (selector de plantilla PDF)
  - CreateNotesField (selector de notas predefinidas)
  - CreateCustomFields (campos personalizados)

  El store proforma-invoice.js tiene la misma interfaz que invoice.js
  (getters de cálculo, gestión de ítems, etc.) por lo que los componentes
  compartidos funcionan sin modificación pasándole el store como prop.
-->
<template>
  <!-- Modales reutilizados de facturas -->
  <SelectTemplateModal />
  <ItemModal />
  <TaxTypeModal />
  <NumberWarningDialog
    :visible="showNumberWarning"
    :loading="isSaving || isSavingDraft"
    :title="$t('proforma_invoices.number_warning_title')"
    :messages="numberWarningMessages"
    @confirm="onConfirmNumberWarning"
    @cancel="onCancelNumberWarning"
  />

  <BasePage class="relative invoice-create-page">
    <form @submit.prevent="submitForm">
      <!-- Cabecera con breadcrumb y botón guardar -->
      <BasePageHeader :title="pageTitle">
        <BaseBreadcrumb>
          <BaseBreadcrumbItem :title="$t('general.home')" to="/admin/dashboard" />
          <BaseBreadcrumbItem :title="$t('proforma_invoices')" to="/admin/proforma-invoices" />
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
            {{ $t('proforma_invoices.save_as_draft') }}
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

      <!-- Campos básicos: cliente, fecha, número -->
      <div class="grid grid-cols-12 gap-8 mt-6 mb-8">
        <!-- Selector de cliente -->
        <BaseCustomerSelectPopup
          v-model="proformaInvoiceStore.newProformaInvoice.customer"
          :valid="v$.customer_id"
          :content-loading="isLoadingContent"
          type="proforma-invoice"
          class="col-span-12 lg:col-span-5 pr-0"
        />

        <!-- Campos de fecha y número -->
        <BaseInputGrid class="col-span-12 lg:col-span-7">
          <!-- Fecha de la proforma -->
          <BaseInputGroup
            :label="$t('pdf_proforma_invoice_date')"
            :content-loading="isLoadingContent"
            required
            :error="v$.proforma_invoice_date.$error && v$.proforma_invoice_date.$errors[0].$message"
          >
            <BaseDatePicker
              v-model="proformaInvoiceStore.newProformaInvoice.proforma_invoice_date"
              :content-loading="isLoadingContent"
              :calendar-button="true"
              calendar-button-icon="calendar"
            />
          </BaseInputGroup>

          <!-- Fecha de validez/expiración -->
          <BaseInputGroup
            :label="$t('pdf_proforma_invoice_expiry_date')"
            :content-loading="isLoadingContent"
          >
            <BaseDatePicker
              v-model="proformaInvoiceStore.newProformaInvoice.expiry_date"
              :content-loading="isLoadingContent"
              :calendar-button="true"
              calendar-button-icon="calendar"
            />
          </BaseInputGroup>

          <!-- Número de proforma -->
          <BaseInputGroup
            :label="$t('pdf_proforma_invoice_number')"
            :content-loading="isLoadingContent"
            :error="v$.proforma_invoice_number.$error && v$.proforma_invoice_number.$errors[0].$message"
            required
          >
            <BaseInput
              v-model="proformaInvoiceStore.newProformaInvoice.proforma_invoice_number"
              :content-loading="isLoadingContent"
              @input="v$.proforma_invoice_number.$touch()"
            />
          </BaseInputGroup>

          <!-- Referencia -->
          <BaseInputGroup
            :label="$t('invoices.ref_number')"
            :content-loading="isLoadingContent"
          >
            <BaseInput
              v-model="proformaInvoiceStore.newProformaInvoice.reference_number"
              :content-loading="isLoadingContent"
            />
          </BaseInputGroup>
        </BaseInputGrid>
      </div>

      <BaseScrollPane>
        <!-- Líneas de ítems — componente compartido con facturas -->
        <CreateItems
          :currency="proformaInvoiceStore.newProformaInvoice.selectedCurrency"
          :is-loading="isLoadingContent"
          :item-validation-scope="validationScope"
          :store="proformaInvoiceStore"
          store-prop="newProformaInvoice"
        />

        <!-- Pie del formulario: notas + campos custom + template + totales -->
        <div class="block mt-10 invoice-foot lg:flex lg:justify-between lg:items-start">
          <div class="relative w-full lg:w-1/2 lg:mr-4">
            <!-- Selector de notas predefinidas -->
            <NoteFields
              :store="proformaInvoiceStore"
              store-prop="newProformaInvoice"
              :fields="noteFieldList"
              type="Invoice"
            />

            <!-- Campos personalizados -->
            <CreateCustomFields
              type="Invoice"
              :is-edit="isEdit"
              :is-loading="isLoadingContent"
              :store="proformaInvoiceStore"
              store-prop="newProformaInvoice"
              :custom-field-scope="validationScope"
              class="mb-6"
            />

            <!-- Selector de plantilla PDF -->
            <SelectTemplate
              :store="proformaInvoiceStore"
              store-prop="newProformaInvoice"
              component-name="InvoiceTemplate"
            />
          </div>

          <!-- Bloque de totales — componente compartido -->
          <CreateTotal
            :currency="proformaInvoiceStore.newProformaInvoice.selectedCurrency"
            :is-loading="isLoadingContent"
            :store="proformaInvoiceStore"
            store-prop="newProformaInvoice"
            tax-popup-type="invoice"
          />
        </div>
      </BaseScrollPane>
    </form>
  </BasePage>
</template>

<script setup>
/**
 * Script del formulario de creación/edición de facturas proforma.
 * Usa los mismos componentes compartidos que InvoiceCreate.vue
 * pasando el store de proforma-invoice como prop.
 */
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { required, maxLength, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { cloneDeep } from 'lodash'

// Store de proforma-invoice (tiene la misma interfaz que invoice store)
import { useProformaInvoiceStore } from '@/scripts/admin/stores/proforma-invoice'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useCustomFieldStore } from '@/scripts/admin/stores/custom-field'

// Componentes compartidos con facturas y presupuestos
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

const proformaInvoiceStore = useProformaInvoiceStore()
const companyStore = useCompanyStore()
const customFieldStore = useCustomFieldStore()

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const validationScope = 'newProformaInvoice'
let isSaving = ref(false)
let isSavingDraft = ref(false)

// Onfactu: modal de aviso
const showNumberWarning = ref(false)
const numberWarningMessages = ref([])
const pendingSaveOptions = ref(null)

// Lista de tipos de notas disponibles en el selector
const noteFieldList = ref(['customer', 'company', 'customerCustom', 'invoice', 'invoiceCustom'])

// Detectar si estamos en modo edición por el nombre de la ruta
let isEdit = computed(() => route.name === 'proformaInvoices.edit')

// Onfactu: botón borrador solo si aún no hay número.
const showDraftButton = computed(() => {
  if (!isEdit.value) return true
  return !proformaInvoiceStore.newProformaInvoice.proforma_invoice_number
})

let isLoadingContent = computed(
  () => proformaInvoiceStore.isFetchingProformaInvoice || proformaInvoiceStore.isFetchingInitialSettings
)

let pageTitle = computed(() =>
  isEdit.value ? t('proforma_invoice') : t('new_proforma_invoice')
)

// Reglas de validación Vuelidate
const rules = {
  proforma_invoice_date: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  customer_id: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  proforma_invoice_number: {
    required: helpers.withMessage(t('validation.required'), required),
  },
}

const v$ = useVuelidate(
  rules,
  computed(() => proformaInvoiceStore.newProformaInvoice),
  { $scope: validationScope }
)

// Inicializar: resetear estado y cargar configuración
customFieldStore.resetCustomFields()
v$.value.$reset
proformaInvoiceStore.resetCurrentProformaInvoice()
proformaInvoiceStore.fetchProformaInvoiceInitialSettings(isEdit.value)

// Vigilar cambio de cliente para actualizar la moneda
// Cuando el usuario selecciona un cliente en BaseCustomerSelectPopup,
// se actualiza el objeto 'customer'. Este watcher sincroniza customer_id
// y la moneda, ya que BaseCustomerSelectPopup no conoce el store de proforma
// (solo actualiza invoiceStore internamente por su lógica hardcodeada).
watch(
  () => proformaInvoiceStore.newProformaInvoice.customer,
  (newVal) => {
    if (newVal) {
      // Sincronizar customer_id desde el objeto customer seleccionado
      proformaInvoiceStore.newProformaInvoice.customer_id = newVal.id
      // Actualizar moneda del documento según la moneda del cliente
      proformaInvoiceStore.newProformaInvoice.selectedCurrency = newVal.currency || companyStore.selectedCompanyCurrency
      // Actualizar currency_id
      proformaInvoiceStore.newProformaInvoice.currency_id = newVal.currency_id
    } else {
      proformaInvoiceStore.newProformaInvoice.customer_id = null
      proformaInvoiceStore.newProformaInvoice.selectedCurrency = companyStore.selectedCompanyCurrency
    }
  }
)

/**
 * Envía el formulario al backend.
 * Onfactu: antes de guardar, consulta el endpoint y si detecta hueco de
 * número, fecha anterior o año distinto, muestra modal. Si no, guarda.
 */
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
    const resp = await axios.get('/api/v1/proforma-invoices/next-number-info')
    info = resp.data
  } catch (err) {
    console.warn('Could not fetch proforma next-number-info:', err)
    return doSave({ clearNumber })
  }

  const warnings = []
  const currentNumber = proformaInvoiceStore.newProformaInvoice.proforma_invoice_number
  const currentDate = proformaInvoiceStore.newProformaInvoice.proforma_invoice_date

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
        warnings.push(t('proforma_invoices.warning_number_skip', { expected: expectedNumber, entered: currentNumber }))
      } else if (enteredSeq < expectedSeq && !isEdit.value) {
        warnings.push(t('proforma_invoices.warning_number_below', { entered: currentNumber, last: info.last_number }))
      }
    }
  }

  if (info.last_numbered_date && currentDate) {
    if (new Date(currentDate) < new Date(info.last_numbered_date)) {
      warnings.push(t('proforma_invoices.warning_date_earlier', { date: info.last_numbered_date }))
    }
  }

  if (currentDate) {
    const entered = new Date(currentDate)
    if (!isNaN(entered.getTime())) {
      const enteredYear = entered.getFullYear()
      const currentYear = new Date().getFullYear()
      if (enteredYear !== currentYear) {
        warnings.push(t('proforma_invoices.warning_year_mismatch', { entered: enteredYear, current: currentYear }))
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

  // Clonar datos del store y añadir cálculos
  let data = cloneDeep({
    ...proformaInvoiceStore.newProformaInvoice,
    sub_total: proformaInvoiceStore.getSubTotal,
    total: proformaInvoiceStore.getTotal,
    tax: proformaInvoiceStore.getTotalTax,
  })

  if (clearNumber) {
    data.proforma_invoice_number = null
    data.sequence_number         = null
    data.status                  = 'DRAFT'
  }

  // Convertir descuentos fijos a céntimos para el backend
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
      ? proformaInvoiceStore.updateProformaInvoice
      : proformaInvoiceStore.addProformaInvoice

    const response = await action(data)
    router.push(`/admin/proforma-invoices/${response.data.data.id}/view`)
  } catch (err) {
    console.error(err)
  }

  isSaving.value = false
  isSavingDraft.value = false
}
</script>
