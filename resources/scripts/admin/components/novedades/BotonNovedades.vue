<!--
  Onfactu v.1.18.0 — Botón de la barra de arriba para abrir las novedades.
  Lleva un punto verde mientras haya novedades sin ver.
-->
<template>
  <button
    type="button"
    class="relative flex items-center justify-center w-8 h-8 ml-2 bg-white rounded md:h-9 md:w-9 hover:bg-gray-100"
    :title="$t('novedades.titulo')"
    :aria-label="$t('novedades.titulo')"
    @click="store.abrir()"
  >
    <BaseIcon name="SparklesIcon" class="w-5 h-5 text-gray-500" />
    <span
      v-if="store.hayNuevas"
      class="absolute top-1 right-1 h-2 w-2 rounded-full bg-[#38d587] ring-2 ring-white"
    />
  </button>
</template>

<script setup>
import { useNovedadesStore } from '@/scripts/admin/stores/novedades'

const store = useNovedadesStore()
</script>
