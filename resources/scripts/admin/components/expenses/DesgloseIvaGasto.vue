<!--
  Onfactu v.1.15.0 — Desglose del IVA de un gasto.

  Una fila por tipo de IVA (un ticket puede llevar 10 % y 21 %), con la base
  y el total de la fila: se puede escribir cualquiera de los dos y el otro se
  calcula. Debajo, la retención y el resumen: base, IVA, retención y total a
  pagar.

  Trabaja sobre store.currentExpense: lineas_iva ([{ tipo, base, deducible }],
  importes en céntimos), retencion_porcentaje y amount, que es el total. El
  servidor lo vuelve a calcular todo al guardar (App\Support\IvaGastos); aquí
  solo se calcula para enseñarlo.

  Las compras intracomunitarias (autoliquidación) llegan sin IVA: su cuota se
  enseña, pero no suma en el total a pagar.
-->
<template>
  <div class="col-span-2">
    <BaseLabel class="mb-2">{{ $t('gastos_iva.titulo') }}</BaseLabel>

    <div class="overflow-x-auto border border-gray-200 rounded-md">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500">
          <tr>
            <th class="px-3 py-2 font-normal text-left">{{ $t('gastos_iva.tipo') }}</th>
            <th class="px-3 py-2 font-normal text-right">{{ $t('gastos_iva.base') }}</th>
            <th class="px-3 py-2 font-normal text-right">{{ $t('gastos_iva.iva') }}</th>
            <th class="px-3 py-2 font-normal text-right">{{ $t('gastos_iva.total_linea') }}</th>
            <th class="px-3 py-2 font-normal text-center">{{ $t('gastos_iva.no_deducible') }}</th>
            <th class="w-8"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(fila, i) in filas" :key="i" class="border-t border-gray-100">
            <td class="px-3 py-2">
              <select
                v-model="fila.tipo"
                class="w-full min-w-[12rem] text-sm border-gray-200 rounded-md focus:border-primary-400 focus:ring-primary-400"
                @change="cambiarTipo(i)"
              >
                <option v-for="t in tipos" :key="t.clave" :value="t.clave">{{ t.nombre }}</option>
              </select>
            </td>
            <td class="px-3 py-2">
              <BaseInput
                v-model="fila.baseTxt"
                type="number"
                step="0.01"
                min="0"
                class="text-right min-w-[7rem]"
                @update:modelValue="cambiarBase(i)"
              />
            </td>
            <td class="px-3 py-2 text-right text-gray-600 whitespace-nowrap">
              {{ euros(cuota(fila)) }}
              <span v-if="esAutoliquidacion(fila.tipo)" class="block text-xs text-gray-400">
                {{ $t('gastos_iva.no_se_paga') }}
              </span>
            </td>
            <td class="px-3 py-2">
              <BaseInput
                v-model="fila.totalTxt"
                type="number"
                step="0.01"
                min="0"
                class="text-right min-w-[7rem]"
                @update:modelValue="cambiarTotal(i)"
              />
            </td>
            <td class="px-3 py-2 text-center">
              <input
                v-model="fila.noDeducible"
                type="checkbox"
                class="w-4 h-4 border-gray-300 rounded text-primary-500 focus:ring-primary-400"
                :title="$t('gastos_iva.no_deducible_ayuda')"
                @change="volcar"
              />
            </td>
            <td class="px-2 py-2 text-center">
              <button
                v-if="filas.length > 1"
                type="button"
                class="text-gray-400 hover:text-red-500"
                :title="$t('gastos_iva.quitar')"
                @click="quitar(i)"
              >
                <BaseIcon name="TrashIcon" class="w-4 h-4" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <button
      v-if="filas.length < 10"
      type="button"
      class="mt-2 text-sm font-medium text-primary-500 hover:text-primary-600"
      @click="anadir"
    >
      + {{ $t('gastos_iva.anadir_tipo') }}
    </button>

    <div class="grid gap-4 mt-4 md:grid-cols-2">
      <BaseInputGroup :label="$t('gastos_iva.retencion')">
        <select
          v-model.number="gasto.retencion_porcentaje"
          class="w-full text-sm border-gray-200 rounded-md focus:border-primary-400 focus:ring-primary-400"
          @change="volcar"
        >
          <option v-for="r in retenciones" :key="r" :value="r">
            {{ r === 0 ? $t('gastos_iva.sin_retencion') : r + ' %' }}
          </option>
        </select>
      </BaseInputGroup>

      <dl class="text-sm divide-y divide-gray-100">
        <div class="flex justify-between py-1">
          <dt class="text-gray-500">{{ $t('gastos_iva.base') }}</dt><dd>{{ euros(resumen.base) }}</dd>
        </div>
        <div class="flex justify-between py-1">
          <dt class="text-gray-500">{{ $t('gastos_iva.iva') }}</dt><dd>{{ euros(resumen.iva) }}</dd>
        </div>
        <div v-if="resumen.retencion" class="flex justify-between py-1">
          <dt class="text-gray-500">{{ $t('gastos_iva.retencion') }}</dt><dd>- {{ euros(resumen.retencion) }}</dd>
        </div>
        <div class="flex justify-between py-1 font-semibold">
          <dt>{{ $t('gastos_iva.total_pagar') }}</dt><dd>{{ euros(resumen.total) }}</dd>
        </div>
      </dl>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  store: { type: Object, required: true },
})

