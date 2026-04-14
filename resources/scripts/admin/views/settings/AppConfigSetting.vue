<template>
  <div class="relative">
    <BaseSettingCard
      :title="'Configuración de la instancia'"
      :description="'Activa o desactiva las opciones del menú para este usuario.'"
    >
      <div v-if="isLoading" class="flex justify-center py-10">
        <BaseContentPlaceholders>
          <BaseContentPlaceholdersText :lines="6" />
        </BaseContentPlaceholders>
      </div>
      <div v-else>
        <h3 class="text-lg font-semibold text-gray-700 mb-4 mt-2">Opciones del menú</h3>
        <div class="space-y-4">
          <div v-for="config in toggleConfigs" :key="config.key"
               class="flex items-center justify-between py-3 px-4 bg-gray-50 rounded-lg">
            <div>
              <p class="text-sm font-medium text-gray-800">{{ labelFor(config.key) }}</p>
              <p class="text-xs text-gray-500">{{ config.key }}</p>
            </div>
            <BaseSwitch v-model="config.value" :true-value="'1'" :false-value="'0'" />
          </div>
        </div>
        <h3 class="text-lg font-semibold text-gray-700 mb-4 mt-8">Otros ajustes</h3>
        <div class="space-y-4">
          <div v-for="config in otherConfigs" :key="config.key"
               class="py-3 px-4 bg-gray-50 rounded-lg">
            <label class="block text-sm font-medium text-gray-800 mb-1">{{ labelFor(config.key) }}</label>
            <p class="text-xs text-gray-500 mb-2">{{ config.key }}</p>
            <BaseInput v-model="config.value" type="text" />
          </div>
        </div>
        <div class="mt-8 flex justify-end">
          <BaseButton :loading="isSaving" variant="primary" @click="saveConfig">
            <template #left="slotProps">
              <BaseIcon name="CheckIcon" :class="slotProps.class" />
            </template>
            Guardar cambios
          </BaseButton>
        </div>
      </div>
    </BaseSettingCard>
  </div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useNotificationStore } from '@/scripts/stores/notification'
const notificationStore = useNotificationStore()
const configs = ref([])
const isLoading = ref(true)
const isSaving = ref(false)
const labels = {
  'OPCION_MENU_PRESUPUESTOS': 'Presupuestos',
  'OPCION_MENU_FACTURAS': 'Facturas',
  'OPCION_MENU_PROFORMAS': 'Facturas Proforma',
  'OPCION_MENU_ALBARANES': 'Albaranes',
  'OPCION_MENU_FRA_RECURRENTE': 'Facturas recurrentes',
  'OPCION_MENU_PAGOS': 'Pagos',
  'OPCION_MENU_GASTOS': 'Gastos',
  'NOMBRE_EMPRESA': 'Nombre de la empresa',
  'URL_LOGOTIPO': 'URL del logotipo',
}
function labelFor(key) { return labels[key] || key }
const toggleConfigs = computed(() => configs.value.filter(c => c.key.startsWith('OPCION_MENU_')))
const otherConfigs = computed(() => configs.value.filter(c => !c.key.startsWith('OPCION_MENU_')))
async function fetchConfig() {
  isLoading.value = true
  try {
    const response = await axios.get('/api/v1/app-config')
    configs.value = response.data.data
  } catch (err) {
    notificationStore.showNotification({ type: 'error', message: 'Error al cargar la configuración' })
  } finally { isLoading.value = false }
}
async function saveConfig() {
  isSaving.value = true
  try {
    await axios.put('/api/v1/app-config', {
      configs: configs.value.map(c => ({ key: c.key, value: c.key.startsWith('OPCION_MENU_') ? (c.value === true || c.value === '1' || c.value === 'true' ? '1' : '0') : String(c.value) })),
    })
    notificationStore.showNotification({ type: 'success', message: 'Configuración guardada correctamente' })
  } catch (err) {
    notificationStore.showNotification({ type: 'error', message: 'Error al guardar la configuración' })
  } finally { isSaving.value = false }
}
onMounted(() => { fetchConfig() })
</script>
