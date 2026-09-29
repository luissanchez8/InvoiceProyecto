<!--
  Onfactu v.1.15.0 — Proveedor del gasto: nombre, NIF y número de su factura.

  Opcional y sin lista de proveedores aparte. El nombre es un selector como el
  de Cliente: sugiere los proveedores de gastos anteriores y deja escribir uno
  nuevo. Al elegir uno ya usado, rellena su NIF si está vacío.
-->
<template>
  <BaseInputGroup :label="$t('gastos_iva.proveedor')" :content-loading="loading">
    <BaseMultiselect
      v-if="!loading"
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
</template>

<script setup>
import { computed } from 'vue'
import axios from 'axios'

const props = defineProps({
  store: { type: Object, required: true },
  loading: { type: Boolean, default: false },
})

const gasto = computed(() => props.store.currentExpense)
let ultimos = []

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
</script>
