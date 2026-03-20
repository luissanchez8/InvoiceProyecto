<!--
  Vista: Detalle de Albarán (View)

  Replica el patrón completo de invoices/View.vue:
  - Sidebar izquierdo con lista navegable de albaranes
  - Área principal con cabecera (botones de acción) + iframe con el PDF
-->
<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { debounce } from 'lodash'
import { useDeliveryNoteStore } from '@/scripts/admin/stores/delivery-note'
import { useDialogStore } from '@/scripts/stores/dialog'
import { useUserStore } from '@/scripts/admin/stores/user'
import LoadingIcon from '@/scripts/components/icons/LoadingIcon.vue'
import abilities from '@/scripts/admin/stub/abilities'

const deliveryNoteStore = useDeliveryNoteStore()
const dialogStore = useDialogStore()
const userStore = useUserStore()
const { t } = useI18n()
const route = useRoute()

// --- Estado del documento actual ---
const deliveryNoteData = ref(null)

// --- Estado del sidebar ---
const sidebarList = ref(null)
const currentPageNumber = ref(1)
const lastPageNumber = ref(1)
const sidebarListSection = ref(null)
const isLoading = ref(false)

const searchData = reactive({
  orderBy: null,
  orderByField: null,
  searchText: null,
})

const pageTitle = computed(() => deliveryNoteData.value?.delivery_note_number || '')

const shareableLink = computed(() => {
  if (!deliveryNoteData.value?.unique_hash) return ''
  return `/delivery-notes/pdf/${deliveryNoteData.value.unique_hash}?preview`
})

const getOrderBy = computed(() => {
  return searchData.orderBy === 'asc' || searchData.orderBy == null
})

watch(route, (to) => {
  if (to.name === 'deliveryNotes.view') {
    loadDeliveryNote()
  }
})

async function loadDeliveryNote() {
  let response = await deliveryNoteStore.fetchDeliveryNote(route.params.id)
  if (response.data) {
    deliveryNoteData.value = { ...response.data.data }
  }
}

async function loadSidebarList(pageNumber, fromScrollListener = false) {
  if (isLoading.value) return

  let params = {}
  if (searchData.searchText) params.search = searchData.searchText
  if (searchData.orderBy) params.orderBy = searchData.orderBy
  if (searchData.orderByField) params.orderByField = searchData.orderByField

  isLoading.value = true
  let response = await deliveryNoteStore.fetchDeliveryNotes({ page: pageNumber, ...params })
  isLoading.value = false

  sidebarList.value = sidebarList.value || []
  sidebarList.value = [...sidebarList.value, ...response.data.data]

  currentPageNumber.value = pageNumber || 1
  lastPageNumber.value = response.data.last_page || 1

  let found = sidebarList.value.find((item) => item.id == route.params.id)
  if (!fromScrollListener && !found && currentPageNumber.value < lastPageNumber.value && Object.keys(params).length === 0) {
    loadSidebarList(++currentPageNumber.value)
  }
  if (found && !fromScrollListener) {
    setTimeout(() => scrollToItem(), 500)
  }
}

function scrollToItem() {
  const el = document.getElementById(`deliverynote-${route.params.id}`)
  if (el) {
    el.scrollIntoView({ behavior: 'smooth' })
    el.classList.add('shake')
    addScrollListener()
  }
}

function addScrollListener() {
  if (!sidebarListSection.value) return
  sidebarListSection.value.addEventListener('scroll', (ev) => {
    if (ev.target.scrollTop > 0 && ev.target.scrollTop + ev.target.clientHeight > ev.target.scrollHeight - 200) {
      if (currentPageNumber.value < lastPageNumber.value) {
        loadSidebarList(++currentPageNumber.value, true)
      }
    }
  })
}

function hasActiveUrl(id) {
  return route.params.id == id
}

async function onSearched() {
  sidebarList.value = []
  loadSidebarList()
}

function sortData() {
  searchData.orderBy = searchData.orderBy === 'asc' ? 'desc' : 'asc'
  onSearched()
}

async function onMarkAsSent() {
  const confirmed = await dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('general.are_you_sure'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'primary',
  })
  if (confirmed) {
    await deliveryNoteStore.markAsSent({ id: deliveryNoteData.value.id })
    deliveryNoteData.value.status = 'SENT'
  }
}

async function onMarkAsDelivered() {
  const confirmed = await dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('general.are_you_sure'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'primary',
  })
  if (confirmed) {
    await deliveryNoteStore.markAsDelivered({ id: deliveryNoteData.value.id })
    deliveryNoteData.value.status = 'DELIVERED'
  }
}

loadSidebarList()
loadDeliveryNote()
onSearched = debounce(onSearched, 500)
</script>

