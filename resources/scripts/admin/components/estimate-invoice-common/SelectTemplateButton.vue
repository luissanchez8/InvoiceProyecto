<template>
  <!-- Selector de plantilla oculto - plantilla fija -->
</template>

<script setup>
const props = defineProps({
  store: { type: Object, default: null },
  storeProp: { type: String, default: '' },
  isMarkAsDefault: { type: Boolean, default: false },
})
</script>
