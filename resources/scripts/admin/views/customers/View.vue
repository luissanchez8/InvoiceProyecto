<template>
  <BasePage class="xl:pl-96">
    <BasePageHeader :title="pageTitle">
      <template #actions>
        <router-link
          v-if="userStore.hasAbilities(abilities.EDIT_CUSTOMER)"
          :to="`/admin/customers/${route.params.id}/edit`"
        >
          <BaseButton
            class="mr-3"
            variant="primary-outline"
            :content-loading="isLoading"
          >
            {{ $t('general.edit') }}
          </BaseButton>
        </router-link>

        <BaseDropdown
          v-if="canCreateTransaction()"
          position="bottom-end"
          :content-loading="isLoading"
        >
          <template #activator>
            <BaseButton
              class="mr-3"
              variant="primary"
              :content-loading="isLoading"
            >
              {{ $t('customers.new_transaction') }}
            </BaseButton>
          </template>

          <router-link
            v-if="userStore.hasAbilities(abilities.CREATE_ESTIMATE)"
            :to="`/admin/estimates/create?customer=${$route.params.id}`"
          >
            <BaseDropdownItem class="">
              <BaseIcon name="DocumentIcon" class="mr-3 text-gray-600" />
              {{ $t('estimates.new_estimate') }}
            </BaseDropdownItem>
          </router-link>

          <router-link
            v-if="userStore.hasAbilities(abilities.CREATE_INVOICE)"
            :to="`/admin/invoices/create?customer=${$route.params.id}`"
          >
            <BaseDropdownItem>
              <BaseIcon name="DocumentTextIcon" class="mr-3 text-gray-600" />
              {{ $t('invoices.new_invoice') }}
            </BaseDropdownItem>
          </router-link>

          <router-link
            v-if="userStore.hasAbilities(abilities.CREATE_PAYMENT)"
            :to="`/admin/payments/create?customer=${$route.params.id}`"
          >
            <BaseDropdownItem>
              <BaseIcon name="CreditCardIcon" class="mr-3 text-gray-600" />
              {{ $t('payments.new_payment') }}
            </BaseDropdownItem>
          </router-link>

          <router-link
            v-if="userStore.hasAbilities(abilities.CREATE_EXPENSE)"
            :to="`/admin/expenses/create?customer=${$route.params.id}`"
          >
            <BaseDropdownItem>
              <BaseIcon name="CalculatorIcon" class="mr-3 text-gray-600" />
              {{ $t('expenses.new_expense') }}
            </BaseDropdownItem>
          </router-link>
        </BaseDropdown>

        <CustomerDropdown
          v-if="hasAtleastOneAbility()"
          :class="{
            'ml-3': isLoading,
          }"
          :row="customerStore.selectedViewCustomer"
          :load-data="refreshData"
        />
      </template>
    </BasePageHeader>

    <!-- Customer View Sidebar -->
    <CustomerViewSidebar />

    <!-- Pestañas -->
    <div class="mt-6">
      <div class="border-b border-gray-200">
        <nav class="-mb-px flex space-x-6 overflow-x-auto" aria-label="Tabs">
          <button
            v-for="tab in visibleTabs"
            :key="tab.key"
            class="whitespace-nowrap py-3 px-1 border-b-2 text-sm font-medium transition-colors"
            :class="activeTab === tab.key
              ? 'border-primary-500 text-primary-600'
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
            @click="activeTab = tab.key"
          >
            {{ tab.label }}
          </button>
        </nav>
      </div>

      <div class="mt-6">
        <!-- Dashboard (gráfica original) -->
        <CustomerChart v-if="activeTab === 'dashboard'" />

        <!-- Documentos -->
        <CustomerDocumentsTab
          v-if="activeTab === 'invoices'"
          :customer-id="route.params.id"
          doc-type="invoices"
          view-route="invoices"
          number-field="invoice_number"
          empty-title="Sin facturas"
          empty-description="Este cliente no tiene facturas."
        />

        <CustomerDocumentsTab
          v-if="activeTab === 'estimates'"
          :customer-id="route.params.id"
          doc-type="estimates"
          view-route="estimates"
          number-field="estimate_number"
          empty-title="Sin presupuestos"
          empty-description="Este cliente no tiene presupuestos."
        />

        <CustomerDocumentsTab
          v-if="activeTab === 'payments'"
          :customer-id="route.params.id"
          doc-type="payments"
          view-route="payments"
          number-field="payment_number"
          empty-title="Sin pagos"
          empty-description="Este cliente no tiene pagos registrados."
        />

        <CustomerDocumentsTab
          v-if="activeTab === 'proforma-invoices'"
          :customer-id="route.params.id"
          doc-type="proforma-invoices"
          view-route="proforma-invoices"
          number-field="proforma_invoice_number"
          empty-title="Sin facturas proforma"
          empty-description="Este cliente no tiene facturas proforma."
        />

        <CustomerDocumentsTab
          v-if="activeTab === 'delivery-notes'"
          :customer-id="route.params.id"
          doc-type="delivery-notes"
          view-route="delivery-notes"
          number-field="delivery_note_number"
          empty-title="Sin albaranes"
          empty-description="Este cliente no tiene albaranes."
        />

        <CustomerDocumentsTab
          v-if="activeTab === 'expenses'"
          :customer-id="route.params.id"
          doc-type="expenses"
          view-route="expenses"
          number-field="expense_number"
          empty-title="Sin gastos"
          empty-description="Este cliente no tiene gastos."
        />
      </div>
    </div>
  </BasePage>
