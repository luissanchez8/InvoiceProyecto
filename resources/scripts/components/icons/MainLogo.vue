<template>
  <img
    :src="logoUrl"
    alt="Logo Empresa"
    style="height: 65px; width: auto;"
  />
</template>

<script setup>
import { ref, onMounted } from 'vue';

const logoUrl = ref(''); // valor inicial vacío

onMounted(async () => {
  try {
    const res = await fetch('/api/logo-url'); // Llama a tu endpoint Laravel
    const data = await res.json();
    if (data.url) {
      logoUrl.value = data.url;
    } else {
      console.warn('No se encontró URL del logo');
    }
  } catch (e) {
    console.error('Error cargando logo:', e);
  }
});
</script>
