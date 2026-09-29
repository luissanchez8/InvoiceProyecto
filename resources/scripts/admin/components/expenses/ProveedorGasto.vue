<!--
  Onfactu v.1.15.0 — Proveedor del gasto: nombre, NIF y número de su factura.

  Opcional y sin lista aparte: al escribir el nombre se sugieren los
  proveedores de gastos anteriores y, al elegir uno, se rellena su NIF si está
  vacío. Es lo que necesita la gestoría para el libro de facturas recibidas.
-->
<template>
  <BaseInputGroup :label="$t('gastos_iva.proveedor')" :content-loading="loading">
    <BaseInput
      v-model="gasto.proveedor_nombre"
      :content-loading="loading"
      :list="idLista"
      maxlength="190"
      autocomplete="off"
      :placeholder="$t('gastos_iva.proveedor_ayuda')"
      @update:modelValue="escribiendo"
    />
    <datalist :id="idLista">
      <option v-for="p in sugerencias" :key="p.nombre" :value="p.nombre" />
    </datalist>
  </BaseInputGroup>

  <BaseInputGroup :label="$t('gastos_iva.nif_proveedor')" :content-loading="loading">
    <BaseInput v-model="gasto.proveedor_nif" :content-loading="loading" maxlength="30" autocomplete="off" />
  </BaseInputGroup>

  <BaseInputGroup :label="$t('gastos_iva.numero_factura')" :content-loading="loading">
    <BaseInput v-model="gasto.numero_factura" :content-loading="loading" maxlength="60" autocomplete="off" />
  </BaseInputGroup>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
  store: { type: Object, required: true },
  loading: { type: Boolean, default: false },
})

const gasto = computed(() => props.store.currentExpense)
const sugerencias = ref([])
const idLista = 'proveedores-gasto'
let temporizador = null

function escribiendo(valor) {
  const elegido = sugerencias.value.find((p) => p.nombre === valor)
  if (elegido && elegido.nif && !gasto.value.proveedor_nif) {
    gasto.value.proveedor_nif = elegido.nif
  }

  clearTimeout(temporizador)
  temporizador = setTimeout(() => buscar(valor), 300)
}

async function buscar(texto) {
  try {
    const res = await axios.get('/api/v1/expenses/iva/proveedores', { params: { search: texto || '' } })
    sugerencias.value = res.data.data
  } catch (e) {
    sugerencias.value = []
  }
}

buscar('')
</script>
