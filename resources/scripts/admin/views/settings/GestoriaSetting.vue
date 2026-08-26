<template>
  <BaseSettingCard
    :title="$t('settings.menu_title.gestoria')"
    :description="$t('settings.gestoria.description')"
  >
    <!-- ─── Interruptor ─────────────────────────────────────────── -->
    <div class="flex items-center justify-between py-4 mt-4 border-t border-gray-200">
      <div class="pr-6">
        <p class="text-sm font-medium text-black">
          {{ $t('settings.gestoria.enable_title') }}
        </p>
        <p class="mt-1 text-sm text-gray-500">
          {{ $t('settings.gestoria.enable_help') }}
        </p>
      </div>
      <BaseSwitch
        v-model="estado.activa"
        :disabled="isSaving"
        class="flex-shrink-0"
        @update:modelValue="onToggle"
      />
    </div>

    <!-- ─── Activada, sin vincular: pedir el código ──────────────── -->
    <div
      v-if="estado.activa && !estado.vinculada && !estado.pendiente"
      class="py-6 border-t border-gray-200"
    >
      <p class="mb-4 text-sm text-gray-600">
        {{ $t('settings.gestoria.ask_code') }}
      </p>
      <div class="flex flex-wrap items-end gap-3">
        <BaseInputGroup
          :label="$t('settings.gestoria.code_label')"
          :error="codigoError"
          class="w-full sm:w-72"
        >
          <BaseInput
            v-model="codigo"
            placeholder="GEST-XXXX-00"
            :invalid="!!codigoError"
            @input="codigoError = ''"
          />
        </BaseInputGroup>
        <BaseButton
          :loading="isSaving"
          :disabled="isSaving || !codigo"
          @click="vincular"
        >
          {{ $t('settings.gestoria.send_request') }}
        </BaseButton>
      </div>
    </div>

    <!-- ─── Solicitud pendiente ─────────────────────────────────── -->
    <div v-if="estado.pendiente" class="py-6 border-t border-gray-200">
      <div class="flex gap-3 p-4 border rounded-md bg-amber-50 border-amber-200">
        <BaseIcon name="ClockIcon" class="w-5 h-5 text-amber-600 shrink-0" />
        <div>
          <p class="text-sm font-medium text-amber-800">
            {{ $t('settings.gestoria.pending_title', { gestoria: estado.gestoria?.nombre }) }}
          </p>
          <p class="mt-1 text-sm text-amber-700">
            {{ $t('settings.gestoria.pending_help') }}
          </p>
        </div>
      </div>
    </div>

    <!-- ─── Vinculada ───────────────────────────────────────────── -->
    <div v-if="estado.vinculada" class="py-6 border-t border-gray-200">
      <div class="flex gap-3 p-4 mb-6 border rounded-md bg-emerald-50 border-emerald-200">
        <BaseIcon name="CheckCircleIcon" class="w-5 h-5 text-emerald-600 shrink-0" />
        <p class="text-sm text-emerald-800">
          {{ $t('settings.gestoria.linked_help') }}
        </p>
      </div>

      <BaseInputGrid>
        <BaseInputGroup :label="$t('settings.gestoria.name')">
          <BaseInput :model-value="estado.gestoria?.nombre" disabled />
        </BaseInputGroup>
        <BaseInputGroup :label="$t('settings.gestoria.nif')">
          <BaseInput :model-value="estado.gestoria?.nif || '—'" disabled />
        </BaseInputGroup>
        <BaseInputGroup :label="$t('settings.gestoria.email')">
          <BaseInput :model-value="estado.gestoria?.email || '—'" disabled />
        </BaseInputGroup>
        <BaseInputGroup :label="$t('settings.gestoria.phone')">
          <BaseInput :model-value="estado.gestoria?.telefono || '—'" disabled />
        </BaseInputGroup>
      </BaseInputGrid>

      <div class="pt-6 mt-6 border-t border-gray-100">
        <p class="mb-3 text-sm text-gray-500">
          {{ $t('settings.gestoria.unlink_help') }}
        </p>
        <BaseButton variant="danger" :loading="isSaving" @click="confirmarDesvincular">
          {{ $t('settings.gestoria.unlink') }}
        </BaseButton>
      </div>
    </div>
  </BaseSettingCard>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import axios from 'axios'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useDialogStore } from '@/scripts/stores/dialog'

const { t } = useI18n()
const notificationStore = useNotificationStore()
const dialogStore = useDialogStore()

const isSaving = ref(false)
const codigo = ref('')
const codigoError = ref('')

const estado = reactive({
  activa: false,
  vinculada: false,
  pendiente: false,
  gestoria: null,
  subdominio: '',
})

function aplicar(data) {
  Object.assign(estado, data)
}

async function cargar() {
  try {
    const { data } = await axios.get('/api/v1/gestoria')
    aplicar(data)
  } catch (e) {
    // silencioso: la tarjeta se queda con los valores por defecto
  }
}

// El switch cambia el valor antes de llamar aquí, así que si el backend
// rechaza el cambio hay que devolverlo a su sitio.
async function onToggle(valor) {
  isSaving.value = true
  try {
    const { data } = await axios.post('/api/v1/gestoria/toggle', { activa: valor })
    aplicar(data.data)
    notificationStore.showNotification({ type: 'success', message: data.message })
  } catch (e) {
    estado.activa = !valor
    notificationStore.showNotification({
      type: 'error',
      message: e.response?.data?.message || t('general.something_went_wrong'),
    })
  } finally {
    isSaving.value = false
  }
}

async function vincular() {
  isSaving.value = true
  codigoError.value = ''
  try {
    const { data } = await axios.post('/api/v1/gestoria/vincular', { codigo: codigo.value })
    aplicar(data.data)
    codigo.value = ''
    notificationStore.showNotification({ type: 'success', message: data.message })
  } catch (e) {
    codigoError.value = e.response?.data?.message || t('general.something_went_wrong')
  } finally {
    isSaving.value = false
  }
}

function confirmarDesvincular() {
  dialogStore
    .openDialog({
      title: t('settings.gestoria.unlink_confirm_title'),
      message: t('settings.gestoria.unlink_confirm', {
        gestoria: estado.gestoria?.nombre,
      }),
      yesLabel: t('settings.gestoria.unlink'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then((res) => {
      if (res) desvincular()
    })
}

async function desvincular() {
  isSaving.value = true
  try {
    const { data } = await axios.post('/api/v1/gestoria/desvincular')
    aplicar(data.data)
    notificationStore.showNotification({ type: 'success', message: data.message })
  } catch (e) {
    notificationStore.showNotification({
      type: 'error',
      message: e.response?.data?.message || t('general.something_went_wrong'),
    })
  } finally {
    isSaving.value = false
  }
}

onMounted(cargar)
</script>
