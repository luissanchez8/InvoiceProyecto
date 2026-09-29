<!--
  Onfactu v.1.15.0 — Proveedor del gasto: nombre, NIF y número de su factura.

  Opcional y sin lista de proveedores aparte. El nombre es un selector como el
  de Cliente: sugiere los proveedores de gastos anteriores y deja escribir uno
  nuevo. Al elegir uno ya usado, rellena su NIF si está vacío.

  v.1.15.3: "Gestionar", junto a la etiqueta, abre la ventana para corregir o
  quitar un proveedor en todos sus gastos (ModalProveedores).
-->
<template>
  <BaseInputGroup :label="$t('gastos_iva.proveedor')" :content-loading="loading">
    <template #labelRight>
      <button type="button" class="text-xs font-medium text-primary-500 hover:text-primary-600" @click="gestionar = true">
        {{ $t('gastos_iva.gestionar') }}
      </button>
    </template>
    <BaseMultiselect
      v-if="!loading"
      :key="refrescar"
      v-model="gasto.proveedor_nombre"
      :options="buscar"
      value-prop="nombre"
      label="nombre"
      track-by="nombre"
      :filter-results="false"
      :create-tag="true"
      resolve-on-load
      :delay="300"
      searchable
      :placeholder="$t('gastos_iva.proveedor_ayuda')"
      @select="elegido"
    />
  </BaseInputGroup>

  <BaseInputGroup :label="$t('gastos_iva.nif_proveedor')" :content-loading="loading">
    <BaseInput v-model="gasto.proveedor_nif" :content-loading="loading" maxlength="30" />
  </BaseInputGroup>

  <BaseInputGroup :label="$t('gastos_iva.numero_factura')" :content-loading="loading">
    <BaseInput v-model="gasto.numero_factura" :content-loading="loading" maxlength="60" />
  </BaseInputGroup>

  <ModalProveedores :show="gestionar" @close="gestionar = false" @cambiado="cambiado" />
</template>

<script setup>
import { computed, ref } from 'vue'
import axios from 'axios'
import ModalProveedores from './ModalProveedores.vue'

const props = defineProps({
  store: { type: Object, required: true },
  loading: { type: Boolean, default: false },
})

const gasto = computed(() => props.store.currentExpense)
let ultimos = []
const gestionar = ref(false)
// Al cambiar el valor desde fuera, el selector se vuelve a montar para que
// muestre el nombre nuevo y vuelva a pedir las sugerencias.
const refrescar = ref(0)

async function buscar(texto) {
  try {
    const res = await axios.get('/api/v1/expenses/iva/proveedores', { params: { search: texto || '' } })
    ultimos = res.data.data
  } catch (e) {
    ultimos = []
  }
  // El proveedor del gasto que se edita, aunque no salga entre los sugeridos
  const actual = gasto.value.proveedor_nombre
  if (actual && !ultimos.some((p) => p.nombre === actual)) {
    return [{ nombre: actual, nif: gasto.value.proveedor_nif }, ...ultimos]
  }
  return ultimos
}

function elegido(nombre) {
  const p = ultimos.find((x) => x.nombre === nombre)
  if (p && p.nif && !gasto.value.proveedor_nif) {
    gasto.value.proveedor_nif = p.nif
  }
}

// Si en la ventana se corrige o se quita el proveedor del gasto que se está
// editando, se cambia también aquí: si no, al guardar el formulario volvería
// a escribir el nombre antiguo.
function cambiado({ de, a, nif }) {
  if (gasto.value.proveedor_nombre === de) {
    gasto.value.proveedor_nombre = a
    gasto.value.proveedor_nif = nif
  }
  refrescar.value++
}
</script>
