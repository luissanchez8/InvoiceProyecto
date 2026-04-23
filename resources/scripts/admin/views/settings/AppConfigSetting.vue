<template>
  <div class="relative">
    <BaseSettingCard
      :title="'Configuración de la instancia'"
      :description="'Activa o desactiva las opciones del menú y configura los ajustes de esta instancia.'"
    >
      <div v-if="isLoading" class="flex justify-center py-10">
        <BaseContentPlaceholders>
          <BaseContentPlaceholdersText :lines="6" />
        </BaseContentPlaceholders>
      </div>
      <div v-else>
        <!-- Sección Plan -->
        <div class="mb-6 py-4 px-4 bg-gray-50 border border-gray-200 rounded-lg">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-800">Plan contratado</p>
              <p v-if="stripePlan" class="text-lg font-semibold text-primary-700 mt-1">
                {{ stripePlan.label }}
                <span v-if="stripePlan.interval" class="text-sm font-normal text-gray-500 ml-1">
                  ({{ stripePlan.interval === 'month' ? 'mensual' : 'anual' }})
                </span>
                <span v-if="stripePlan.status" class="ml-2 text-xs font-medium px-2 py-0.5 rounded-full"
                      :class="stripePlan.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                  {{ stripePlan.status }}
                </span>
              </p>
              <p v-else-if="planId" class="text-lg font-semibold text-primary-700 mt-1">{{ planLabel }}</p>
              <p v-else class="text-sm text-gray-500 mt-1">No disponible</p>
              <p v-if="stripePlan && stripePlan.email" class="text-xs text-gray-500 mt-1">Admin: {{ stripePlan.email }}</p>
            </div>
            <BaseButton
              :loading="isFetchingPlan"
              variant="primary-outline"
              size="sm"
              @click="fetchPlanFromStripe"
            >
              Consultar plan
            </BaseButton>
          </div>
        </div>

        <h3 class="text-lg font-semibold text-gray-700 mb-4 mt-2">Opciones del menú</h3>
        <div class="space-y-4">
          <div v-for="config in toggleConfigs" :key="config.key"
               class="flex items-center justify-between py-3 px-4 bg-gray-50 rounded-lg">
            <div>
              <p class="text-sm font-medium text-gray-800">{{ labelFor(config.key) }}</p>
              <p class="text-xs text-gray-500">{{ config.key }}</p>
            </div>
            <BaseSwitch v-model="config.enabled" />
          </div>
        </div>

        <h3 class="text-lg font-semibold text-gray-700 mb-4 mt-8">Otros ajustes</h3>
        <div class="space-y-4">
          <div v-for="config in editableConfigs" :key="config.key"
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
const isFetchingPlan = ref(false)
const stripePlan = ref(null)

const labels = {
  'OPCION_MENU_PRESUPUESTOS': 'Presupuestos',
  'OPCION_MENU_FACTURAS': 'Facturas',
  'OPCION_MENU_PROFORMAS': 'Facturas Proforma',
  'OPCION_MENU_ALBARANES': 'Albaranes',
  'OPCION_MENU_FRA_RECURRENTE': 'Facturas recurrentes',
  'OPCION_MENU_PAGOS': 'Pagos',
  'OPCION_MENU_GASTOS': 'Gastos',
  'OPCION_VERIFACTU': 'Activar VeriFactu',
  'NOMBRE_EMPRESA': 'Nombre de la empresa',
  'URL_LOGOTIPO': 'URL del logotipo',
  'PLAN_ID': 'Plan contratado',
}

const planLabels = {
  'essential': 'Essential',
  'advanced': 'Advanced',
  'pro': 'Pro',
}

const readonlyKeys = ['PLAN_ID']

function labelFor(key) { return labels[key] || key }

// PLAN_ID local de app_config (fallback)
const planId = computed(() => {
  const cfg = configs.value.find(c => c.key === 'PLAN_ID')
  return cfg ? cfg.value : null
})

const planLabel = computed(() => {
  if (!planId.value) return ''
  return planLabels[planId.value] || planId.value
})

const toggleConfigs = computed(() =>
  configs.value.filter(c =>
    c.key.startsWith('OPCION_MENU_') || c.key === 'OPCION_VERIFACTU'
  )
)

const editableConfigs = computed(() =>
  configs.value.filter(c =>
    !c.key.startsWith('OPCION_MENU_') &&
    c.key !== 'OPCION_VERIFACTU' &&
    !readonlyKeys.includes(c.key)
  )
)

async function fetchConfig() {
  isLoading.value = true
  try {
    const response = await axios.get('/api/v1/app-config')
    configs.value = response.data.data.map(item => ({
      ...item,
      enabled: item.key.startsWith('OPCION_MENU_') ? item.value === '1' : undefined,
    }))
  } catch (err) {
    notificationStore.showNotification({
      type: 'error',
      message: 'Error al cargar la configuración',
    })
  } finally {
    isLoading.value = false
  }
}

async function fetchPlanFromStripe() {
  isFetchingPlan.value = true
  try {
    const response = await axios.get('/api/v1/app-config/plan-from-stripe')
    if (response.data.ok) {
      stripePlan.value = {
        label: planLabels[response.data.plan_id] || response.data.plan_id || 'Desconocido',
        interval: response.data.plan_interval,
        status: response.data.plan_status,
        email: response.data.admin_email,
      }
    } else {
      notificationStore.showNotification({
        type: 'error',
        message: response.data.error || 'No se encontró el plan',
      })
    }
  } catch (err) {
    notificationStore.showNotification({
      type: 'error',
      message: 'Error al consultar el plan en Stripe',
    })
  } finally {
    isFetchingPlan.value = false
  }
}

async function saveConfig() {
  isSaving.value = true
  try {
    const payload = configs.value
      .filter(c => !readonlyKeys.includes(c.key))
      .map(c => ({
        key: c.key,
        value: c.key.startsWith('OPCION_MENU_')
          ? (c.enabled ? '1' : '0')
          : String(c.value ?? ''),
      }))

    await axios.put('/api/v1/app-config', { configs: payload })
    notificationStore.showNotification({
      type: 'success',
      message: 'Configuración guardada correctamente',
    })
  } catch (err) {
    notificationStore.showNotification({
      type: 'error',
      message: 'Error al guardar la configuración',
    })
  } finally {
    isSaving.value = false
  }
}

onMounted(() => { fetchConfig() })
</script>
