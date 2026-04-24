<template>
  <SelectTemplateModal />
  <ItemModal />
  <TaxTypeModal />
  <NumberWarningDialog
    :visible="showNumberWarning"
    :loading="isSaving || isSavingDraft"
    :title="$t('estimates.number_warning_title')"
    :messages="numberWarningMessages"
    @confirm="onConfirmNumberWarning"
    @cancel="onCancelNumberWarning"
  />
  <SalesTax
    v-if="salesTaxEnabled && (!isLoadingContent || route.query.customer)"
    :store="estimateStore"
    store-prop="newEstimate"
    :is-edit="isEdit"
    :customer="estimateStore.newEstimate.customer"
  />

  <BasePage class="relative estimate-create-page">
    <form @submit.prevent="submitForm">
      <BasePageHeader :title="pageTitle">
        <BaseBreadcrumb>
          <BaseBreadcrumbItem
            :title="$t('general.home')"
            to="/admin/dashboard"
          />
          <BaseBreadcrumbItem
            :title="$t('estimates.estimate', 2)"
            to="/admin/estimates"
          />
          <BaseBreadcrumbItem
            v-if="$route.name === 'estimates.edit'"
            :title="$t('estimates.edit_estimate')"
            to="#"
            active
          />
          <BaseBreadcrumbItem
            v-else
            :title="$t('estimates.new_estimate')"
            to="#"
            active
          />
        </BaseBreadcrumb>

        <template #actions>
          <router-link
            v-if="$route.name === 'estimates.edit'"
            :to="`/estimates/pdf/${estimateStore.newEstimate.unique_hash}`"
            target="_blank"
          >
            <BaseButton class="mr-3" variant="primary-outline" type="button">
              <span class="flex">
                {{ $t('general.view_pdf') }}
              </span>
            </BaseButton>
          </router-link>

          <!--
            Onfactu: 'Guardar como borrador' → sin número de serie.
            'Guardar' → asigna número y status normal.
          -->
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
              <BaseIcon
                v-if="!isSavingDraft"
                name="DocumentIcon"
                :class="slotProps.class"
              />
            </template>
            {{ $t('estimates.save_as_draft') }}
          </BaseButton>

          <BaseButton
            :loading="isSaving"
            :disabled="isSaving || isSavingDraft"
            :content-loading="isLoadingContent"
            variant="primary"
            type="submit"
          >
            <template #left="slotProps">
              <BaseIcon
                v-if="!isSaving"
                :class="slotProps.class"
                name="ArrowDownOnSquareIcon"
              />
            </template>
            {{ $t('estimates.save_estimate') }}
          </BaseButton>
        </template>
      </BasePageHeader>

      <!-- Select Customer & Basic Fields  -->
      <EstimateBasicFields
        :v="v$"
        :is-loading="isLoadingContent"
        :is-edit="isEdit"
      />

      <BaseScrollPane>
        <!-- Estimate Items -->
        <Items
          :currency="estimateStore.newEstimate.selectedCurrency"
          :is-loading="isLoadingContent"
          :item-validation-scope="estimateValidationScope"
          :store="estimateStore"
          store-prop="newEstimate"
        />

        <!-- Estimate Footer Section -->
        <div
          class="
            block
            mt-10
            estimate-foot
            lg:flex lg:justify-between lg:items-start
          "
        >
          <div class="relative w-full lg:w-1/2">
            <!-- Estimate Custom Notes -->
            <NoteFields
              :store="estimateStore"
              store-prop="newEstimate"
              :fields="estimateNoteFieldList"
              type="Estimate"
            />

            <!-- Estimate Custom Fields -->
            <EstimateCustomFields
              type="Estimate"
              :is-edit="isEdit"
              :is-loading="isLoadingContent"
              :store="estimateStore"
              store-prop="newEstimate"
              :custom-field-scope="estimateValidationScope"
              class="mb-6"
            />

            <!-- Estimate Template Button-->
            <SelectTemplate
              :store="estimateStore"
              component-name="EstimateTemplate"
              store-prop="newEstimate"
              :is-mark-as-default="isMarkAsDefault"
            />
          </div>

          <Total
            :currency="estimateStore.newEstimate.selectedCurrency"
            :is-loading="isLoadingContent"
            :store="estimateStore"
            store-prop="newEstimate"
            tax-popup-type="estimate"
          />
        </div>
      </BaseScrollPane>
    </form>
  </BasePage>
