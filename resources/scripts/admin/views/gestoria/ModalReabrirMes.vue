<!--
  Onfactu v.1.16.0 — Abrir un mes cerrado para corregirlo.
  Explica en tres líneas qué se puede cambiar y pide el motivo, que se envía a
  la gestoría. Solo lo ve el propietario de la cuenta (puede_reabrir).
  Reglas: App\Services\ReaperturaMes.
-->
<template>
  <BaseModal :show="show" @close="$emit('close')">
    <template #header>
      <span>{{ $t('gestoria.reopen_title', { mes: nombre }) }}</span>
    </template>

    <div class="p-6 space-y-4">
      <ul class="ml-4 space-y-1 text-sm text-gray-600 list-disc">
        <li>{{ $t('gestoria.reopen_help_1') }}</li>
        <li>{{ $t('gestoria.reopen_help_2') }}</li>
        <li>{{ $t('gestoria.reopen_help_3') }}</li>
      </ul>

      <BaseInputGroup :label="$t('gestoria.reopen_reason')" required :error="error">
        <BaseTextarea v-model="motivo" rows="3" :placeholder="$t('gestoria.reopen_reason_placeholder')" />
      </BaseInputGroup>
    </div>

    <template #footer>
      <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200">
        <BaseButton variant="white" @click="$emit('close')">{{ $t('general.cancel') }}</BaseButton>
        <BaseButton variant="primary" :loading="enviando" :disabled="motivo.trim().length < 5" @click="abrir">
          {{ $t('gestoria.reopen_confirm') }}
        </BaseButton>
      </div>
    </template>
  </BaseModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import axios from 'axios'
import { useNotificationStore } from '@/scripts/stores/notification'

const props = defineProps({
  show: { type: Boolean, default: false },
  mes: { type: Object, default: null },
  nombres: { type: Array, default: () => [] },
})
const emit = defineEmits(['close', 'hecho'])
const avisos = useNotificationStore()

const motivo = ref('')
const error = ref('')
const enviando = ref(false)
const nombre = computed(() => (props.mes ? props.nombres[props.mes.month - 1] + ' ' + props.mes.year : ''))

watch(() => props.show, (v) => { if (v) { motivo.value = ''; error.value = '' } })

async function abrir() {
  enviando.value = true
  error.value = ''
  try {
    const { data } = await axios.post('/api/v1/closed-months/reabrir', {
      year: props.mes.year, month: props.mes.month, motivo: motivo.value.trim(),
    })
    avisos.showNotification({ type: 'success', message: data.message })
    emit('hecho')
  } catch (e) {
    error.value = e.response?.data?.message || ''
  } finally {
    enviando.value = false
  }
}
</script>
