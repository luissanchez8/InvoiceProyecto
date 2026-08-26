<template>
  <BasePage>
    <BasePageHeader :title="$t('gestoria.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('gestoria.title')" to="#" active />
      </BaseBreadcrumb>
      <template #actions>
        <BaseMultiselect
          v-if="estado.vinculada"
          v-model="anio"
          :options="anios"
          :can-deselect="false"
          :can-clear="false"
          class="w-32"
          @update:modelValue="cargarMeses"
        />
      </template>
    </BasePageHeader>

    <!-- ═══ 1. Desactivada ═══════════════════════════════════════ -->
    <BaseCard v-if="!cargando && !estado.activa" class="mt-6">
      <div class="max-w-xl py-10 mx-auto text-center">
        <img src="/images/icons/gestoria.svg" class="w-12 h-12 mx-auto mb-5 opacity-40" alt="" />
        <h3 class="text-lg font-semibold text-black">
          {{ $t('gestoria.off_title') }}
        </h3>
        <p class="mt-2 text-sm leading-relaxed text-gray-500">
          {{ $t('gestoria.off_help') }}
        </p>
        <BaseButton class="mt-6" @click="$router.push('/admin/settings/gestoria')">
          {{ $t('gestoria.go_settings') }}
        </BaseButton>
      </div>
    </BaseCard>

    <!-- ═══ 2. Activada, sin vincular ════════════════════════════ -->
    <BaseCard v-else-if="!cargando && !estado.vinculada" class="mt-6">
      <div class="max-w-xl py-10 mx-auto text-center">
        <img src="/images/icons/gestoria.svg" class="w-12 h-12 mx-auto mb-5 opacity-40" alt="" />
        <h3 class="text-lg font-semibold text-black">
          {{ estado.pendiente ? $t('gestoria.pending_title') : $t('gestoria.nolink_title') }}
        </h3>
        <p class="mt-2 text-sm leading-relaxed text-gray-500">
          {{ estado.pendiente ? $t('gestoria.pending_help') : $t('gestoria.nolink_help') }}
        </p>
        <BaseButton
          v-if="!estado.pendiente"
          class="mt-6"
          @click="$router.push('/admin/settings/gestoria')"
        >
          {{ $t('gestoria.enter_code') }}
        </BaseButton>
      </div>
    </BaseCard>

    <!-- ═══ 3. Vinculada: los meses ══════════════════════════════ -->
    <template v-else-if="!cargando">
      <!-- aviso de mes sin cerrar -->
      <div
        v-if="aviso"
        class="flex items-start gap-3 p-4 mt-6 border rounded-md bg-amber-50 border-amber-200"
      >
        <BaseIcon name="ExclamationTriangleIcon" class="w-5 h-5 text-amber-600 shrink-0" />
        <div class="flex-1">
          <p class="text-sm font-medium text-amber-800">{{ aviso.mensaje }}</p>
        </div>
      </div>

      <p class="mt-6 mb-4 text-sm text-gray-500">
        {{ $t('gestoria.linked_help', { gestoria: estado.gestoria?.nombre }) }}
      </p>

      <!-- un bloque por trimestre -->
      <div class="space-y-6">
        <BaseCard v-for="tri in trimestres" :key="tri.n" class="!p-0 overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-sm table-fixed min-w-[760px]">
              <colgroup>
                <col style="width: 20%" />
                <col style="width: 14%" />
                <col style="width: 12%" />
                <col style="width: 16%" />
                <col style="width: 14%" />
                <col style="width: 24%" />
              </colgroup>
              <thead>
                <tr class="border-b border-gray-100 bg-gray-50/70">
                  <th class="px-6 py-3 text-left text-[11px] font-semibold tracking-wider text-gray-400 uppercase">
                    {{ $t('gestoria.month') }}
                  </th>
                  <th class="px-6 py-3 text-left text-[11px] font-semibold tracking-wider text-gray-400 uppercase">
                    {{ $t('gestoria.status') }}
                  </th>
                  <th class="px-6 py-3 text-right text-[11px] font-semibold tracking-wider text-gray-400 uppercase">
                    {{ $t('gestoria.invoices') }}
                  </th>
                  <th class="px-6 py-3 text-right text-[11px] font-semibold tracking-wider text-gray-400 uppercase">
                    {{ $t('gestoria.net') }}
                  </th>
                  <th class="px-6 py-3 text-right text-[11px] font-semibold tracking-wider text-gray-400 uppercase">
                    {{ $t('gestoria.vat') }}
                  </th>
                  <th class="px-6 py-3"></th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="mes in tri.meses"
                  :key="mes.month"
                  class="border-b border-gray-50 last:border-0"
                >
                  <td class="px-6 py-4">
                    <span
                      class="block truncate"
                      :class="mes.estado === 'cerrado' ? 'font-semibold text-black' : 'text-gray-500'"
                    >
                      {{ nombreMes(mes.month) }}
                    </span>
                  </td>
                  <td class="px-6 py-4">
                    <span
                      class="px-2 py-1 text-xs font-semibold rounded whitespace-nowrap"
                      :class="pillClass(mes.estado)"
                    >
                      {{ $t('gestoria.state_' + mes.estado) }}
                    </span>
                  </td>
                  <td class="px-6 py-4 text-right tabular-nums text-gray-600">
                    {{ mes.totals ? mes.totals.facturas : '—' }}
                  </td>
                  <td class="px-6 py-4 text-right tabular-nums text-gray-600">
                    {{ mes.totals ? money(mes.totals.neto) : '—' }}
                  </td>
                  <td class="px-6 py-4 text-right tabular-nums text-gray-600">
                    {{ mes.totals ? money(mes.totals.iva) : '—' }}
                  </td>
                  <td class="px-6 py-4 text-right">
                    <BaseButton
                      v-if="mes.puede_cerrarse"
                      size="sm"
                      variant="primary-outline"
                      @click="abrirCierre(mes)"
                    >
                      {{ $t('gestoria.close_month') }}
                    </BaseButton>
                  </td>
                </tr>
              </tbody>
              <tfoot v-if="tri.cerrados">
                <tr class="bg-primary-100 border-t-[3px] border-primary-500">
                  <td class="px-6 py-5" colspan="2">
                    <div class="text-base font-semibold text-primary-700 whitespace-nowrap">
                      {{ $t('gestoria.quarter', { n: tri.n }) }}
                    </div>
                  </td>
                  <td class="px-6 py-5 text-right tabular-nums text-base font-bold text-primary-700">
                    {{ tri.facturas }}
                  </td>
                  <td class="px-6 py-5 text-right tabular-nums text-base font-bold text-primary-700">
                    {{ money(tri.neto) }}
                  </td>
                  <td class="px-6 py-5 text-right tabular-nums text-base font-bold text-primary-700">
                    {{ money(tri.iva) }}
                  </td>
                  <td class="px-6 py-5 text-right">
                    <span class="text-lg font-bold text-primary-700 tabular-nums whitespace-nowrap">
                      {{ money(tri.bruto) }}
                    </span>
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </BaseCard>
      </div>
    </template>

    <!-- ═══ Modal de confirmación de cierre ══════════════════════ -->
    <BaseModal :show="modal" @close="modal = false">
      <template #header>
        <div class="flex items-center gap-2">
          <BaseIcon name="ExclamationTriangleIcon" class="w-5 h-5 text-red-600" />
          <span>{{ $t('gestoria.confirm_title', { mes: preview?.nombre }) }}</span>
        </div>
      </template>

      <div class="p-6">
        <!-- lo que se entrega -->
        <div class="grid grid-cols-2 gap-4 mb-5 sm:grid-cols-4">
          <div v-for="k in ['facturas', 'neto', 'iva', 'bruto']" :key="k">
            <div class="text-xs tracking-wide text-gray-400 uppercase">
              {{ $t('gestoria.' + k) }}
            </div>
            <div class="mt-1 text-lg font-semibold tabular-nums">
              {{ k === 'facturas' ? preview?.totales.facturas : money(preview?.totales[k]) }}
            </div>
          </div>
        </div>

        <!-- borradores que se quedan fuera -->
        <div
          v-if="preview?.tiene_borradores"
          class="p-4 mb-5 border rounded-md bg-amber-50 border-amber-200"
        >
          <p class="text-sm font-semibold text-amber-800">
            {{ $t('gestoria.drafts_title') }}
          </p>
          <ul class="mt-2 ml-4 text-sm list-disc text-amber-700">
            <li v-for="b in preview.borradores" :key="b.tipo">
              {{ b.total }} {{ b.tipo }}
            </li>
          </ul>
          <p class="mt-2 text-sm text-amber-700">
            {{ $t('gestoria.drafts_help') }}
          </p>
        </div>

        <!-- el aviso fuerte -->
        <div class="p-4 border rounded-md border-red-300 bg-red-50">
          <p class="text-sm font-semibold text-red-800">
            {{ $t('gestoria.irreversible_title') }}
          </p>
          <ul class="mt-2 ml-4 text-sm text-red-700 list-disc space-y-1">
            <li>{{ $t('gestoria.irreversible_1') }}</li>
            <li>{{ $t('gestoria.irreversible_2') }}</li>
            <li>{{ $t('gestoria.irreversible_3') }}</li>
          </ul>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-3">
          <BaseButton variant="white" @click="modal = false">
            {{ $t('general.cancel') }}
          </BaseButton>
          <BaseButton variant="danger" :loading="cerrando" @click="cerrarMes">
            {{ $t('gestoria.confirm_close') }}
          </BaseButton>
        </div>
      </template>
    </BaseModal>
  </BasePage>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import axios from 'axios'
