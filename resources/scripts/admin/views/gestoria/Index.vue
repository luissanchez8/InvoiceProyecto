<template>
  <BasePage>
    <BasePageHeader :title="$t('gestoria.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('gestoria.title')" to="#" active />
      </BaseBreadcrumb>
      <template #actions>
        <div v-if="estado.vinculada" class="flex items-center h-12 overflow-hidden bg-white border border-gray-200 rounded-md">
          <span class="px-4 text-xs font-semibold tracking-wider text-gray-400 uppercase border-r border-gray-100">
            {{ $t('gestoria.year') }}
          </span>
          <select
            v-model="anio"
            class="h-full pl-3 pr-8 text-base font-semibold bg-white border-0 cursor-pointer focus:outline-none"
            @change="cargarMeses"
          >
            <option v-for="y in anios" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
      </template>
    </BasePageHeader>

    <!-- ═══ 1. Desactivada ═══════════════════════════════════════ -->
    <div v-if="!cargando && !estado.activa" class="mt-6 overflow-hidden bg-white rounded-lg shadow">
      <div class="max-w-xl px-6 py-16 mx-auto text-center">
        <img src="/images/icons/gestoria.svg" class="w-12 h-12 mx-auto mb-5 opacity-40" alt="" />
        <p class="text-base font-semibold text-black">{{ $t('gestoria.off_title') }}</p>
        <p class="mt-2 text-sm leading-relaxed text-gray-500">{{ $t('gestoria.off_help') }}</p>
        <BaseButton class="mt-6" @click="$router.push('/admin/settings/gestoria')">
          {{ $t('gestoria.go_settings') }}
        </BaseButton>
      </div>
    </div>

    <!-- ═══ 2. Activada, sin vincular ════════════════════════════ -->
    <div v-else-if="!cargando && !estado.vinculada" class="mt-6 overflow-hidden bg-white rounded-lg shadow">
      <div class="max-w-xl px-6 py-16 mx-auto text-center">
        <img src="/images/icons/gestoria.svg" class="w-12 h-12 mx-auto mb-5 opacity-40" alt="" />
        <p class="text-base font-semibold text-black">
          {{ estado.pendiente ? $t('gestoria.pending_title') : $t('gestoria.nolink_title') }}
        </p>
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
    </div>

    <!-- ═══ 3. Vinculada ═════════════════════════════════════════ -->
    <template v-else-if="!cargando">
      <!-- aviso de mes sin cerrar -->
      <div
        v-if="aviso"
        class="flex gap-3 p-4 mt-6 text-sm border rounded-lg bg-amber-50 text-amber-800 border-amber-200"
      >
        <span>⚠</span>
        <div>{{ aviso.mensaje }}</div>
      </div>

      <p class="mt-6 mb-6 text-sm text-gray-500">
        {{ $t('gestoria.linked_help', { gestoria: estado.gestoria?.nombre }) }}
      </p>

      <!-- ─── Un bloque por trimestre ─── -->
      <div class="space-y-6">
        <div
          v-for="tri in trimestres"
          :key="tri.n"
          class="overflow-hidden bg-white rounded-lg shadow"
        >
          <div class="overflow-x-auto">
            <table class="w-full text-sm table-fixed min-w-[980px]">
              <colgroup>
                <col style="width: 15%" /><col style="width: 11%" /><col style="width: 9%" />
                <col style="width: 13%" /><col style="width: 12%" /><col style="width: 14%" />
                <col style="width: 12%" /><col style="width: 8%" /><col style="width: 6%" />
              </colgroup>
              <thead>
                <tr class="border-b border-gray-100 bg-gray-50/70">
                  <th :class="TH">{{ $t('gestoria.month') }}</th>
                  <th :class="TH">{{ $t('gestoria.status') }}</th>
                  <th :class="THR">{{ $t('gestoria.n_invoices') }}</th>
                  <th :class="THR">{{ $t('gestoria.neto') }}</th>
                  <th :class="THR">{{ $t('gestoria.iva') }}</th>
                  <th :class="THR">{{ $t('gestoria.bruto') }}</th>
                  <th :class="THR">{{ $t('gestoria.gastos') }}</th>
                  <th :class="THC">{{ $t('gestoria.rectif') }}</th>
                  <th class="px-6 py-3"></th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="mes in tri.meses"
                  :key="mes.month"
                  class="border-b border-gray-50 last:border-0"
                  :class="mes.estado === 'cerrado' ? 'hover:bg-gray-50/70' : ''"
                >
                  <td class="px-6 py-4">
                    <span
                      class="block truncate"
                      :class="mes.estado === 'cerrado' ? 'font-medium text-gray-700' : 'text-gray-400'"
                    >{{ NOMBRES[mes.month - 1] }}</span>
                  </td>
                  <td class="px-6 py-4">
                    <span class="px-2 py-1 text-xs font-semibold rounded whitespace-nowrap" :class="pillClass(mes.estado)">
                      {{ $t('gestoria.state_' + mes.estado) }}
                    </span>
                  </td>
                  <td class="px-6 py-4 text-right tabular-nums" :class="cellClass(mes)">
                    {{ mes.totals ? mes.totals.facturas : '—' }}
                  </td>
                  <td class="px-6 py-4 text-right tabular-nums" :class="cellClass(mes)">
                    {{ mes.totals ? money(mes.totals.neto) : '—' }}
                  </td>
                  <td class="px-6 py-4 text-right tabular-nums" :class="cellClass(mes)">
                    {{ mes.totals ? money(mes.totals.iva) : '—' }}
                  </td>
                  <td class="px-6 py-4 text-right">
                    <span
                      class="text-base font-semibold tabular-nums"
                      :class="mes.totals ? 'text-black' : 'text-gray-300'"
                    >{{ mes.totals ? money(mes.totals.bruto) : '—' }}</span>
                  </td>
                  <td class="px-6 py-4 text-right">
                    <template v-if="mes.totals">
                      <span class="text-gray-600 tabular-nums">{{ money(mes.totals.importe_gastos) }}</span>
                      <span class="block mt-0.5 text-xs text-gray-400">
                        {{ $t('gestoria.records', { n: mes.totals.gastos }) }}
                      </span>
                    </template>
                    <span v-else class="text-gray-300">—</span>
                  </td>
                  <td class="px-6 py-4 text-center">
                    <span
                      v-if="mes.totals && mes.totals.rectificativas"
                      class="px-2 py-0.5 text-xs font-semibold rounded bg-amber-100 text-amber-800 tabular-nums"
                    >{{ money(mes.totals.importe_rectificativas) }}</span>
                    <span v-else class="text-gray-300">—</span>
                  </td>
                  <td class="px-6 py-4 text-right">
                    <button
                      v-if="mes.puede_cerrarse"
                      class="text-xs font-semibold text-primary-500 hover:underline whitespace-nowrap"
                      @click="abrirCierre(mes)"
                    >{{ $t('gestoria.close_month') }}</button>
                    <span
                      v-else-if="mes.estado === 'abierto'"
                      class="text-xs text-amber-600 whitespace-nowrap"
                    >{{ $t('gestoria.pending_short') }}</span>
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="bg-primary-100 border-t-[3px] border-primary-500">
                  <td class="px-6 py-6">
                    <div class="text-lg font-semibold leading-tight text-primary-700 whitespace-nowrap">
                      {{ $t('gestoria.quarter_' + tri.n) }}
                    </div>
                    <div class="mt-1 text-xs text-primary-400 whitespace-nowrap">
                      {{ NOMBRES[tri.n * 3 - 3] }} – {{ NOMBRES[tri.n * 3 - 1] }}
                    </div>
                  </td>
                  <td class="px-6 py-6">
                    <span class="px-2 py-1 text-xs font-semibold rounded whitespace-nowrap" :class="pillClass(tri.estado)">
                      {{ $t('gestoria.state_' + tri.estado) }}
                    </span>
                  </td>
                  <td class="px-6 py-6 text-base font-bold text-right tabular-nums text-primary-700">
                    {{ tri.cerrados ? tri.facturas : '—' }}
                  </td>
                  <td class="px-6 py-6 text-base font-bold text-right tabular-nums text-primary-700">
                    {{ tri.cerrados ? money(tri.neto) : '—' }}
                  </td>
                  <td class="px-6 py-6 text-base font-bold text-right tabular-nums text-primary-700">
                    {{ tri.cerrados ? money(tri.iva) : '—' }}
                  </td>
                  <td class="px-6 py-6 text-right">
                    <span class="text-2xl font-bold leading-none tabular-nums text-primary-700">
                      {{ tri.cerrados ? money(tri.bruto) : '—' }}
                    </span>
                  </td>
                  <td class="px-6 py-6 text-base font-bold text-right tabular-nums text-primary-700">
                    {{ tri.cerrados ? money(tri.gastos) : '—' }}
                  </td>
                  <td class="px-6 py-6 text-base font-bold text-center tabular-nums text-primary-700">
                    {{ tri.cerrados && tri.rectificativas ? money(tri.rectificativas) : '—' }}
                  </td>
                  <td class="px-6 py-6"></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>

      <!-- ─── Total del año ─── -->
      <div class="mt-8 overflow-hidden text-white rounded-lg shadow bg-primary-500">
        <div class="flex items-center gap-4 px-6 py-5 border-b border-white/10">
          <div class="shrink-0 w-52">
            <h6 class="text-lg font-semibold leading-tight">{{ $t('gestoria.total_year', { y: anio }) }}</h6>
            <p class="mt-0.5 text-xs text-white/50">{{ $t('gestoria.total_year_help') }}</p>
          </div>
          <span
            class="px-2 py-1 text-xs font-semibold rounded"
            :class="anioCompleto ? 'bg-[#38d587] text-[#070322]' : 'bg-white/15 text-white'"
          >{{ anioCompleto ? $t('gestoria.year_complete') : $t('gestoria.year_ongoing') }}</span>
          <div class="flex justify-end ml-auto w-52"></div>
        </div>
        <div class="grid gap-6 px-6 py-6 sm:grid-cols-2 lg:grid-cols-5">
          <div v-for="b in bloquesAnuales" :key="b.l" class="min-h-[64px]">
            <div class="mb-1.5 text-xs tracking-wider uppercase truncate text-white/50">{{ b.l }}</div>
            <div class="text-2xl font-semibold tabular-nums whitespace-nowrap">{{ b.v }}</div>
          </div>
        </div>
      </div>

      <!-- ─── Nota sobre el IVA de los gastos ─── -->
      <div class="flex gap-3 p-4 mt-6 text-sm border rounded-lg bg-amber-50 text-amber-800 border-amber-200">
        <span>⚠</span>
        <div v-html="$t('gestoria.expenses_vat_note')"></div>
      </div>
    </template>

    <!-- ═══ Modal de confirmación ════════════════════════════════ -->
    <BaseModal :show="modal" @close="modal = false">
      <template #header>
        <div class="flex items-center gap-2">
          <BaseIcon name="ExclamationTriangleIcon" class="w-5 h-5 text-red-600" />
          <span>{{ $t('gestoria.confirm_title', { mes: preview?.nombre }) }}</span>
        </div>
      </template>

      <div class="p-6">
        <div class="grid grid-cols-2 gap-4 mb-5 sm:grid-cols-4">
          <div v-for="b in bloquesPreview" :key="b.l">
            <div class="text-xs tracking-wide text-gray-400 uppercase">{{ b.l }}</div>
            <div class="mt-1 text-lg font-semibold tabular-nums">{{ b.v }}</div>
          </div>
        </div>

        <div
          v-if="preview?.tiene_borradores"
          class="p-4 mb-5 border rounded-md bg-amber-50 border-amber-200"
        >
          <p class="text-sm font-semibold text-amber-800">{{ $t('gestoria.drafts_title') }}</p>
          <ul class="mt-2 ml-4 text-sm list-disc text-amber-700">
            <li v-for="d in preview.borradores" :key="d.tipo">{{ d.total }} {{ d.tipo }}</li>
          </ul>
          <p class="mt-2 text-sm text-amber-700">{{ $t('gestoria.drafts_help') }}</p>
        </div>

        <div class="p-4 border border-red-300 rounded-md bg-red-50">
          <p class="text-sm font-semibold text-red-800">{{ $t('gestoria.irreversible_title') }}</p>
          <ul class="mt-2 ml-4 space-y-1 text-sm text-red-700 list-disc">
            <li>{{ $t('gestoria.irreversible_1') }}</li>
            <li>{{ $t('gestoria.irreversible_2') }}</li>
            <li>{{ $t('gestoria.irreversible_3') }}</li>
          </ul>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-3">
          <BaseButton variant="white" @click="modal = false">{{ $t('general.cancel') }}</BaseButton>
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

