<!--
  Vista: Detalle de Factura Proforma (View)

  Replica el patrón completo de invoices/View.vue:
  - Sidebar izquierdo con lista navegable de proformas (búsqueda, ordenación, scroll infinito)
  - Área principal con cabecera (botones de acción) + iframe con el PDF
-->
<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { debounce } from 'lodash'
import { useProformaInvoiceStore } from '@/scripts/admin/stores/proforma-invoice'
import { useDialogStore } from '@/scripts/stores/dialog'
import { useModalStore } from '@/scripts/stores/modal'
import { useUserStore } from '@/scripts/admin/stores/user'
import SendInvoiceModal from '@/scripts/admin/components/modal-components/SendInvoiceModal.vue'
import NumberCollisionDialog from '@/scripts/admin/components/modal-components/NumberCollisionDialog.vue'
import ProformaInvoiceDropdown from '@/scripts/admin/components/dropdowns/ProformaInvoiceIndexDropdown.vue'
import LoadingIcon from '@/scripts/components/icons/LoadingIcon.vue'
import abilities from '@/scripts/admin/stub/abilities'

const proformaInvoiceStore = useProformaInvoiceStore()
const dialogStore = useDialogStore()
const modalStore = useModalStore()
const userStore = useUserStore()
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

// --- Estado del documento actual ---
const proformaInvoiceData = ref(null)

// --- Estado del sidebar (lista lateral) ---
const sidebarList = ref(null)
const currentPageNumber = ref(1)
const lastPageNumber = ref(1)
const sidebarListSection = ref(null)
const isLoading = ref(false)

// Onfactu — numeración diferida: modal de colisión
const numberCollision = ref(null)
const showCollisionDialog = computed({
  get: () => numberCollision.value !== null,
  set: (val) => {
    if (!val) numberCollision.value = null
  },
})

const searchData = reactive({
  orderBy: null,
  orderByField: null,
  searchText: null,
})

// --- Computados ---
const pageTitle = computed(() => proformaInvoiceData.value?.proforma_invoice_number || t('proforma_invoices.draft_number'))

const shareableLink = computed(() => {
  if (!proformaInvoiceData.value?.unique_hash) return ''
  return `/proforma-invoices/pdf/${proformaInvoiceData.value.unique_hash}`
})

const getOrderBy = computed(() => {
  return searchData.orderBy === 'asc' || searchData.orderBy == null
})

// --- Watchers ---
// Recargar al navegar entre proformas
watch(route, (to) => {
  if (to.name === 'proformaInvoices.view') {
    loadProformaInvoice()
  }
})

// --- Funciones de carga ---

/** Carga los datos del documento actual */
async function loadProformaInvoice() {
  let response = await proformaInvoiceStore.fetchProformaInvoice(route.params.id)
  if (response.data) {
    proformaInvoiceData.value = { ...response.data.data }
  }
}

/** Carga la lista del sidebar con paginación y scroll infinito */
async function loadSidebarList(pageNumber, fromScrollListener = false) {
  if (isLoading.value) return

  let params = {}
  if (searchData.searchText) params.search = searchData.searchText
  if (searchData.orderBy) params.orderBy = searchData.orderBy
  if (searchData.orderByField) params.orderByField = searchData.orderByField

  isLoading.value = true
  let response = await proformaInvoiceStore.fetchProformaInvoices({ page: pageNumber, ...params })
  isLoading.value = false

  sidebarList.value = sidebarList.value || []
  sidebarList.value = [...sidebarList.value, ...response.data.data]

  currentPageNumber.value = pageNumber || 1
  lastPageNumber.value = response.data.last_page || 1

  // Si el documento actual no está en la lista y hay más páginas, cargar siguiente
  let found = sidebarList.value.find((item) => item.id == route.params.id)
  if (!fromScrollListener && !found && currentPageNumber.value < lastPageNumber.value && Object.keys(params).length === 0) {
    loadSidebarList(++currentPageNumber.value)
  }

  // Scroll al documento actual
  if (found && !fromScrollListener) {
    setTimeout(() => scrollToItem(), 500)
  }
}

function scrollToItem() {
  const el = document.getElementById(`proforma-${route.params.id}`)
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

// --- Acciones sobre el documento ---

async function onMarkAsSent() {
  const confirmed = await dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('general.are_you_sure'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'primary',
  })
  if (confirmed) {
    try {
      await proformaInvoiceStore.markAsSent({ id: proformaInvoiceData.value.id })
      proformaInvoiceData.value.status = 'SENT'

      // Refrescar la proforma para ver el número recién asignado
      proformaInvoiceStore.fetchProformaInvoice(proformaInvoiceData.value.id).then((res) => {
        if (res?.data?.data) {
          proformaInvoiceData.value = res.data.data
        }
      }).catch(() => {})
    } catch (err) {
      // Onfactu — numeración diferida: captura 409 de colisión
      const status = err?.response?.status
      const errorCode = err?.response?.data?.error_code
      if (status === 409 && errorCode === 'number_collision') {
        numberCollision.value = err.response.data.details || {}
      } else {
        console.error(err)
      }
    }
  }
}

