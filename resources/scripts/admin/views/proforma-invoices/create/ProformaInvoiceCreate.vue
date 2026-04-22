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
  <NumberCollisionDialog
    :visible="showCollisionDialog"
    :details="numberCollision"
    doc-type="proforma-invoice"
    @close="numberCollision = null"
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
            :loading="isSaving"
            :disabled="isSaving"
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
            :help-text="$t('proforma_invoices.proforma_invoice_number_help')"
            :error="v$.proforma_invoice_number.$error && v$.proforma_invoice_number.$errors[0].$message"
          >
            <BaseInput
              v-model="proformaInvoiceStore.newProformaInvoice.proforma_invoice_number"
              :content-loading="isLoadingContent"
              :placeholder="$t('proforma_invoices.proforma_invoice_number_placeholder')"
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
import NumberCollisionDialog from '@/scripts/admin/components/modal-components/NumberCollisionDialog.vue'

const proformaInvoiceStore = useProformaInvoiceStore()
const companyStore = useCompanyStore()
const customFieldStore = useCustomFieldStore()

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const validationScope = 'newProformaInvoice'
let isSaving = ref(false)

// Lista de tipos de notas disponibles en el selector
const noteFieldList = ref(['customer', 'company', 'customerCustom', 'invoice', 'invoiceCustom'])

// Detectar si estamos en modo edición por el nombre de la ruta
let isEdit = computed(() => route.name === 'proformaInvoices.edit')

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
    // Onfactu — numeración diferida: OPCIONAL.
    // Si se deja vacío, se asigna al enviar la proforma.
    maxLength: helpers.withMessage(
      t('validation.name_max_length'),
      maxLength(100)
    ),
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
 * Prepara los datos calculando subtotal, total e impuestos desde los getters.
 */

// ── Onfactu — numeración diferida ──────────────────────────────────────────
const numberCollision = ref(null)
const showCollisionDialog = computed({
  get: () => numberCollision.value !== null,
  set: (val) => {
    if (!val) numberCollision.value = null
  },
})

function isUnchangedSuggestion(numberInForm) {
  const suggestion = proformaInvoiceStore.suggestedProformaInvoiceNumber
  if (!suggestion) return false
  return String(numberInForm || '').trim() === String(suggestion).trim()
}

// Al guardar como borrador, opción C:
//  - Sugerencia "clean" (MAX+1 puro) y no tocada → null (libera el número).
//  - Sugerencia "skipped" (salto por hueco) y no tocada → se persiste literal.
//  - Usuario cambió el número → se respeta el valor manual.
function resolveNumberForDraft(data) {
  if (isUnchangedSuggestion(data.proforma_invoice_number)) {
    if (!proformaInvoiceStore.suggestedProformaInvoiceNumberIsSkipped) {
      data.proforma_invoice_number = null
    }
  }
  return data
}

async function submitForm() {
  v$.value.$touch()
  if (v$.value.$invalid) return false

  isSaving.value = true

  // Clonar datos del store y añadir cálculos
  let data = cloneDeep({
    ...proformaInvoiceStore.newProformaInvoice,
    sub_total: proformaInvoiceStore.getSubTotal,
    total: proformaInvoiceStore.getTotal,
    tax: proformaInvoiceStore.getTotalTax,
  })

  // Numeración diferida: descarta sugerencia no tocada
  data = resolveNumberForDraft(data)

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
    // Captura colisión 409 también aquí por si el usuario puso un número ya ocupado
    const status = err?.response?.status
    const errorCode = err?.response?.data?.error_code
    if (status === 409 && errorCode === 'number_collision') {
      numberCollision.value = err.response.data.details || {}
    } else {
      console.error(err)
    }
  }

  isSaving.value = false
}
</script>
