<template>
  <!--
    Confirmación del cierre de mes. Enseña lo que se entrega, lo que queda
    fuera (facturas en borrador) y que el cierre es irreversible.
    Emite `confirmar` con si el cliente quiere descargar también el CSV.
  -->
  <BaseModal :show="show" @close="$emit('close')">
    <template #header>
      <div class="flex items-center gap-2">
        <BaseIcon name="ExclamationTriangleIcon" class="w-5 h-5 text-red-600" />
        <span>{{ $t('gestoria.confirm_title', { mes: preview?.nombre }) }}</span>
      </div>
    </template>

    <div class="p-6">
      <div class="grid grid-cols-2 gap-4 mb-5 sm:grid-cols-4">
        <div v-for="b in bloques" :key="b.l">
          <div class="text-xs tracking-wide text-gray-400 uppercase">{{ b.l }}</div>
          <div class="mt-1 text-lg font-semibold tabular-nums">{{ b.v }}</div>
        </div>
      </div>

      <div v-if="preview?.tiene_borradores" class="p-4 mb-5 border rounded-md bg-amber-50 border-amber-200">
        <p class="text-sm font-semibold text-amber-800">{{ $t('gestoria.drafts_title') }}</p>
        <ul class="mt-2 ml-4 text-sm list-disc text-amber-700">
          <li v-for="d in preview.borradores" :key="d.tipo">{{ d.total }} {{ d.tipo }}</li>
        </ul>
        <p class="mt-2 text-sm text-amber-700">{{ $t('gestoria.drafts_help') }}</p>
      </div>

      <div class="p-4 mb-5 border border-red-300 rounded-md bg-red-50">
        <p class="text-sm font-semibold text-red-800">{{ $t('gestoria.irreversible_title') }}</p>
        <ul class="mt-2 ml-4 space-y-1 text-sm text-red-700 list-disc">
          <li>{{ $t('gestoria.irreversible_1') }}</li>
          <li>{{ $t('gestoria.irreversible_2') }}</li>
          <li>{{ $t('gestoria.irreversible_3') }}</li>
        </ul>
      </div>

      <label class="flex items-start gap-3 cursor-pointer select-none">
        <input
          v-model="descargar"
          type="checkbox"
          class="mt-0.5 w-4 h-4 rounded border-gray-300 text-primary-500 focus:ring-primary-500"
        />
        <span>
          <span class="block text-sm font-medium text-gray-800">{{ $t('gestoria.download_csv_option') }}</span>
          <span class="block mt-0.5 text-xs text-gray-500">{{ $t('gestoria.download_csv_help') }}</span>
        </span>
      </label>
    </div>

    <template #footer>
      <div class="flex flex-wrap justify-end gap-3 px-8 py-4 border-t border-gray-200">
        <BaseButton variant="white" @click="$emit('close')">{{ $t('general.cancel') }}</BaseButton>
        <BaseButton variant="danger" :loading="cerrando" @click="$emit('confirmar', descargar)">
          {{ $t('gestoria.confirm_close') }}
        </BaseButton>
      </div>
    </template>
  </BaseModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  show: { type: Boolean, default: false },
  preview: { type: Object, default: null },
  cerrando: { type: Boolean, default: false },
  money: { type: Function, required: true },
})
defineEmits(['close', 'confirmar'])

const { t } = useI18n()
const descargar = ref(false)

// Cada vez que se abre, la casilla empieza desmarcada
watch(() => props.show, (v) => { if (v) descargar.value = false })

const bloques = computed(() => {
  const x = props.preview?.totales
  if (!x) return []
  return [
    { l: t('gestoria.facturas'), v: x.facturas },
    { l: t('gestoria.neto'), v: props.money(x.neto) + ' €' },
    { l: t('gestoria.iva'), v: props.money(x.iva) + ' €' },
    { l: t('gestoria.bruto'), v: props.money(x.bruto) + ' €' },
  ]
})
</script>
