<!--
  Onfactu v.1.16.0 — Volver a cerrar un mes abierto para corregirlo.
  Enseña lo que se ha cambiado y cómo quedan los totales antes de cerrar; al
  cerrar se entregan a la gestoría. Reglas: App\Services\ReaperturaMes.
-->
<template>
  <BaseModal :show="show" @close="$emit('close')">
    <template #header>
      <span>{{ $t('gestoria.reclose_title', { mes: datos?.nombre || '' }) }}</span>
    </template>

    <div class="p-6 space-y-5">
      <div v-if="cargando" class="py-6 text-sm text-center text-gray-400">…</div>

      <template v-else-if="datos">
        <div>
          <p class="mb-2 text-sm font-semibold text-gray-800">{{ $t('gestoria.changes_title') }}</p>
          <ul v-if="datos.cambios.length" class="ml-4 space-y-1 text-sm text-gray-600 list-disc">
            <li v-for="(c, i) in datos.cambios" :key="i">{{ c }}</li>
          </ul>
          <p v-else class="text-sm text-gray-500">{{ $t('gestoria.no_changes') }}</p>
        </div>

        <div v-if="datos.diferencias.length">
          <p class="mb-2 text-sm font-semibold text-gray-800">{{ $t('gestoria.changed_totals') }}</p>
          <div v-for="d in datos.diferencias" :key="d.etiqueta" class="flex justify-between py-1 text-sm border-b border-gray-100 last:border-0">
            <span class="text-gray-500">{{ d.etiqueta }}</span>
            <span class="tabular-nums"><span class="text-gray-400">{{ d.antes }}</span> → <strong>{{ d.despues }}</strong></span>
          </div>
        </div>

        <p class="text-xs text-gray-500">{{ $t('gestoria.reclose_help') }}</p>
      </template>
    </div>

    <template #footer>
      <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200">
        <BaseButton variant="white" @click="$emit('close')">{{ $t('general.cancel') }}</BaseButton>
        <BaseButton variant="primary" :loading="enviando" :disabled="!datos" @click="cerrar">
          {{ $t('gestoria.reclose_confirm') }}
        </BaseButton>
      </div>
    </template>
  </BaseModal>
</template>

<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import { useI18n } from 'vue-i18n'
import { useNotificationStore } from '@/scripts/stores/notification'

const props = defineProps({
  show: { type: Boolean, default: false },
  mes: { type: Object, default: null },
})
const emit = defineEmits(['close', 'hecho'])
const { t } = useI18n()
const avisos = useNotificationStore()

const datos = ref(null)
const cargando = ref(false)
const enviando = ref(false)

watch(() => props.show, async (v) => {
  if (!v || !props.mes) return
  datos.value = null
  cargando.value = true
  try {
    const { data } = await axios.get('/api/v1/closed-months/reabierto', {
      params: { year: props.mes.year, month: props.mes.month },
    })
    datos.value = data
  } catch (e) {
    avisos.showNotification({ type: 'error', message: e.response?.data?.message || t('general.something_went_wrong') })
    emit('close')
  } finally {
    cargando.value = false
  }
})

async function cerrar() {
  enviando.value = true
  try {
    const { data } = await axios.post('/api/v1/closed-months/recerrar', { year: props.mes.year, month: props.mes.month })
    avisos.showNotification({ type: 'success', message: data.message })
    emit('hecho')
  } catch (e) {
    avisos.showNotification({ type: 'error', message: e.response?.data?.message || t('general.something_went_wrong') })
  } finally {
    enviando.value = false
  }
}
</script>