<template>
  <BasePage v-if="deliveryNoteData" class="xl:pl-96 xl:ml-8">
    <BasePageHeader :title="pageTitle">
      <template #actions>
        <BaseButton
          v-if="deliveryNoteData.status === 'DRAFT'"
          variant="primary-outline"
          class="mr-3 text-sm"
          @click="onMarkAsSent"
        >
          {{ $t('general.mark_as_sent') }}
        </BaseButton>

        <BaseButton
          v-if="deliveryNoteData.status === 'SENT'"
          variant="primary"
          class="mr-3 text-sm"
          @click="onMarkAsDelivered"
        >
          {{ $t('mark_as_delivered') }}
        </BaseButton>

        <router-link
          v-if="deliveryNoteData.allow_edit && userStore.hasAbilities(abilities.EDIT_DELIVERY_NOTE)"
          :to="`/admin/delivery-notes/${deliveryNoteData.id}/edit`"
        >
          <BaseButton variant="gray" class="text-sm">
            {{ $t('general.edit') }}
          </BaseButton>
        </router-link>
      </template>
    </BasePageHeader>

    <!-- =============== SIDEBAR IZQUIERDO =============== -->
    <div class="fixed top-0 left-0 hidden h-full pt-16 pb-[6.4rem] ml-56 bg-white xl:ml-64 w-88 xl:block">
      <div class="flex items-center justify-between px-4 pt-8 pb-2 border border-gray-200 border-solid height-full">
        <div class="mb-6">
          <BaseInput
            v-model="searchData.searchText"
            :placeholder="$t('general.search')"
            type="text"
            variant="gray"
            @input="onSearched()"
          >
            <template #right>
              <BaseIcon name="MagnifyingGlassIcon" class="h-5 text-gray-400" />
            </template>
          </BaseInput>
        </div>
        <div class="flex mb-6 ml-3">
          <BaseButton class="ml-1" size="md" variant="gray" @click="sortData">
            <BaseIcon v-if="getOrderBy" name="BarsArrowUpIcon" />
            <BaseIcon v-else name="BarsArrowDownIcon" />
          </BaseButton>
        </div>
      </div>

      <div ref="sidebarListSection" class="h-full overflow-y-scroll border-l border-gray-200 border-solid base-scroll">
        <div v-for="(item, index) in sidebarList" :key="index">
          <router-link
            v-if="item"
            :id="'deliverynote-' + item.id"
            :to="`/admin/delivery-notes/${item.id}/view`"
            :class="[
              'flex justify-between side-invoice p-4 cursor-pointer hover:bg-gray-100 items-center border-l-4 border-transparent',
              { 'bg-gray-100 border-l-4 border-primary-500 border-solid': hasActiveUrl(item.id) },
            ]"
            style="border-bottom: 1px solid rgba(185, 193, 209, 0.41)"
          >
            <div class="flex-2">
              <BaseText
                :text="item.customer?.name || '-'"
                class="pr-2 mb-2 text-sm not-italic font-normal leading-5 text-black capitalize truncate"
              />
              <div class="mt-1 mb-2 text-xs not-italic font-medium leading-5 text-gray-600">
                {{ item.delivery_note_number }}
              </div>
              <BaseBadge
                :variant="item.status === 'DELIVERED' ? 'success' : item.status === 'SENT' ? 'primary' : 'gray'"
                class="px-1 text-xs"
              >
                {{ item.status }}
              </BaseBadge>
            </div>
            <div class="flex-1 whitespace-nowrap right">
              <BaseFormatMoney
                v-if="item.show_prices"
                class="mb-2 text-xl not-italic font-semibold leading-8 text-right text-gray-900 block"
                :amount="item.total"
                :currency="item.customer?.currency"
              />
              <div class="text-sm not-italic font-normal leading-5 text-right text-gray-600">
                {{ item.formattedDeliveryNoteDate }}
              </div>
            </div>
          </router-link>
        </div>
        <div v-if="isLoading" class="flex justify-center p-4 items-center">
          <LoadingIcon class="h-6 m-1 animate-spin text-primary-400" />
        </div>
        <p v-if="!sidebarList?.length && !isLoading" class="flex justify-center px-4 mt-5 text-sm text-gray-600">
          {{ $t('general.no_results') }}
        </p>
      </div>
    </div>

    <!-- =============== IFRAME PDF =============== -->
    <div class="flex flex-col min-h-0 mt-8 overflow-hidden" style="height: 75vh">
      <iframe
        :src="shareableLink"
        class="flex-1 border border-gray-400 border-solid bg-white rounded-md frame-style"
      />
    </div>
  </BasePage>
</template>
