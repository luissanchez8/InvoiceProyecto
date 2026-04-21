<template>
  <TransitionRoot as="template" :show="visible">
    <Dialog
      as="div"
      static
      class="fixed inset-0 z-30 overflow-y-auto"
      :open="visible"
      @close="onClose"
    >
      <div
        class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0"
      >
        <TransitionChild
          as="template"
          enter="ease-out duration-300"
          enter-from="opacity-0"
          enter-to="opacity-100"
          leave="ease-in duration-200"
          leave-from="opacity-100"
          leave-to="opacity-0"
        >
          <DialogOverlay
            class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"
          />
        </TransitionChild>

        <span
          class="hidden sm:inline-block sm:align-middle sm:h-screen"
          aria-hidden="true"
        >&#8203;</span>

        <TransitionChild
          as="template"
          enter="ease-out duration-300"
          enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
          enter-to="opacity-100 translate-y-0 sm:scale-100"
          leave="ease-in duration-200"
          leave-from="opacity-100 translate-y-0 sm:scale-100"
          leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
          <div
            class="inline-block px-6 pt-6 pb-6 overflow-hidden text-left align-bottom transition-all bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full relative"
          >
            <!-- Icon + Title -->
            <div class="flex items-start">
              <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
              </div>
              <div class="ml-4 flex-1">
                <DialogTitle as="h3" class="text-lg font-semibold text-gray-900">
                  {{ $t('number_collision.title') }}
                </DialogTitle>
                <p class="mt-2 text-sm text-gray-600 leading-relaxed">
                  {{ $t('number_collision.body', {
                    number: details?.attempted_number || details?.conflicting_number || '—',
                  }) }}
                </p>
              </div>
            </div>

            <!-- Detalles del conflicto -->
            <div class="mt-5 px-4 py-3 bg-gray-50 border border-gray-200 rounded-md">
              <div class="text-xs text-gray-500 uppercase tracking-wide mb-2">
                {{ $t('number_collision.conflicting_document') }}
              </div>
              <dl class="text-sm text-gray-700 space-y-1">
                <div class="flex justify-between">
                  <dt class="font-medium">{{ $t('number_collision.number') }}:</dt>
                  <dd class="font-mono">{{ details?.conflicting_number || '—' }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="font-medium">{{ $t('number_collision.status') }}:</dt>
                  <dd>{{ details?.conflicting_status || '—' }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="font-medium">{{ $t('number_collision.id') }}:</dt>
                  <dd>#{{ details?.conflicting_id || '—' }}</dd>
                </div>
              </dl>
            </div>

            <div class="mt-4 text-sm text-gray-600 leading-relaxed">
              {{ $t('number_collision.instructions') }}
            </div>

            <!-- Buttons -->
            <div class="mt-6 flex flex-col sm:flex-row sm:justify-end gap-3">
              <BaseButton
                variant="primary-outline"
                @click="onClose"
              >
                {{ $t('general.close') }}
              </BaseButton>

              <router-link
                v-if="details?.conflicting_id && targetRoute"
                :to="targetRoute"
                @click="onClose"
              >
                <BaseButton variant="primary">
                  {{ $t('number_collision.edit_conflicting') }}
                </BaseButton>
              </router-link>
            </div>
          </div>
        </TransitionChild>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup>
import { computed } from 'vue'
import {
  Dialog,
  DialogOverlay,
  DialogTitle,
  TransitionChild,
  TransitionRoot,
} from '@headlessui/vue'

const props = defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
  details: {
    type: Object,
    default: null,
  },
  // Tipo de documento: 'invoice' | 'estimate' | 'proforma-invoice' | 'delivery-note'
  // Se usa para construir la ruta al documento conflictivo.
  docType: {
    type: String,
    default: 'invoice',
  },
})

const emit = defineEmits(['close'])

// Construye la ruta al documento conflictivo según el tipo.
const targetRoute = computed(() => {
  if (!props.details?.conflicting_id) return null
  const id = props.details.conflicting_id

  const routes = {
    'invoice': `/admin/invoices/${id}/edit`,
    'estimate': `/admin/estimates/${id}/edit`,
    'proforma-invoice': `/admin/proforma-invoices/${id}/edit`,
    'delivery-note': `/admin/delivery-notes/${id}/edit`,
  }

  return routes[props.docType] || null
})

function onClose() {
  emit('close')
}
</script>
