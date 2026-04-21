<template>
  <TransitionRoot as="template" :show="visible">
    <Dialog
      as="div"
      static
      class="fixed inset-0 z-20 overflow-y-auto"
      :open="visible"
      @close="onCancel"
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
            class="inline-block px-6 pt-6 pb-6 overflow-hidden text-left align-bottom transition-all bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-md sm:w-full relative"
          >
            <!-- Title -->
            <div class="text-center">
              <DialogTitle
                as="h3"
                class="text-xl font-semibold leading-6 text-gray-900"
              >
                {{ $t('verifactu.before_approve_title') }}
              </DialogTitle>

              <div class="mt-4">
                <p class="text-sm text-gray-500 leading-relaxed">
                  {{ $t('verifactu.before_approve_message') }}
                </p>
              </div>

              <!-- Onfactu — aviso si el usuario puso un número manual distinto
                   de la sugerencia secuencial. -->
              <div
                v-if="manualNumber"
                class="mt-4 px-4 py-3 bg-amber-50 border border-amber-200 rounded-md text-left"
              >
                <div class="flex items-start">
                  <svg
                    class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                    />
                  </svg>
                  <div class="ml-2 text-sm text-amber-800">
                    <strong>{{ $t('verifactu.manual_number_warning_title') }}</strong>
                    <p class="mt-1">
                      {{ $t('verifactu.manual_number_warning_body', {
                        manual: manualNumber,
                        suggested: suggestedNumber || '—',
                      }) }}
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Buttons -->
            <div class="mt-6 flex flex-col items-center gap-3">
              <BaseButton
                variant="primary"
                class="w-full justify-center"
                :loading="loading"
                :disabled="loading"
                @click="onApprove"
              >
                {{ $t('verifactu.approve_invoice') }}
              </BaseButton>

              <button
                type="button"
                class="text-sm font-medium text-primary-500 hover:text-primary-600"
                :disabled="loading"
                @click="onSaveDraft"
              >
                {{ $t('verifactu.save_as_draft') }}
              </button>

              <button
                type="button"
                class="text-sm text-gray-400 hover:text-gray-500"
                :disabled="loading"
                @click="onCancel"
              >
                {{ $t('general.cancel') }}
              </button>
            </div>
          </div>
        </TransitionChild>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup>
import {
  Dialog,
  DialogOverlay,
  DialogTitle,
  TransitionChild,
  TransitionRoot,
} from '@headlessui/vue'

defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  // Onfactu — numeración diferida:
  // Si el usuario ha puesto un invoice_number distinto al secuencial sugerido,
  // se le muestra un aviso amber antes de aprobar.
  manualNumber: {
    type: String,
    default: '',
  },
  suggestedNumber: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['approve', 'save-draft', 'cancel'])

function onApprove() {
  emit('approve')
}

function onSaveDraft() {
  emit('save-draft')
}

function onCancel() {
  emit('cancel')
}
</script>
