<template>
  <div class="onf-login-wrapper">
    <NotificationRoot />

    <!-- Columna izquierda: logo + formulario -->
    <div class="onf-login-left">
      <!-- Logo Onfactu -->
      <img
        src="/images/logo-onfactu-2.png"
        alt="Onfactu"
        class="onf-login-brand"
      />

      <!-- Card con formulario -->
      <div class="onf-login-card">
        <!-- Formulario (lo inyecta el router) -->
        <router-view />
      </div>

      <!-- Copyright -->
      <p class="onf-login-copyright">
        {{ copyrightText }}
      </p>
    </div>

    <!-- Columna derecha: imagen de fondo + texto -->
    <div class="onf-login-right">
      <div class="onf-login-right-overlay">
        <h1 class="onf-login-heading">
          {{ pageHeading }}
        </h1>
        <p v-if="pageDescription" class="onf-login-description">
          {{ pageDescription }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import NotificationRoot from '@/scripts/components/notifications/NotificationRoot.vue'
import { computed } from 'vue'

const pageHeading = computed(() => {
  if (window.login_page_heading) {
    return window.login_page_heading
  }
  return 'Facturación electrónica simple. Sin complicaciones'
})

const pageDescription = computed(() => {
  if (window.login_page_description) {
    return window.login_page_description
  }
  return ''
})

const copyrightText = computed(() => {
  if (window.copyright_text) {
    return window.copyright_text
  }
  return 'Copyright © Onfactu ' + new Date().getFullYear()
})

const loginPageLogo = computed(() => {
  if (window.login_page_logo) {
    return window.login_page_logo
  }
  return false
})
</script>

<style scoped>
.onf-login-wrapper {
  display: flex;
  min-height: 100vh;
  width: 100%;
  background-color: #f6f7f8;
}

/* ─── Columna izquierda ─── */
.onf-login-left {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 2rem;
  background-color: #f6f7f8;
  min-width: 0;
}

.onf-login-brand {
  max-width: 220px;
  height: auto;
  margin-bottom: 2.5rem;
}

.onf-login-card {
  width: 100%;
  max-width: 420px;
  background: #ffffff;
  border-radius: 16px;
  padding: 2rem 2rem 2.25rem;
  box-shadow: 0 4px 24px rgba(15, 23, 42, 0.05);
}

.onf-login-copyright {
  margin-top: 2.5rem;
  font-size: 0.8125rem;
  color: #94a3b8;
  text-align: center;
}

/* ─── Columna derecha (imagen de fondo) ─── */
.onf-login-right {
  flex: 1;
  position: relative;
  display: none;
  background-image: url('/images/saas-bg.jpg');
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  min-height: 100vh;
  overflow: hidden;
}

.onf-login-right-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 2.5rem;
  text-align: center;
}

.onf-login-heading {
  color: #ffffff;
  font-size: clamp(1.75rem, 2.8vw, 2.75rem);
  font-weight: 700;
  line-height: 1.2;
  max-width: 700px;
  margin: 0;
  text-shadow: 0 2px 16px rgba(0, 0, 0, 0.35);
}

.onf-login-description {
  color: #e2e8f0;
  font-size: 1rem;
  line-height: 1.5;
  max-width: 560px;
  margin: 1.25rem 0 0;
  text-shadow: 0 2px 12px rgba(0, 0, 0, 0.35);
}

/* ─── Responsive ─── */
@media (min-width: 768px) {
  .onf-login-right {
    display: block;
  }
}

@media (max-width: 767px) {
  .onf-login-wrapper {
    flex-direction: column;
  }
  .onf-login-left {
    padding: 1.5rem;
  }
  .onf-login-brand {
    max-width: 180px;
    margin-bottom: 1.5rem;
  }
}
</style>