</template>

<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import { cloneDeep } from 'lodash'
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
import { useModuleStore } from '@/scripts/admin/stores/module'
import { useEstimateStore } from '@/scripts/admin/stores/estimate'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useCustomFieldStore } from '@/scripts/admin/stores/custom-field'

import Items from '@/scripts/admin/components/estimate-invoice-common/CreateItems.vue'
import Total from '@/scripts/admin/components/estimate-invoice-common/CreateTotal.vue'
import SelectTemplate from '@/scripts/admin/components/estimate-invoice-common/SelectTemplateButton.vue'
import EstimateCustomFields from '@/scripts/admin/components/custom-fields/CreateCustomFields.vue'
import NoteFields from '@/scripts/admin/components/estimate-invoice-common/CreateNotesField.vue'
import EstimateBasicFields from './EstimateCreateBasicFields.vue'
import SelectTemplateModal from '@/scripts/admin/components/modal-components/SelectTemplateModal.vue'
import TaxTypeModal from '@/scripts/admin/components/modal-components/TaxTypeModal.vue'
import ItemModal from '@/scripts/admin/components/modal-components/ItemModal.vue'
import SalesTax from '@/scripts/admin/components/estimate-invoice-common/SalesTax.vue'
import NumberWarningDialog from '@/scripts/admin/components/dialogs/NumberWarningDialog.vue'
import axios from 'axios'

const estimateStore = useEstimateStore()
const moduleStore = useModuleStore()
const companyStore = useCompanyStore()
const customFieldStore = useCustomFieldStore()
const { t } = useI18n()

const estimateValidationScope = 'newEstimate'
let isSaving = ref(false)
let isSavingDraft = ref(false)
const isMarkAsDefault = ref(false)

// Onfactu: aviso de inconsistencia en numeración/fecha.
const showNumberWarning = ref(false)
const numberWarningMessages = ref([])
const pendingSaveOptions = ref(null)

const estimateNoteFieldList = ref([
  'customer',
  'company',
  'customerCustom',
  'estimate',
  'estimateCustom',
])

let route = useRoute()
let router = useRouter()

let isLoadingContent = computed(() => estimateStore.isFetchingInitialSettings)

let pageTitle = computed(() =>
  isEdit.value ? t('estimates.edit_estimate') : t('estimates.new_estimate')
)

let isEdit = computed(() => route.name === 'estimates.edit')

// Onfactu: 'Guardar como borrador' solo visible si aún no hay número.
const showDraftButton = computed(() => {
  if (!isEdit.value) return true
  return !estimateStore.newEstimate.estimate_number
})

const salesTaxEnabled = computed(() => {
  return (
    companyStore.selectedCompanySettings.sales_tax_us_enabled === 'YES' &&
    moduleStore.salesTaxUSEnabled
  )
})

