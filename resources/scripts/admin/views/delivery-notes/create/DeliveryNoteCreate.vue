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

const deliveryNoteStore = useDeliveryNoteStore()
const companyStore = useCompanyStore()
const customFieldStore = useCustomFieldStore()

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const validationScope = 'newDeliveryNote'
let isSaving = ref(false)

const noteFieldList = ref(['customer', 'company', 'customerCustom', 'invoice', 'invoiceCustom'])

let isEdit = computed(() => route.name === 'deliveryNotes.edit')

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
  v$.value.$touch()
  if (v$.value.$invalid) return false

  isSaving.value = true

  let data = cloneDeep({
    ...deliveryNoteStore.newDeliveryNote,
    sub_total: deliveryNoteStore.getSubTotal,
    total: deliveryNoteStore.getTotal,
    tax: deliveryNoteStore.getTotalTax,
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
}
</script>