// Clases de cabecera, calcadas del mockup
const TH = 'px-6 py-3 text-left text-[11px] font-semibold tracking-wider text-gray-400 uppercase'
const THR = 'px-6 py-3 text-right text-[11px] font-semibold tracking-wider text-gray-400 uppercase'
const THC = 'px-6 py-3 text-center text-[11px] font-semibold tracking-wider text-gray-400 uppercase'

const NOMBRES = [
  'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
  'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
]

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
  activa: false, vinculada: false, pendiente: false, gestoria: null,
})

// Los importes llegan en céntimos, como en el resto de la app.
function money(v) {
  if (v === null || v === undefined) return '—'
  return (v / 100).toLocaleString('es-ES', {
    minimumFractionDigits: 2, maximumFractionDigits: 2,
  })
}

function pillClass(e) {
  return {
    cerrado: 'bg-emerald-50 text-emerald-700',
    completo: 'bg-emerald-50 text-emerald-700',
    abierto: 'bg-amber-50 text-amber-700',
    incompleto: 'bg-amber-50 text-amber-700',
    en_curso: 'bg-blue-50 text-blue-700',
    futuro: 'bg-gray-100 text-gray-400',
  }[e] || 'bg-gray-100 text-gray-500'
}

function cellClass(mes) {
  return mes.totals ? 'text-gray-600' : 'text-gray-300'
}

