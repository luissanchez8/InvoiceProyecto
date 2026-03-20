<!--
  Tab de personalización de Facturas Proforma.
  Contiene el configurador de formato de numeración (serie, delimitador, secuencia).
  Reutiliza el componente genérico NumberCustomizer con type="proforma_invoice".
-->
<template>
  <!-- Configurador de formato de número (PRF-000001, etc.) -->
  <ProformaInvoicesTabNumber />
</template>

<script setup>
import ProformaInvoicesTabNumber from './ProformaInvoicesTabNumber.vue'
</script>
