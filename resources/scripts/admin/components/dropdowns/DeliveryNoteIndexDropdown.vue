<template>
  <BaseDropdown>
    <template #activator>
      <BaseButton v-if="route.name === 'deliveryNotes.view'" variant="primary">
        <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-white" />
      </BaseButton>
      <BaseIcon v-else name="EllipsisHorizontalIcon" class="h-5 text-gray-500" />
    </template>

    <!-- Edit -->
    <router-link
      v-if="userStore.hasAbilities(abilities.EDIT_DELIVERY_NOTE)"
      :to="`/admin/delivery-notes/${row.id}/edit`"
    >
      <BaseDropdownItem v-show="row.allow_edit">
        <BaseIcon name="PencilIcon" class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500" />
        {{ $t('general.edit') }}
      </BaseDropdownItem>
    </router-link>

    <!-- Copy PDF url -->
    <BaseDropdownItem v-if="route.name === 'deliveryNotes.view'" @click="copyPdfUrl">
      <BaseIcon name="LinkIcon" class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500" />
      {{ $t('general.copy_pdf_url') }}
    </BaseDropdownItem>

    <!-- View -->
    <router-link
      v-if="route.name !== 'deliveryNotes.view' && userStore.hasAbilities(abilities.VIEW_DELIVERY_NOTE)"
      :to="`/admin/delivery-notes/${row.id}/view`"
    >
      <BaseDropdownItem>
        <BaseIcon name="EyeIcon" class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500" />
        {{ $t('general.view') }}
      </BaseDropdownItem>
    </router-link>

    <!-- Send email -->
    <BaseDropdownItem
      v-if="row.status === 'DRAFT' && userStore.hasAbilities(abilities.SEND_DELIVERY_NOTE)"
      @click="sendDeliveryNote(row)"
    >
      <BaseIcon name="PaperAirplaneIcon" class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500" />
      {{ $t('invoices.send_invoice') }}
    </BaseDropdownItem>

    <!-- Mark as sent -->
    <BaseDropdownItem
      v-if="row.status === 'DRAFT' && route.name !== 'deliveryNotes.view'"
      @click="onMarkAsSent(row.id)"
    >
      <BaseIcon name="CheckCircleIcon" class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500" />
      {{ $t('general.mark_as_sent') }}
    </BaseDropdownItem>

    <!-- Onfactu: Mark as delivered -->
    <BaseDropdownItem
      v-if="row.status !== 'DELIVERED'"
      @click="onMarkAsDelivered(row.id)"
    >
      <BaseIcon name="CheckBadgeIcon" class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500" />
      {{ $t('delivery_notes.mark_as_delivered') }}
    </BaseDropdownItem>

    <!-- Clone -->
    <BaseDropdownItem @click="cloneDeliveryNoteData(row)">
      <BaseIcon name="DocumentDuplicateIcon" class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500" />
      {{ $t('clone_delivery_note') }}
    </BaseDropdownItem>

    <!-- Delete -->
    <BaseDropdownItem
      v-if="userStore.hasAbilities(abilities.DELETE_DELIVERY_NOTE)"
      @click="removeDeliveryNote(row.id)"
    >
      <BaseIcon name="TrashIcon" class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500" />
      {{ $t('general.delete') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>

<script setup>
import { useDeliveryNoteStore } from '@/scripts/admin/stores/delivery-note'
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

const deliveryNoteStore = useDeliveryNoteStore()
const modalStore = useModalStore()
const notificationStore = useNotificationStore()
const dialogStore = useDialogStore()
const userStore = useUserStore()

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const utils = inject('utils')

async function removeDeliveryNote(id) {
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
        deliveryNoteStore.deleteDeliveryNote(id).then((res) => {
          if (res.data.success) {
            router.push('/admin/delivery-notes')
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
        deliveryNoteStore.markAsSent({ id }).then(() => {
          props.table && props.table.refresh()
        })
      }
    })
}

// Onfactu: marca el albarán como entregado. El backend asigna número
// automáticamente si era borrador sin número.
async function onMarkAsDelivered(id) {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('delivery_notes.confirm_deliver'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'primary',
      hideNoButton: false,
      size: 'lg',
    })
    .then((response) => {
      if (response) {
        deliveryNoteStore.markAsDelivered({ id }).then(() => {
          props.table && props.table.refresh()
        })
      }
    })
}

async function sendDeliveryNote(deliveryNote) {
  modalStore.openModal({
    title: t('general.send') + ' ' + t('delivery_note'),
    componentName: 'SendInvoiceModal',
    id: deliveryNote.id,
    data: deliveryNote,
    docType: 'delivery_note',
    variant: 'sm',
  })
}

async function cloneDeliveryNoteData(data) {
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
        deliveryNoteStore.cloneDeliveryNote(data).then((res) => {
          router.push(`/admin/delivery-notes/${res.data.delivery_note.id}/edit`)
        })
      }
    })
}

function copyPdfUrl() {
  let pdfUrl = `${window.location.origin}/delivery-notes/pdf/${props.row.unique_hash}`
  utils.copyTextToClipboard(pdfUrl)
  notificationStore.showNotification({
    type: 'success',
    message: t('general.copied_pdf_url_clipboard'),
  })
}
</script>
