<template>
  <InvoicesTabInvoiceNumber />

  <BaseDivider class="my-8" />

  <InvoicesTabDueDate />

  <BaseDivider class="my-8" />

  <InvoicesTabRetrospective />

  <BaseDivider class="my-8" />

  <!--
    VeriFactu
    ---------
    Comportamiento:
    - Usuario normal (no asistencia):
      · Si Asistencia ha activado OPCION_VERIFACTU → ve el toggle tradicional.
      · Si no → ve un botón "Solicitar activación" que envía email a soporte.
    - Usuario asistencia: nunca ve esta tarjeta; gestiona VeriFactu desde
      Ajustes → Configuración App.
  -->
  <div>
    <h3 class="text-lg font-medium leading-6 text-gray-900">
      {{ $t('verifactu.title') }}
    </h3>

    <!-- Usuario normal con VeriFactu habilitado por Asistencia: toggle normal -->
    <ul v-if="!isAsistencia && isVerifactuAllowed" class="mt-2 divide-y divide-gray-200">
      <BaseSwitchSection
        v-model="verifactuEnabledField"
        :title="$t('verifactu.enable')"
        :description="$t('verifactu.enable_description')"
      />
    </ul>

    <!-- Usuario normal sin VeriFactu habilitado: botón de solicitud -->
    <div v-else-if="!isAsistencia" class="mt-4 p-4 bg-gray-50 border border-gray-200 rounded-lg">
      <p class="text-sm text-gray-700 mb-3">
        {{ $t('verifactu.request_activation_description') }}
      </p>
      <BaseButton
        :loading="isRequestingActivation"
        variant="primary"
        @click="openRequestActivationModal"
      >
        <template #left="slotProps">
          <BaseIcon name="EnvelopeIcon" :class="slotProps.class" />
        </template>
        {{ $t('verifactu.request_activation_button') }}
      </BaseButton>
    </div>

    <!-- Usuario asistencia: nota informativa (toggle real está en Configuración App) -->
    <p v-else class="mt-2 text-sm text-gray-500">
      {{ $t('verifactu.asistencia_hint') }}
    </p>
  </div>

  <BaseDivider class="my-8" />

  <!-- Formatos por defecto: SOLO asistencia -->
  <InvoicesTabDefaultFormats v-if="isAsistencia" />

  <!-- Adjuntar factura: SOLO asistencia (sin líneas sueltas) -->
  <template v-if="isAsistencia">
    <BaseDivider class="mt-6 mb-2" />
    <ul class="divide-y divide-gray-200">
      <BaseSwitchSection
        v-model="sendAsAttachmentField"
        :title="$t('settings.customization.invoices.invoice_email_attachment')"
        :description="$t('settings.customization.invoices.invoice_email_attachment_setting_description')"
      />
    </ul>
  </template>
</template>

<script setup>
import { computed, reactive, inject, ref, watch, onMounted } from 'vue'
import axios from 'axios'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useUserStore } from '@/scripts/admin/stores/user'
import { useGlobalStore } from '@/scripts/admin/stores/global'
import { useDialogStore } from '@/scripts/stores/dialog'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useI18n } from 'vue-i18n'
import InvoicesTabInvoiceNumber from './InvoicesTabInvoiceNumber.vue'
import InvoicesTabRetrospective from './InvoicesTabRetrospective.vue'
import InvoicesTabDueDate from './InvoicesTabDueDate.vue'
import InvoicesTabDefaultFormats from './InvoicesTabDefaultFormats.vue'

const companyStore = useCompanyStore()
const userStore = useUserStore()
const globalStore = useGlobalStore()
const dialogStore = useDialogStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()
const utils = inject('utils')

// Refrescar bootstrap al abrir esta tab para que el usuario vea en tiempo
// real si Asistencia ha cambiado OPCION_VERIFACTU.
onMounted(() => {
  globalStore.bootstrap().catch(() => {})
})

// rol desde userStore
const isAsistencia = computed(() =>
  ((userStore.currentUser?.role || '') + '').trim().toLowerCase() === 'asistencia'
)

// Asistencia permite al usuario ver/cambiar el toggle de VeriFactu
const isVerifactuAllowed = computed(() => globalStore.opcionVerifactu === true)

const estimateSettings = reactive({
  estimate_email_attachment: null,
})

utils.mergeSettings(estimateSettings, {
  ...companyStore.selectedCompanySettings,
})

// ✅ Por defecto ON si viene vacío
if (estimateSettings.estimate_email_attachment == null) {
  estimateSettings.estimate_email_attachment = 'YES'
}

const sendAsAttachmentField = computed({
  // ON salvo que sea explícitamente 'NO'
  get: () => (estimateSettings.estimate_email_attachment ?? 'YES') === 'YES',
  set: async (newValue) => {
    const value = newValue ? 'YES' : 'NO'
    estimateSettings.estimate_email_attachment = value
    await companyStore.updateCompanySettings({
      data: { settings: { estimate_email_attachment: value } },
      message: 'general.setting_updated',
    })
  },
})

// --- VeriFactu toggle (solo visible si Asistencia lo ha permitido) ---
const verifactuEnabledField = computed({
  get: () => companyStore.selectedCompanySettings.verifactu_enabled === 'YES',
  set: async (newValue) => {
    if (newValue) {
      const confirmed = await dialogStore.openDialog({
        title: t('verifactu.warning_title'),
        message: t('verifactu.warning_message'),
        yesLabel: t('general.ok'),
        noLabel: t('general.cancel'),
        variant: 'danger',
        hideNoButton: false,
        size: 'lg',
      })
      if (!confirmed) return
    }
    const value = newValue ? 'YES' : 'NO'
    await companyStore.updateCompanySettings({
      data: { settings: { verifactu_enabled: value } },
      message: 'general.setting_updated',
    })
  },
})

// --- Solicitud de activación de VeriFactu (usuario normal) ---
const isRequestingActivation = ref(false)

async function openRequestActivationModal() {
  const confirmed = await dialogStore.openDialog({
    title: t('verifactu.request_activation_modal_title'),
    message: t('verifactu.request_activation_modal_message'),
    yesLabel: t('verifactu.request_activation_modal_confirm'),
    noLabel: t('general.cancel'),
    variant: 'primary',
    hideNoButton: false,
    size: 'lg',
  })
  if (!confirmed) return

  isRequestingActivation.value = true
  try {
    const response = await axios.post('/api/v1/verifactu/request-activation')
    if (response.data?.success) {
      notificationStore.showNotification({
        type: 'success',
        message: t('verifactu.request_activation_sent'),
      })
    } else {
      notificationStore.showNotification({
        type: 'error',
        message: response.data?.error || t('verifactu.request_activation_error'),
      })
    }
  } catch (err) {
    notificationStore.showNotification({
      type: 'error',
      message: err?.response?.data?.error || t('verifactu.request_activation_error'),
    })
  } finally {
    isRequestingActivation.value = false
  }
}
</script>
