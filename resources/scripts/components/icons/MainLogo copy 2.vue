<template>
  <img
    src="https://www.linkealia.com/imagenes/logo-yellow.png"
    alt="Logo Empresa AAA"
    style="height: 65px; width: auto;"
  />
</template>

<script setup>

import {ref} from 'vue'

const props = defineProps({
  darkColor: {
    type: String,
    default: 'rgba(var(--color-primary-500), var(--tw-text-opacity))',
  },
  lightColor: {
    type: String,
    default: 'rgba(var(--color-primary-400), var(--tw-text-opacity))',
  },
})

const style = ref({
  strokeWidth: '0.25mm',
  stroke: props.lightColor,
  fill: props.lightColor,
});

</script>
