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
              <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-yellow-100">
                <BaseIcon name="ExclamationTriangleIcon" class="h-6 w-6 text-yellow-600" />
              </div>

              <DialogTitle
                as="h3"
                class="text-xl font-semibold leading-6 text-gray-900"
              >
                {{ title }}
              </DialogTitle>

              <div class="mt-4 space-y-3">
                <p
                  v-for="(msg, idx) in messages"
                  :key="idx"
                  class="text-sm text-gray-600 leading-relaxed"
                >
                  {{ msg }}
                </p>
              </div>
            </div>

            <!-- Buttons -->
            <div class="mt-6 flex flex-col items-center gap-3">
              <BaseButton
                variant="primary"
                class="w-full justify-center"
                :loading="loading"
                :disabled="loading"
                @click="onConfirm"
              >
                {{ $t('general.continue_anyway') }}
              </BaseButton>

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
/**
 * Onfactu: modal de aviso que se muestra antes de guardar un documento
 * si se detectan inconsistencias (salto de numeración, fecha anterior
 * a la del último documento numerado, etc.). El usuario puede elegir
 * "Continuar de todos modos" o "Cancelar".
 *
 * Uso:
 *   <NumberWarningDialog
 *     :visible="showWarning"
 *     :loading="isSaving"
 *     :title="'Atención'"
 *     :messages="['msg 1', 'msg 2']"
 *     @confirm="onConfirmWarning"
 *     @cancel="onCancelWarning"
 *   />
 */
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
  title: {
    type: String,
    default: '',
  },
  messages: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['confirm', 'cancel'])

function onConfirm() {
  emit('confirm')
}

function onCancel() {
  emit('cancel')
}
</script>