</template>

<script setup>
import CustomerViewSidebar from './partials/CustomerViewSidebar.vue'
import CustomerChart from './partials/CustomerChart.vue'
import CustomerDocumentsTab from './partials/CustomerDocumentsTab.vue'
import { ref, computed, inject } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useCustomerStore } from '@/scripts/admin/stores/customer'
import { useDialogStore } from '@/scripts/stores/dialog'
import { useUserStore } from '@/scripts/admin/stores/user'
import { useGlobalStore } from '@/scripts/admin/stores/global'
import CustomerDropdown from '@/scripts/admin/components/dropdowns/CustomerIndexDropdown.vue'
import abilities from '@/scripts/admin/stub/abilities'

const utils = inject('utils')
const dialogStore = useDialogStore()
const customerStore = useCustomerStore()
const userStore = useUserStore()
const globalStore = useGlobalStore()
const { t } = useI18n()

const router = useRouter()
const route = useRoute()
const customer = ref(null)
const activeTab = ref('dashboard')

const pageTitle = computed(() => {
  return customerStore.selectedViewCustomer.customer
    ? customerStore.selectedViewCustomer.customer.name
    : ''
})

let isLoading = computed(() => {
  return customerStore.isFetchingViewData
})

// Pestañas disponibles, filtradas por disabledMenuOptions
const allTabs = [
  { key: 'dashboard', label: 'Dashboard', segment: null },
  { key: 'invoices', label: 'Facturas', segment: 'invoices' },
  { key: 'estimates', label: 'Presupuestos', segment: 'estimates' },
  { key: 'payments', label: 'Pagos', segment: 'payments' },
  { key: 'proforma-invoices', label: 'Facturas Proforma', segment: 'proforma-invoices' },
  { key: 'delivery-notes', label: 'Albaranes', segment: 'delivery-notes' },
  { key: 'expenses', label: 'Gastos', segment: 'expenses' },
]

const visibleTabs = computed(() => {
  const disabled = globalStore.disabledMenuOptions || []
  return allTabs.filter(tab => {
    if (!tab.segment) return true
    return !disabled.includes(tab.segment)
  })
})

function canCreateTransaction() {
  return userStore.hasAbilities([
    abilities.CREATE_ESTIMATE,
    abilities.CREATE_INVOICE,
    abilities.CREATE_PAYMENT,
    abilities.CREATE_EXPENSE,
  ])
}

function hasAtleastOneAbility() {
  return userStore.hasAbilities([
    abilities.DELETE_CUSTOMER,
    abilities.EDIT_CUSTOMER,
  ])
}

function refreshData() {
  router.push('/admin/customers')
}
</script>
