<!--
  Onfactu v.1.15.3 — Gestión de los proveedores de los gastos, en una ventana
  que se abre desde el campo Proveedor del formulario.

  No hay lista de proveedores aparte: el proveedor va escrito en cada gasto.
  Aquí se corrige a la vez en todos sus gastos:
    - Editar: nombre y NIF. Si el nombre nuevo ya existe, se unen en uno.
    - Quitar: deja esos gastos sin proveedor (no borra los gastos).
  Los gastos de meses cerrados no se tocan (lo decide el servidor).

  Emite `cambiado` con { de, a, nif } (a = null si se ha quitado), para que el
  formulario actualice el gasto que se está editando.
-->
<template>
  <BaseModal :show="show" @close="$emit('close')">
    <template #header>
      <span>{{ $t('gastos_iva.proveedores') }}</span>
    </template>

    <div class="px-6 pt-5 pb-2">
      <p class="mb-3 text-sm text-gray-500">{{ $t('gastos_iva.proveedores_ayuda') }}</p>
      <BaseInput v-model="buscar" :placeholder="$t('gastos_iva.buscar_proveedor')" />
    </div>

    <div class="px-6 pb-4 overflow-y-auto max-h-[55vh]">
      <p v-if="!cargando && !lista.length" class="py-8 text-sm text-center text-gray-400">
        {{ $t('gastos_iva.sin_proveedores') }}
      </p>

      <div v-for="p in lista" :key="p.nombre" class="py-3 border-b border-gray-100 last:border-0">
        <!-- Editando -->
        <div v-if="editando === p.nombre" class="space-y-2">
          <div class="grid gap-2 sm:grid-cols-2">
            <BaseInput v-model="form.nombre" maxlength="190" :placeholder="$t('gastos_iva.proveedor')" />
            <BaseInput v-model="form.nif" maxlength="30" :placeholder="$t('gastos_iva.nif_proveedor')" />
          </div>
          <p v-if="seUnira" class="text-xs text-amber-700">{{ $t('gastos_iva.se_unira') }}</p>
          <div class="flex justify-end gap-2">
            <BaseButton variant="white" size="sm" @click="editando = null">{{ $t('general.cancel') }}</BaseButton>
            <BaseButton variant="primary" size="sm" :loading="guardando" :disabled="!form.nombre.trim()" @click="guardar(p)">
              {{ $t('general.save') }}
            </BaseButton>
          </div>
        </div>

        <!-- Confirmar quitar -->
        <div v-else-if="quitando === p.nombre" class="flex flex-wrap items-center justify-between gap-2">
          <span class="text-sm text-gray-700">
            <strong>{{ p.nombre }}</strong>: {{ $t('gastos_iva.quitar_aviso') }}
          </span>
          <div class="flex gap-2">
            <BaseButton variant="white" size="sm" @click="quitando = null">{{ $t('general.cancel') }}</BaseButton>
            <BaseButton variant="danger" size="sm" :loading="guardando" @click="quitar(p)">
              {{ $t('gastos_iva.quitar_proveedor') }}
            </BaseButton>
          </div>
        </div>

        <!-- Fila normal -->
        <div v-else class="flex items-center justify-between gap-3">
          <div class="min-w-0">
            <div class="text-sm font-medium text-gray-900 truncate">{{ p.nombre }}</div>
            <div class="text-xs text-gray-500">
              <span v-if="p.nif" class="mr-2 tabular-nums">{{ p.nif }}</span>
              <span>{{ $t('gastos_iva.n_gastos', p.gastos) }}</span>
              <span v-if="p.cerrados" class="ml-1 text-gray-400">({{ $t('gastos_iva.n_cerrados', { n: p.cerrados }) }})</span>
            </div>
          </div>
          <div class="flex items-center gap-3 shrink-0">
            <button type="button" class="text-sm font-medium text-primary-500 hover:text-primary-600" @click="editar(p)">
              {{ $t('gastos_iva.editar') }}
            </button>
            <button type="button" class="text-sm text-gray-400 hover:text-red-500" @click="quitando = p.nombre; editando = null">
              {{ $t('gastos_iva.quitar_proveedor') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end px-6 py-4 border-t border-gray-200">
        <BaseButton variant="white" @click="$emit('close')">{{ $t('gastos_iva.cerrar') }}</BaseButton>
      </div>
    </template>
  </BaseModal>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import axios from 'axios'
import { handleError } from '@/scripts/helpers/error-handling'
import { useNotificationStore } from '@/scripts/stores/notification'

const props = defineProps({ show: { type: Boolean, default: false } })
const emit = defineEmits(['close', 'cambiado'])

const { t } = useI18n()
const avisos = useNotificationStore()

const lista = ref([])
const buscar = ref('')
const cargando = ref(false)
const guardando = ref(false)
const editando = ref(null)
const quitando = ref(null)
const form = reactive({ nombre: '', nif: '' })
let pausa = null

// Aviso de que dos proveedores se van a unir: el nombre nuevo ya lo lleva otro
const seUnira = computed(() => {
  const nuevo = form.nombre.trim()
  return nuevo !== editando.value && lista.value.some((p) => p.nombre === nuevo)
})

watch(() => props.show, (abierto) => {
  if (abierto) {
    buscar.value = ''
    clearTimeout(pausa)
    editando.value = quitando.value = null
    cargar()
  }
})

async function cargar() {
  cargando.value = true
  try {
    const res = await axios.get('/api/v1/expenses/iva/proveedores/gestion', { params: { search: buscar.value } })
    lista.value = res.data.data
  } catch (e) {
    handleError(e)
  } finally {
    cargando.value = false
  }
}

watch(buscar, () => {
  clearTimeout(pausa)
  pausa = setTimeout(cargar, 300)
})

function editar(p) {
  quitando.value = null
  editando.value = p.nombre
  form.nombre = p.nombre
  form.nif = p.nif || ''
}

async function guardar(p) {
  await enviar(() => axios.put('/api/v1/expenses/iva/proveedores', {
    nombre: p.nombre, nuevo_nombre: form.nombre.trim(), nif: form.nif,
  }), 'gastos_iva.actualizado', { de: p.nombre, a: form.nombre.trim(), nif: form.nif.replace(/[\s.-]/g, '').toUpperCase() || null })
}

async function quitar(p) {
  await enviar(() => axios.post('/api/v1/expenses/iva/proveedores/quitar', { nombre: p.nombre }),
    'gastos_iva.quitado', { de: p.nombre, a: null, nif: null })
}

async function enviar(peticion, mensaje, cambio) {
  guardando.value = true
  try {
    const { data } = await peticion()
    let texto = t(mensaje, data.cambiados)
    if (data.cerrados) texto += ' ' + t('gastos_iva.cerrados_sin_cambiar', data.cerrados)
    avisos.showNotification({ type: 'success', message: texto })
    editando.value = quitando.value = null
    emit('cambiado', cambio)
    await cargar()
  } catch (e) {
    handleError(e)
  } finally {
    guardando.value = false
  }
}
</script>
