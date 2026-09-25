<!--
  Onfactu v.1.13.0 — Icono del estado Aprobada: candado.
  Por defecto en el verde de Onfactu (#38d587). En botones y menús se usa con
  color="currentColor" para que tome el color del texto.
  El estilo va en cada trazo y no en una clase: dentro de una página, las
  clases de los SVG son globales y un icono pisaría el color del otro.
-->
<template>
  <svg
    viewBox="0 0 48.98 42.82" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"
    fill="none" :stroke="color" stroke-width="3.7" stroke-miterlimit="10"
  >
    <path d="M5.43,29.04c0-5.39,0-8.09,1.68-9.76,1.68-1.68,4.37-1.68,9.76-1.68h15.25c5.39,0,8.09,0,9.76,1.68,1.68,1.68,1.68,4.37,1.68,9.76s0,8.09-1.68,9.76-4.37,1.68-9.76,1.68h-15.25c-5.39,0-8.09,0-9.76-1.68s-1.68-4.37-1.68-9.76Z"/>
    <path d="M13.05,17.6v-3.81c0-6.32,5.12-11.44,11.44-11.44s11.44,5.12,11.44,11.44v3.81"/>
  </svg>
</template>

<script setup>
defineProps({ color: { type: String, default: '#38d587' } })
</script>
