<!--
  Onfactu v.1.13.0 — Icono del estado Borrador: libreta.
  Por defecto en naranja (#f19b47). En botones y menús se usa con
  color="currentColor" para que tome el color del texto.
  El estilo va en cada trazo y no en una clase: dentro de una página, las
  clases de los SVG son globales y un icono pisaría el color del otro.
-->
<template>
  <svg
    viewBox="0 0 48.98 42.82" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"
    fill="none" :stroke="color" stroke-width="3.7" stroke-linecap="round" stroke-linejoin="round"
  >
    <path d="M40.17,9.22l-16.22,14.71c-1.62,1.46-6.41,2.14-7.48,1.17-1.07-.97-.34-5.32,1.28-6.78L33.98,3.59c.4-.4.89-.71,1.43-.94.54-.22,1.12-.34,1.72-.35.59-.01,1.18.09,1.73.29.55.2,1.05.5,1.47.88.42.38.75.83.97,1.33.22.5.33,1.03.31,1.57-.01.54-.15,1.07-.39,1.56-.25.49-.6.93-1.04,1.29h0Z"/>
    <path d="M22.69,5.52h-8.5c-1.8,0-3.53.82-4.81,2.28-1.28,1.46-1.99,3.44-1.99,5.5v19.44c0,2.06.72,4.04,1.99,5.5,1.28,1.46,3.01,2.28,4.81,2.28h18.7c3.76,0,5.1-3.5,5.1-7.78v-9.72"/>
    <line x1="28.39" y1="32.24" x2="16.98" y2="32.24"/>
    <line x1="21.67" y1="14.11" x2="16.98" y2="14.11"/>
    <line x1="28.78" y1="23.17" x2="25.08" y2="23.17"/>
  </svg>
</template>

<script setup>
defineProps({ color: { type: String, default: '#f19b47' } })
</script>