// Agrupa los 12 meses en 4 trimestres con sus subtotales.
const trimestres = computed(() => {
  const out = []
  for (let q = 1; q <= 4; q++) {
    const ms = meses.value.filter((m) => Math.ceil(m.month / 3) === q)
    const cer = ms.filter((m) => m.estado === 'cerrado' && m.totals)

    // Estado del trimestre a partir de sus tres meses
    let est = 'en_curso'
    if (ms.length && ms.every((m) => m.estado === 'futuro')) est = 'futuro'
    else if (ms.length && ms.every((m) => m.estado === 'cerrado')) est = 'completo'
    else if (ms.some((m) => m.estado === 'abierto')) est = 'incompleto'

    const suma = (campo) => cer.reduce((a, m) => a + (m.totals[campo] || 0), 0)

    out.push({
      n: q,
      meses: ms,
      estado: est,
      cerrados: cer.length,
      facturas: suma('facturas'),
      neto: suma('neto'),
      iva: suma('iva'),
      bruto: suma('bruto'),
      gastos: suma('importe_gastos'),
      rectificativas: suma('importe_rectificativas'),
    })
  }
  return out
})

const anioCompleto = computed(() =>
  trimestres.value.length === 4 && trimestres.value.every((t) => t.estado === 'completo')
)

