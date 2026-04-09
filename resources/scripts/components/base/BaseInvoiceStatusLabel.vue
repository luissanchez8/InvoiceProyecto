<template>
  {{ labelStatus }}
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps({
  status: {
    type: String,
    required: false,
    default: '',
  },
})

const labelStatus = computed(() => {
  switch (props.status) {
    case 'DRAFT':
      return t('general.draft')
    case 'SENT':
      return t('general.sent')
    case 'VIEWED':
      return t('invoices.viewed')
    case 'COMPLETED':
      return t('invoices.completed')
    case 'DUE':
      return t('general.due')
    case 'OVERDUE':
      return t('invoices.overdue')
    case 'UNPAID':
      return t('invoices.unpaid')
    case 'PARTIALLY_PAID':
      return t('invoices.partially_paid')
    case 'PAID':
      return t('invoices.paid')
    case 'ACCEPTED':
      return t('estimates.accepted')
    case 'REJECTED':
      return t('estimates.rejected')
    case 'DELIVERED':
      return t('general.delivered')
    case 'APPROVED':
      return t('verifactu.approved')
    default:
      return props.status
  }
})
</script>
