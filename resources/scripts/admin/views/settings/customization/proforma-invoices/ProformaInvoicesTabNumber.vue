<!--
  Configurador de formato de numeración de facturas proforma.
  En vez de importar el store completo (que puede fallar en lazy load),
  se pasa un objeto simple con solo el método getNextNumber que necesita
  el NumberCustomizer para previsualizar números.
-->
<template>
  <NumberCustomizer
    type="proformainvoice"
    :type-store="simpleStore"
    default-series="PRF"
  />
</template>

<script setup>
import axios from 'axios'
import { handleError } from '@/scripts/helpers/error-handling'
import NumberCustomizer from '../NumberCustomizer.vue'

/**
 * Objeto simple que solo expone getNextNumber().
 * El NumberCustomizer solo necesita este método para
 * previsualizar el siguiente número formateado.
 */
const simpleStore = {
  getNextNumber(params) {
    return new Promise((resolve, reject) => {
      axios
        .get('/api/v1/next-number?key=proformainvoice', { params })
        .then((response) => resolve(response))
        .catch((err) => { handleError(err); reject(err) })
    })
  },
}
</script>