import { useNotificationStore } from '@/scripts/stores/notification'

const { t } = useI18n()
const notificationStore = useNotificationStore()

const cargando = ref(true)
const cerrando = ref(false)
const modal = ref(false)
const preview = ref(null)
const mesElegido = ref(null)
const meses = ref([])
const aviso = ref(null)
const anio = ref(new Date().getFullYear())

const anios = computed(() => {
  const y = new Date().getFullYear()
  return [y, y - 1, y - 2]
})

const estado = reactive({
  activa: false,
  vinculada: false,
  pendiente: false,
  gestoria: null,
})

const NOMBRES = [
  'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
  'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
]

function nombreMes(m) {
  const n = NOMBRES[m - 1]
  return n.charAt(0).toUpperCase() + n.slice(1)
}

// Los importes llegan en céntimos, como en el resto de la app.
function money(v) {
  if (v === null || v === undefined) return '—'
  return (v / 100).toLocaleString('es-ES', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }) + ' €'
}

function pillClass(e) {
  return {
    cerrado: 'bg-emerald-50 text-emerald-700',
    abierto: 'bg-amber-50 text-amber-700',
    en_curso: 'bg-blue-50 text-blue-700',
    futuro: 'bg-gray-100 text-gray-400',
  }[e] || 'bg-gray-100 text-gray-500'
}

