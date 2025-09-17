<template>
  <img
    v-if="logoUrl"
    :src="logoUrl"
    :alt="companyName || 'Logo Empresa'"
    style="height: 65px; width: auto; object-fit: contain;"
    class="h-6"
  />
</template>

<script setup>
import { ref, onMounted } from 'vue'

const sanitize = v => (typeof v === 'string' && v.trim().length ? v.trim() : '')
const fallback = '/images/logo.png'

// pinta algo desde el primer render (sin parpadeo)
const logoUrl = ref(sanitize(window.customer_logo || '') || fallback)
const companyName = ref(sanitize(window.brand_name || '') || 'Mi Empresa')

// luego consulta el endpoint de web.php para sobreescribir si cambia
onMounted(async () => {
  try {
    const endpoint = sanitize(window.LOGO_ENDPOINT || '/logo-url')
    const res = await fetch(endpoint, { headers: { Accept: 'application/json' } })
    if (!res.ok) return
    const data = await res.json()
    const u = sanitize(data?.url)
    const n = sanitize(data?.name)
    if (u) logoUrl.value = u
    if (n) companyName.value = n
  } catch {}
})
</script>
