<template>
  <span :class="badgeColorClasses">
    <slot />
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: {
    type: String,
    required: false,
    default: '',
  },
})

const badgeColorClasses = computed(() => {
  switch (props.status) {
    case 'DRAFT':
      return 'bg-orange-300 bg-opacity-30 px-2  py-1 text-sm  text-orange-900 uppercase font-normal text-center '
    case 'SENT':
      return ' bg-yellow-400 bg-opacity-25 px-2 py-1 text-sm  text-yellow-900 uppercase font-normal text-center '
    case 'VIEWED':
      return 'bg-blue-400 bg-opacity-25 px-2  py-1 text-sm  text-blue-900 uppercase font-normal text-center'
    case 'EXPIRED':
      return 'bg-red-300 bg-opacity-25 px-2 py-1 text-sm  text-red-800 uppercase font-normal text-center'
    case 'ACCEPTED':
      return 'bg-green-400 bg-opacity-25 px-2 py-1 text-sm  text-green-800 uppercase font-normal text-center'
    case 'REJECTED':
      return 'bg-purple-300 bg-opacity-25 px-2 py-1 text-sm  text-purple-800 uppercase font-normal text-center'
    case 'COMPLETED':
      return 'bg-green-500 bg-opacity-25 px-2 py-1 text-sm  text-green-900 uppercase font-normal text-center'
    case 'DUE':
      return 'bg-yellow-500 bg-opacity-25 px-2 py-1 text-sm  text-yellow-900 uppercase font-normal text-center'
    case 'OVERDUE':
      return 'bg-red-300 bg-opacity-50 px-2 py-1 text-sm  text-red-900 uppercase font-normal text-center'
    case 'UNPAID':
      return 'bg-yellow-500 bg-opacity-25 px-2 py-1 text-sm  text-yellow-900 uppercase font-normal text-center'
    case 'PARTIALLY_PAID':
      return 'bg-blue-400 bg-opacity-25 px-2 py-1 text-sm  text-blue-900 uppercase font-normal text-center'
    case 'PAID':
      return 'bg-green-500 bg-opacity-25 px-2 py-1 text-sm  text-green-900 uppercase font-normal text-center'
    case 'DELIVERED':
      return 'bg-green-500 bg-opacity-25 px-2 py-1 text-sm  text-green-900 uppercase font-normal text-center'
    case 'APPROVED':
      return 'bg-[#38d587] bg-opacity-25 px-2 py-1 text-sm  text-[#1a6b3a] uppercase font-normal text-center'
    case 'VERIFACTU_PENDING':
      return 'bg-amber-400 bg-opacity-30 px-2 py-1 text-sm  text-amber-900 uppercase font-normal text-center animate-pulse'
    default:
      return 'bg-gray-500 bg-opacity-25 px-2 py-1 text-sm  text-gray-900 uppercase font-normal text-center'
  }
})
</script>