// Agrupa los 12 meses en 4 trimestres con sus subtotales.
const trimestres = computed(() => {
  const out = []
  for (let q = 1; q <= 4; q++) {
    const ms = meses.value.filter((m) => Math.ceil(m.month / 3) === q)
    const cerrados = ms.filter((m) => m.estado === 'cerrado' && m.totals)
    out.push({
      n: q,
      meses: ms,
      cerrados: cerrados.length,
      facturas: cerrados.reduce((a, m) => a + (m.totals.facturas || 0), 0),
      neto: cerrados.reduce((a, m) => a + (m.totals.neto || 0), 0),
      iva: cerrados.reduce((a, m) => a + (m.totals.iva || 0), 0),
      bruto: cerrados.reduce((a, m) => a + (m.totals.bruto || 0), 0),
    })
  }
  return out
})

async function cargarEstado() {
  try {
    const { data } = await axios.get('/api/v1/gestoria')
    Object.assign(estado, data)
  } catch (e) {
    /* se queda en los valores por defecto */
  }
}

async function cargarMeses() {
  if (!estado.vinculada) return
  try {
    const { data } = await axios.get('/api/v1/closed-months', { params: { year: anio.value } })
    meses.value = data.meses
    aviso.value = data.aviso_pendiente
  } catch (e) {
    /* silencioso */
  }
}

async function abrirCierre(mes) {
  mesElegido.value = mes
  try {
    const { data } = await axios.get('/api/v1/closed-months/preview', {
      params: { year: mes.year, month: mes.month },
    })
    preview.value = data
    modal.value = true
  } catch (e) {
    notificationStore.showNotification({
      type: 'error',
      message: e.response?.data?.message || t('general.something_went_wrong'),
    })
  }
}

async function cerrarMes() {
  cerrando.value = true
  try {
    const { data } = await axios.post('/api/v1/closed-months', {
      year: mesElegido.value.year,
      month: mesElegido.value.month,
    })
    notificationStore.showNotification({ type: 'success', message: data.message })
    modal.value = false
    await cargarMeses()
  } catch (e) {
    notificationStore.showNotification({
      type: 'error',
      message: e.response?.data?.message || t('general.something_went_wrong'),
    })
  } finally {
    cerrando.value = false
  }
}

onMounted(async () => {
  await cargarEstado()
  await cargarMeses()
  cargando.value = false
})
</script>