const bloquesAnuales = computed(() => {
  const s = (campo) => trimestres.value.reduce((a, q) => a + (q[campo] || 0), 0)
  const nGastos = meses.value
    .filter((m) => m.totals)
    .reduce((a, m) => a + (m.totals.gastos || 0), 0)

  return [
    { l: t('gestoria.facturas'), v: s('facturas') },
    { l: t('gestoria.neto'), v: money(s('neto')) + ' €' },
    { l: t('gestoria.iva'), v: money(s('iva')) + ' €' },
    { l: t('gestoria.bruto'), v: money(s('bruto')) + ' €' },
    { l: t('gestoria.gastos') + ' (' + nGastos + ')', v: money(s('gastos')) + ' €' },
  ]
})

const bloquesPreview = computed(() => {
  const x = preview.value?.totales
  if (!x) return []
  return [
    { l: t('gestoria.facturas'), v: x.facturas },
    { l: t('gestoria.neto'), v: money(x.neto) + ' €' },
    { l: t('gestoria.iva'), v: money(x.iva) + ' €' },
    { l: t('gestoria.bruto'), v: money(x.bruto) + ' €' },
  ]
})

async function cargarEstado() {
  try {
    const { data } = await axios.get('/api/v1/gestoria')
    Object.assign(estado, data)
  } catch (e) { /* valores por defecto */ }
}

async function cargarMeses() {
  if (!estado.vinculada) return
  try {
    const { data } = await axios.get('/api/v1/closed-months', { params: { year: anio.value } })
    meses.value = data.meses
    aviso.value = data.aviso_pendiente
  } catch (e) { /* silencioso */ }
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
