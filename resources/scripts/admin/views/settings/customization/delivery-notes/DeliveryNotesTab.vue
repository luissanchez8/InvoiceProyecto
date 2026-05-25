<!--
  Tab de personalización de Albaranes.
  Contiene el configurador de formato de numeración (serie, delimitador, secuencia).
-->
<template>
  <DeliveryNotesTabNumber />

  <BaseDivider class="my-8" />

  <DeliveryNotesTabFooter />

</template>

<script setup>
import DeliveryNotesTabNumber from './DeliveryNotesTabNumber.vue'
import DeliveryNotesTabFooter from './DeliveryNotesTabFooter.vue'
</script>