const rules = {
  estimate_date: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  estimate_number: {
    // Onfactu: obligatorio solo si no es borrador. Al guardar como borrador
    // se limpia el número antes de enviar; la validación del backend permite
    // nullable en ese caso.
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
  exchange_rate: {
    required: requiredIf(function () {
      helpers.withMessage(t('validation.required'), required)
      return estimateStore.showExchangeRate
    }),
    decimal: helpers.withMessage(t('validation.valid_exchange_rate'), decimal),
  },
}

const v$ = useVuelidate(
  rules,
  computed(() => estimateStore.newEstimate),
  { $scope: estimateValidationScope }
)

watch(
  () => estimateStore.newEstimate.customer,
  (newVal) => {
    if (newVal && newVal.currency) {
      estimateStore.newEstimate.selectedCurrency = newVal.currency
    } else {
      estimateStore.newEstimate.selectedCurrency =
        companyStore.selectedCompanyCurrency
    }
  }
)

estimateStore.resetCurrentEstimate()
customFieldStore.resetCustomFields()
v$.value.$reset
estimateStore.fetchEstimateInitialSettings(isEdit.value)

async function submitForm() {
  await preSave({ clearNumber: false })
}

// Onfactu: "Guardar como borrador" → sin número de serie.
async function saveAsDraft() {
  await doSave({ clearNumber: true })
}

async function preSave({ clearNumber }) {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return false
  }

  // Consultar info de la serie al backend.
  let info = null
  try {
    const resp = await axios.get('/api/v1/estimates/next-number-info')
    info = resp.data
  } catch (err) {
    console.warn('Could not fetch estimates next-number-info:', err)
    return doSave({ clearNumber })
  }

  const warnings = []
  const currentNumber = estimateStore.newEstimate.estimate_number
  const currentDate = estimateStore.newEstimate.estimate_date

  // Aviso 1: salto o número por debajo del esperado.
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
        warnings.push(
          t('estimates.warning_number_skip', {
            expected: expectedNumber,
            entered: currentNumber,
          })
        )
      } else if (enteredSeq < expectedSeq && !isEdit.value) {
        warnings.push(
          t('estimates.warning_number_below', {
            entered: currentNumber,
            last: info.last_number,
          })
        )
      }
    }
  }

  // Aviso 2: fecha anterior a la última creada.
  if (info.last_numbered_date && currentDate) {
    const lastDate = new Date(info.last_numbered_date)
    const curDate = new Date(currentDate)
    if (curDate < lastDate) {
      warnings.push(
        t('estimates.warning_date_earlier', {
          date: info.last_numbered_date,
        })
      )
    }
  }

  // Aviso 3: año distinto al actual.
  if (currentDate) {
    const entered = new Date(currentDate)
    if (!isNaN(entered.getTime())) {
      const enteredYear = entered.getFullYear()
      const currentYear = new Date().getFullYear()
      if (enteredYear !== currentYear) {
        warnings.push(
          t('estimates.warning_year_mismatch', {
            entered: enteredYear,
            current: currentYear,
          })
        )
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
  const isDraft = clearNumber
  if (isDraft) {
    isSavingDraft.value = true
  } else {
    isSaving.value = true
  }

  let data = cloneDeep({
    ...estimateStore.newEstimate,
    sub_total: estimateStore.getSubTotal,
    total: estimateStore.getTotal,
    tax: estimateStore.getTotalTax,
  })

  if (isDraft) {
    data.estimate_number  = null
    data.sequence_number  = null
    data.status           = 'DRAFT'
  }

  if (data.discount_per_item === 'YES') {
    data.items.forEach((item, index) => {
      if (item.discount_type === 'fixed'){
        data.items[index].discount = Math.round(item.discount * 100)
      }
    })
  }
  else {
    if (data.discount_type === 'fixed'){
      data.discount = Math.round(data.discount * 100)
    }
  }

  if (
    !estimateStore.newEstimate.tax_per_item === 'YES'
    && data.taxes.length
  ){
    data.tax_type_ids = data.taxes.map(_t => _t.tax_type_id)
  }

  const action = isEdit.value
    ? estimateStore.updateEstimate
    : estimateStore.addEstimate

  try {
    let res = await action(data)

    if (res.data.data) {
      router.push(`/admin/estimates/${res.data.data.id}/view`)
    }
  } catch (err) {
    console.error(err)
  }

  isSaving.value = false
  isSavingDraft.value = false
}
</script>
