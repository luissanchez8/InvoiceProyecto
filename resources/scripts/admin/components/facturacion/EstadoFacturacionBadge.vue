<!--
  Onfactu v.1.17.0 — Estado de facturación de un presupuesto, proforma o
  albarán, al lado de su estado comercial. "Sin facturar" no se enseña salvo
  que se pida (mostrarPendiente): es lo normal y llenaría las listas de ruido.
-->
<template>
  <span v-if="visible" :class="clases">{{ $t(`facturacion.estado_${estado}`) }}</span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  estado: { type: String, default: 'PENDIENTE' },
  mostrarPendiente: { type: Boolean, default: false },
})

const estado = computed(() => props.estado || 'PENDIENTE')
const visible = computed(() => estado.value !== 'PENDIENTE' || props.mostrarPendiente)

const clases = computed(() => {
  const base = 'px-2 py-1 text-sm uppercase font-normal text-center whitespace-nowrap '
  if (estado.value === 'FACTURADO') return base + 'bg-[#38d587] bg-opacity-25 text-[#1a6b3a]'
  if (estado.value === 'ANTICIPO') return base + 'bg-amber-400 bg-opacity-30 text-amber-900'
  return base + 'bg-gray-500 bg-opacity-20 text-gray-800'
})
</script>
