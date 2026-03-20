<!--
  Configurador de formato de numeración de albaranes.
  Pasa un objeto simple con getNextNumber() al NumberCustomizer.
-->
<template>
  <NumberCustomizer
    type="deliverynote"
    :type-store="simpleStore"
    default-series="ALB"
  />
</template>

<script setup>
import axios from 'axios'
import { handleError } from '@/scripts/helpers/error-handling'
import NumberCustomizer from '../NumberCustomizer.vue'

const simpleStore = {
  getNextNumber(params) {
    return new Promise((resolve, reject) => {
      axios
        .get('/api/v1/next-number?key=deliverynote', { params })
        .then((response) => resolve(response))
        .catch((err) => { handleError(err); reject(err) })
    })
  },
}
</script>