const gasto = computed(() => props.store.currentExpense)
const tipos = ref([])
const retenciones = ref([0, 7, 15, 19])
const filas = ref([])

const porClave = computed(() => Object.fromEntries(tipos.value.map((t) => [t.clave, t])))

function porcentaje(tipo) {
  return porClave.value[tipo]?.porcentaje ?? 0
}
function esAutoliquidacion(tipo) {
  return !!porClave.value[tipo]?.autoliquidacion
}
function centimos(txt) {
  const n = parseFloat(String(txt).replace(',', '.'))
  return isNaN(n) || n < 0 ? 0 : Math.round(n * 100)
}
function texto(c) {
  return (c / 100).toFixed(2)
}
function euros(c) {
  return (c / 100).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €'
}
function cuota(fila) {
  return Math.round((centimos(fila.baseTxt) * porcentaje(fila.tipo)) / 100)
}
function totalFila(fila) {
  return centimos(fila.baseTxt) + (esAutoliquidacion(fila.tipo) ? 0 : cuota(fila))
}

const resumen = computed(() => {
  let base = 0
  let iva = 0
  filas.value.forEach((f) => {
    base += centimos(f.baseTxt)
    if (!esAutoliquidacion(f.tipo)) iva += cuota(f)
  })
  const retencion = Math.round((base * (gasto.value.retencion_porcentaje || 0)) / 100)
  return { base, iva, retencion, total: base + iva - retencion }
})

function cambiarBase(i) {
  filas.value[i].totalTxt = texto(totalFila(filas.value[i]))
  volcar()
}
function cambiarTotal(i) {
  const f = filas.value[i]
  const total = centimos(f.totalTxt)
  const base = esAutoliquidacion(f.tipo) ? total : Math.round((total * 100) / (100 + porcentaje(f.tipo)))
  f.baseTxt = texto(base)
  volcar()
}
function cambiarTipo(i) {
  cambiarBase(i)
}
function anadir() {
  filas.value.push({ tipo: 'iva21', baseTxt: '0.00', totalTxt: '0.00', noDeducible: false })
  volcar()
}
function quitar(i) {
  filas.value.splice(i, 1)
  volcar()
}

// Del formulario al gasto: lo que se envía al guardar
function volcar() {
  gasto.value.lineas_iva = filas.value.map((f) => ({
    tipo: f.tipo,
    base: centimos(f.baseTxt),
    deducible: !f.noDeducible,
  }))
  gasto.value.amount = resumen.value.total
}

// Del gasto al formulario: al abrir uno guardado, o uno nuevo
function cargar() {
  const lineas = gasto.value.lineas_iva?.length ? gasto.value.lineas_iva : [{ tipo: 'iva21', base: 0, deducible: true }]
  filas.value = lineas.map((l) => ({
    tipo: l.tipo,
    baseTxt: texto(l.base || 0),
    totalTxt: '0.00',
    noDeducible: l.deducible === false,
  }))
  if (gasto.value.retencion_porcentaje == null) gasto.value.retencion_porcentaje = 0
  filas.value.forEach((f, i) => (f.totalTxt = texto(totalFila(f))))
  volcar()
}

onMounted(async () => {
  try {
    const res = await axios.get('/api/v1/expenses/iva/catalogo')
    tipos.value = res.data.tipos
    retenciones.value = res.data.retenciones
  } catch (e) {
    tipos.value = [{ clave: 'iva21', nombre: 'IVA 21 %', porcentaje: 21, autoliquidacion: false }]
  }
  cargar()
})

// Al terminar de cargar un gasto guardado, sus líneas llegan después de montar
watch(
  () => gasto.value.id,
  () => cargar()
)
</script>
