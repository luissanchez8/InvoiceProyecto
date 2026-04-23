<template>
  <div v-if="shouldShow" class="trial-banner" :class="bannerClass">
    <div class="trial-banner__icon">
      <BaseIcon :name="iconName" class="h-5 w-5" />
    </div>
    <div class="trial-banner__content">
      <p class="trial-banner__title">{{ title }}</p>
      <p class="trial-banner__subtitle">{{ subtitle }}</p>
    </div>
    <a
      v-if="portalUrl"
      :href="portalUrl"
      target="_blank"
      rel="noopener"
      class="trial-banner__button"
    >
      {{ $t('trial.add_payment_method') }}
    </a>
  </div>
</template>

<script setup>
/**
 * Onfactu: banner que se muestra en el sidebar cuando la instancia está
 * en trial o en período de gracia. Consume /api/v1/stripe/plan-status
 * y según el estado muestra:
 *  - Trial normal:     azul, "Te quedan X días de prueba".
 *  - Trial <=7 días:   naranja, urgencia.
 *  - Gracia:           rojo, "Añade tarjeta antes del DD/MM".
 *  - Canceled/paused:  rojo, "Suscripción no activa".
 *
 * Se refresca al montar y cada 5 minutos mientras la sesión esté abierta.
 */
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const planStatus = ref(null)
const daysLeft = ref(null)
const graceDaysLeft = ref(null)
const trialEndsAt = ref(null)
const graceEndsAt = ref(null)
const portalUrl = ref(null)
let refreshTimer = null

async function fetchPlanStatus() {
  try {
    const { data } = await axios.get('/api/v1/stripe/plan-status')
    planStatus.value = data.plan_status
    daysLeft.value = data.days_left
    graceDaysLeft.value = data.grace_days_left
    trialEndsAt.value = data.trial_ends_at
    graceEndsAt.value = data.grace_ends_at
    portalUrl.value = data.portal_url
  } catch (err) {
    // Silencioso: si falla, no mostramos banner.
    console.warn('TrialBanner: no se pudo cargar plan-status', err)
  }
}

onMounted(() => {
  fetchPlanStatus()
  // Refrescar cada 5 minutos mientras el usuario está en la app.
  refreshTimer = setInterval(fetchPlanStatus, 5 * 60 * 1000)
})

onUnmounted(() => {
  if (refreshTimer) clearInterval(refreshTimer)
})

// Mostrar solo si hay algún estado no-normal que comunicar.
const shouldShow = computed(() => {
  if (!planStatus.value) return false
  if (planStatus.value === 'trialing') return true
  if (planStatus.value === 'paused' || planStatus.value === 'past_due') return true
  if (planStatus.value === 'canceled') return true
  // active normal → no banner
  return false
})

const bannerClass = computed(() => {
  if (planStatus.value === 'trialing') {
    if (daysLeft.value !== null && daysLeft.value <= 7) return 'trial-banner--warning'
    return 'trial-banner--info'
  }
  return 'trial-banner--danger'
})

const iconName = computed(() => {
  if (planStatus.value === 'trialing') return 'ClockIcon'
  return 'ExclamationTriangleIcon'
})

const title = computed(() => {
  if (planStatus.value === 'trialing') {
    if (daysLeft.value === null) return t('trial.banner_trial_title_generic')
    if (daysLeft.value <= 1) return t('trial.banner_trial_last_day')
    return t('trial.banner_trial_title', { days: daysLeft.value })
  }
  if (planStatus.value === 'paused' || planStatus.value === 'past_due') {
    if (graceDaysLeft.value !== null && graceDaysLeft.value > 0) {
      return t('trial.banner_grace_title', { days: graceDaysLeft.value })
    }
    return t('trial.banner_grace_expired_title')
  }
  if (planStatus.value === 'canceled') return t('trial.banner_canceled_title')
  return ''
})

const subtitle = computed(() => {
  if (planStatus.value === 'trialing') return t('trial.banner_trial_subtitle')
  if (planStatus.value === 'paused' || planStatus.value === 'past_due') {
    return t('trial.banner_grace_subtitle')
  }
  if (planStatus.value === 'canceled') return t('trial.banner_canceled_subtitle')
  return ''
})
</script>

<style scoped>
.trial-banner {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px;
  border-radius: 8px;
  margin: 12px 8px;
  font-size: 12px;
}

.trial-banner--info {
  background: #eff6ff;
  color: #1e40af;
}
.trial-banner--warning {
  background: #fef3c7;
  color: #92400e;
}
.trial-banner--danger {
  background: #fee2e2;
  color: #991b1b;
}

.trial-banner__icon {
  flex-shrink: 0;
  margin-top: 2px;
}
.trial-banner__content {
  flex: 1;
  min-width: 0;
}
.trial-banner__title {
  font-weight: 600;
  margin: 0;
}
.trial-banner__subtitle {
  font-weight: 400;
  margin: 2px 0 0 0;
  opacity: 0.85;
}
.trial-banner__button {
  display: inline-block;
  margin-top: 8px;
  padding: 6px 10px;
  background: currentColor;
  color: white !important;
  border-radius: 6px;
  font-weight: 600;
  text-decoration: none;
  text-align: center;
  width: 100%;
}
.trial-banner__button:hover {
  opacity: 0.9;
}
</style>
