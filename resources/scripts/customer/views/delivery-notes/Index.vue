<template>
  <BasePage>
    <!-- Page Header -->
    <BasePageHeader :title="$t('delivery-notes.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem
          :title="$t('general.home')"
          :to="`/${globalStore.companySlug}/customer/dashboard`"
        />
        <BaseBreadcrumbItem :title="$t('delivery-notes.delivery_note', 2)" to="#" active />
      </BaseBreadcrumb>
      <template #actions>
        <BaseButton
          v-show="delivery_noteStore.totalDeliveryNotes"
          variant="primary-outline"
          @click="toggleFilter"
        >
          {{ $t('general.filter') }}
          <template #right="slotProps">
            <BaseIcon
              v-if="!showFilters"
              name="FunnelIcon"
              :class="slotProps.class"
            />
            <BaseIcon v-else name="XMarkIcon" :class="slotProps.class" />
          </template>
        </BaseButton>
      </template>
    </BasePageHeader>

    <BaseFilterWrapper v-show="showFilters" @clear="clearFilter">
      <BaseInputGroup :label="$t('delivery-notes.status')" class="px-3">
        <BaseSelectInput
          v-model="filters.status"
          :options="status"
          searchable
          :allow-empty="false"
          :placeholder="$t('general.select_a_status')"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('delivery-notes.delivery_note_number')"
        color="black-light"
        class="px-3 mt-2"
      >
        <BaseInput v-model="filters.delivery_note_number">
          <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-gray-500" />
          <BaseIcon name="HashtagIcon" class="h-5 ml-3 text-gray-600" />
        </BaseInput>
      </BaseInputGroup>

      <BaseInputGroup :label="$t('general.from')" class="px-3">
        <BaseDatePicker
          v-model="filters.from_date"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <div
        class="hidden w-8 h-0 mx-4 border border-gray-400 border-solid xl:block"
        style="margin-top: 1.5rem"
      />

      <BaseInputGroup :label="$t('general.to')" class="px-3">
        <BaseDatePicker
          v-model="filters.to_date"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>
    </BaseFilterWrapper>

    <BaseEmptyPlaceholder
      v-if="showEmptyScreen"
      :title="$t('delivery-notes.no_delivery-notes')"
      :description="$t('delivery-notes.list_of_delivery-notes')"
    >
      <MoonwalkerIcon class="mt-5 mb-4" />
    </BaseEmptyPlaceholder>

    <div v-show="!showEmptyScreen" class="relative table-container">
      <BaseTable
        ref="table"
        :data="fetchData"
        :columns="itemColumns"
        :placeholder-count="delivery_noteStore.totalDeliveryNotes >= 20 ? 10 : 5"
        class="mt-10"
      >
        <template #cell-delivery_note_date="{ row }">
          {{ row.data.formatted_delivery_note_date }}
        </template>

        <template #cell-delivery_note_number="{ row }">
          <router-link
            :to="{ path: `delivery-notes/${row.data.id}/view` }"
            class="font-medium text-primary-500"
          >
            {{ row.data.delivery_note_number }}
          </router-link>
        </template>

        <template #cell-due_amount="{ row }">
          <BaseFormatMoney
            :amount="row.data.total"
            :currency="row.data.customer.currency"
          />
        </template>

        <template #cell-status="{ row }">
          <BaseDeliveryNoteStatusBadge :status="row.data.status" class="px-3 py-1">
            <BaseDeliveryNoteStatusLabel :status="row.data.status" />
          </BaseDeliveryNoteStatusBadge>
        </template>

        <template #cell-paid_status="{ row }">
          <BaseDeliveryNoteStatusBadge
            :status="row.data.paid_status"
            class="px-3 py-1"
          >
            <BaseDeliveryNoteStatusLabel :status="row.data.paid_status" />
          </BaseDeliveryNoteStatusBadge>
        </template>

        <template #cell-actions="{ row }">
          <BaseDropdown>
            <template #activator>
              <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-gray-500" />
            </template>
            <router-link :to="`delivery-notes/${row.data.id}/view`">
              <BaseDropdownItem>
                <BaseIcon name="EyeIcon" class="h-5 mr-3 text-gray-600" />
                {{ $t('general.view') }}
              </BaseDropdownItem>
            </router-link>
          </BaseDropdown>
        </template>
      </BaseTable>
    </div>
  </BasePage>
</template>

<script setup>
import { useDeliveryNoteStore } from '@/scripts/customer/stores/delivery_note'
import { debouncedWatch } from '@vueuse/core'
import BaseTable from '@/scripts/components/base/base-table/BaseTable.vue'
import { ref, computed, reactive, inject, onMounted } from 'vue'
import { useGlobalStore } from '@/scripts/customer/stores/global'
import { useRoute } from 'vue-router'
import MoonwalkerIcon from '@/scripts/components/icons/empty/MoonwalkerIcon.vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

//Utils
const utils = inject('utils')
const route = useRoute()
// local state
const table = ref(null)
let isFetchingInitialData = ref(true)
let showFilters = ref(false)
const status = ref([
  {label: t('general.draft'), value: 'DRAFT'},
  {label: t('general.due'), value: 'DUE'},
  {label: t('general.sent'), value: 'SENT'},
  {label: t('delivery-notes.viewed'), value: 'VIEWED'},
  {label: t('delivery-notes.completed'), value: 'COMPLETED'}
])
const filters = reactive({
  status: '',
  from_date: '',
  to_date: '',
  delivery_note_number: '',
})

// store

const delivery_noteStore = useDeliveryNoteStore()
const globalStore = useGlobalStore()

// DeliveryNote Table columns Data

const currency = computed(() => {
  return globalStore.currency
})

const itemColumns = computed(() => {
  return [
    {
      key: 'delivery_note_date',
      label: t('delivery-notes.date'),
      thClass: 'extra',
      tdClass: 'font-medium text-gray-900',
    },
    { key: 'delivery_note_number', label: t('delivery-notes.number') },

    { key: 'status', label: t('delivery-notes.status') },
    { key: 'paid_status', label: t('delivery-notes.paid_status') },
    {
      key: 'due_amount',
      label: t('dashboard.recent_delivery-notes_card.amount_due'),
    },
    {
      key: 'actions',
      thClass: 'text-right',
      tdClass: 'text-right text-sm font-medium',
      sortable: false,
    },
  ]
})

// computed props

const showEmptyScreen = computed(() => {
  return !delivery_noteStore.totalDeliveryNotes && !isFetchingInitialData.value
})

//watch

debouncedWatch(
  filters,
  () => {
    setFilters()
  },
  { debounce: 500 }
)

//methods

function refreshTable() {
  table.value.refresh()
}

function setFilters() {
  refreshTable()
}

function clearFilter() {
  filters.status = ''
  filters.from_date = ''
  filters.to_date = ''
  filters.delivery_note_number = ''
}

function toggleFilter() {
  if (showFilters.value) {
    clearFilter()
  }

  showFilters.value = !showFilters.value
}

async function fetchData({ page, sort }) {
  let data = {
    status: filters.status.value,
    delivery_note_number: filters.delivery_note_number,
    from_date: filters.from_date,
    to_date: filters.to_date,
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  isFetchingInitialData.value = true

  let response = await delivery_noteStore.fetchDeliveryNotes(data, globalStore.companySlug)

  isFetchingInitialData.value = false

  return {
    data: response.data.data,
    pagination: {
      totalPages: response.data.meta.last_page,
      currentPage: page,
      totalCount: response.data.meta.total,
      limit: 10,
    },
  }
}
</script>
