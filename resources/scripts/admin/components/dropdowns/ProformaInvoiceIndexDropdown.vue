<template>
  <BaseDropdown>
    <template #activator>
      <BaseButton v-if="route.name === 'proformaInvoices.view'" variant="primary">
        <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-white" />
      </BaseButton>
      <BaseIcon v-else name="EllipsisHorizontalIcon" class="h-5 text-gray-500" />
    </template>

    <!-- Edit -->
    <router-link
      v-if="userStore.hasAbilities(abilities.EDIT_PROFORMA_INVOICE)"
      :to="`/admin/proforma-invoices/${row.id}/edit`"
    >
      <BaseDropdownItem v-show="row.allow_edit">
        <BaseIcon name="PencilIcon" class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500" />
        {{ $t('general.edit') }}
      </BaseDropdownItem>
    </router-link>

    <!-- Copy PDF url -->
    <BaseDropdownItem v-if="route.name === 'proformaInvoices.view'" @click="copyPdfUrl">
      <BaseIcon name="LinkIcon" class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500" />
      {{ $t('general.copy_pdf_url') }}
    </BaseDropdownItem>

    <!-- View -->
    <router-link
      v-if="route.name !== 'proformaInvoices.view' && userStore.hasAbilities(abilities.VIEW_PROFORMA_INVOICE)"
      :to="`/admin/proforma-invoices/${row.id}/view`"
    >
      <BaseDropdownItem>
        <BaseIcon name="EyeIcon" class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500" />
        {{ $t('general.view') }}
      </BaseDropdownItem>
    </router-link>

    <!-- Send email -->
    <BaseDropdownItem
      v-if="row.status === 'DRAFT' && userStore.hasAbilities(abilities.SEND_PROFORMA_INVOICE)"
      @click="sendProformaInvoice(row)"
    >
      <BaseIcon name="PaperAirplaneIcon" class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500" />
      {{ $t('invoices.send_invoice') }}
    </BaseDropdownItem>

    <!-- Mark as sent -->
    <BaseDropdownItem
      v-if="row.status === 'DRAFT' && route.name !== 'proformaInvoices.view'"
      @click="onMarkAsSent(row.id)"
    >
      <BaseIcon name="CheckCircleIcon" class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500" />
      {{ $t('general.mark_as_sent') }}
    </BaseDropdownItem>

    <!-- Convert to Invoice (Onfactu) -->
    <BaseDropdownItem
      v-if="row.status !== 'DRAFT' && userStore.hasAbilities(abilities.CREATE_INVOICE)"
      @click="convertToInvoice(row)"
    >
      <BaseIcon name="DocumentTextIcon" class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500" />
      {{ $t('convert_to_invoice') }}
    </BaseDropdownItem>

    <!-- Clone -->
    <BaseDropdownItem @click="cloneProformaInvoiceData(row)">
      <BaseIcon name="DocumentDuplicateIcon" class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500" />
      {{ $t('clone_proforma') }}
    </BaseDropdownItem>

    <!-- Delete -->
    <BaseDropdownItem
      v-if="userStore.hasAbilities(abilities.DELETE_PROFORMA_INVOICE)"
      @click="removeProformaInvoice(row.id)"
    >
      <BaseIcon name="TrashIcon" class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500" />
      {{ $t('general.delete') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>

<script setup>
import { useProformaInvoiceStore } from '@/scripts/admin/stores/proforma-invoice'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useDialogStore } from '@/scripts/stores/dialog'
import { useModalStore } from '@/scripts/stores/modal'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '@/scripts/admin/stores/user'
import { inject } from 'vue'
import abilities from '@/scripts/admin/stub/abilities'

const props = defineProps({
  row: {
    type: Object,
    default: null,
  },
  table: {
    type: Object,
    default: null,
  },
  loadData: {
    type: Function,
    default: () => {},
  },
})

const proformaInvoiceStore = useProformaInvoiceStore()
const modalStore = useModalStore()
const notificationStore = useNotificationStore()
const dialogStore = useDialogStore()
const userStore = useUserStore()

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const utils = inject('utils')

async function removeProformaInvoice(id) {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('general.are_you_sure'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then((res) => {
      if (res) {
        proformaInvoiceStore.deleteProformaInvoice(id).then((res) => {
          if (res.data.success) {
            router.push('/admin/proforma-invoices')
            props.table && props.table.refresh()
          }
        })
      }
    })
}

async function onMarkAsSent(id) {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('general.are_you_sure'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'primary',
      hideNoButton: false,
      size: 'lg',
    })
    .then((response) => {
      if (response) {
        proformaInvoiceStore.markAsSent({ id }).then(() => {
          props.table && props.table.refresh()
        })
      }
    })
}

async function sendProformaInvoice(proformaInvoice) {
  modalStore.openModal({
    title: t('general.send') + ' ' + t('proforma_invoice'),
    componentName: 'SendInvoiceModal',
    id: proformaInvoice.id,
    data: proformaInvoice,
    docType: 'proforma_invoice',
    variant: 'sm',
  })
}

async function cloneProformaInvoiceData(data) {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('general.are_you_sure'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'primary',
      hideNoButton: false,
      size: 'lg',
    })
    .then((res) => {
      if (res) {
        proformaInvoiceStore.cloneProformaInvoice(data).then((res) => {
          router.push(`/admin/proforma-invoices/${res.data.proforma_invoice.id}/edit`)
        })
      }
    })
}

// Onfactu — convertir proforma a factura
async function convertToInvoice(row) {
  const confirmed = await dialogStore.openDialog({
    title: t('convert_to_invoice'),
    message: '¿Quieres crear una factura nueva a partir de esta proforma? La factura nacerá como borrador, podrás revisarla antes de aprobarla.',
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'primary',
    hideNoButton: false,
    size: 'lg',
  })

  if (!confirmed) return

  try {
    const res = await proformaInvoiceStore.convertToInvoice(row.id)
    if (res?.data?.data?.id) {
      notificationStore.showNotification({
        type: 'success',
        message: 'Factura creada a partir de la proforma',
      })
      // Llevar al usuario a editar la nueva factura para que pueda revisar
      // antes de aprobar (la factura nace como borrador SIN número).
      router.push(`/admin/invoices/${res.data.data.id}/edit`)
    }
  } catch (err) {
    notificationStore.showNotification({
      type: 'error',
      message: err?.response?.data?.message || t('general.action_failed'),
    })
  }
}

function copyPdfUrl() {
  let pdfUrl = `${window.location.origin}/proforma-invoices/pdf/${props.row.unique_hash}`
  utils.copyTextToClipboard(pdfUrl)
  notificationStore.showNotification({
    type: 'success',
    message: t('general.copied_pdf_url_clipboard'),
  })
}
</script>