async function onConvertToInvoice() {
  const confirmed = await dialogStore.openDialog({
    title: t('convert_to_invoice'),
    message: t('general.are_you_sure'),
    yesLabel: t('convert_to_invoice'),
    noLabel: t('general.cancel'),
    variant: 'primary',
  })
  if (confirmed) {
    await proformaInvoiceStore.convertToInvoice(proformaInvoiceData.value.id)
    router.push({ name: 'invoices.index' })
  }
}

async function onSendProformaInvoice() {
  modalStore.openModal({
    title: t('general.send') + ' ' + t('proforma_invoice'),
    componentName: 'SendInvoiceModal',
    id: proformaInvoiceData.value.id,
    data: proformaInvoiceData.value,
    docType: 'proforma_invoice',
  })
}

function updateSentProformaInvoice() {
  let pos = sidebarList.value.findIndex(
    (item) => item.id === proformaInvoiceData.value.id
  )
  if (sidebarList.value[pos]) {
    sidebarList.value[pos].status = 'SENT'
    proformaInvoiceData.value.status = 'SENT'
  }
}

// --- Inicialización ---
loadSidebarList()
loadProformaInvoice()
onSearched = debounce(onSearched, 500)
</script>

<template>
  <SendInvoiceModal @update="updateSentProformaInvoice" />
  <NumberCollisionDialog
    :visible="showCollisionDialog"
    :details="numberCollision"
    doc-type="proforma-invoice"
    @close="numberCollision = null"
  />

  <BasePage v-if="proformaInvoiceData" class="xl:pl-96 xl:ml-8">
    <!-- Cabecera con botones de acción -->
    <BasePageHeader :title="pageTitle">
      <template #actions>
        <div class="text-sm mr-3">
          <BaseButton
            v-if="proformaInvoiceData.status === 'DRAFT'"
            variant="primary-outline"
            @click="onMarkAsSent"
          >
            {{ $t('general.mark_as_sent') }}
          </BaseButton>
        </div>

        <BaseButton
          v-if="proformaInvoiceData.status === 'DRAFT' && userStore.hasAbilities(abilities.SEND_PROFORMA_INVOICE)"
          variant="primary"
          class="text-sm"
          @click="onSendProformaInvoice"
        >
          {{ $t('invoices.send_invoice') }}
        </BaseButton>

        <BaseButton
          v-if="proformaInvoiceData.status !== 'REJECTED' && !proformaInvoiceData.converted_invoice_id"
          variant="primary"
          class="ml-3 text-sm"
          @click="onConvertToInvoice"
        >
          {{ $t('convert_to_invoice') }}
        </BaseButton>

        <ProformaInvoiceDropdown
          class="ml-3"
          :row="proformaInvoiceData"
          :load-data="loadSidebarList"
        />
      </template>
    </BasePageHeader>

    <!-- =============== SIDEBAR IZQUIERDO =============== -->
    <div class="fixed top-0 left-0 hidden h-full pt-16 pb-[6.4rem] ml-56 bg-white xl:ml-64 w-88 xl:block">
      <!-- Barra de búsqueda y ordenación -->
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

      <!-- Lista de proformas con scroll infinito -->
      <div ref="sidebarListSection" class="h-full overflow-y-scroll border-l border-gray-200 border-solid base-scroll">
        <div v-for="(item, index) in sidebarList" :key="index">
          <router-link
            v-if="item"
            :id="'proforma-' + item.id"
            :to="`/admin/proforma-invoices/${item.id}/view`"
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
                {{ item.proforma_invoice_number || $t('proforma_invoices.draft_number') }}
              </div>
              <BaseInvoiceStatusBadge :status="item.status" class="px-1 text-xs">
                <BaseInvoiceStatusLabel :status="item.status" />
              </BaseInvoiceStatusBadge>
            </div>
            <div class="flex-1 whitespace-nowrap right">
              <BaseFormatMoney
                class="mb-2 text-xl not-italic font-semibold leading-8 text-right text-gray-900 block"
                :amount="item.total"
                :currency="item.customer?.currency"
              />
              <div class="text-sm not-italic font-normal leading-5 text-right text-gray-600">
                {{ item.formattedProformaInvoiceDate }}
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
