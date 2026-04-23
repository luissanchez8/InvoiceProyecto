<template>
  <div v-if="shouldShow" class="trial-banner" :class="bannerClass">
    <div class="trial-banner__header">
      <BaseIcon :name="iconName" class="trial-banner__icon" />
      <span class="trial-banner__title">{{ title }}</span>
    </div>
    <p class="trial-banner__subtitle">{{ subtitle }}</p>
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
    console.warn('TrialBanner: no se pudo cargar plan-status', err)
  }
}

onMounted(() => {
  fetchPlanStatus()
  refreshTimer = setInterval(fetchPlanStatus, 5 * 60 * 1000)
})

onUnmounted(() => {
  if (refreshTimer) clearInterval(refreshTimer)
})

const shouldShow = computed(() => {
  if (!planStatus.value) return false
  if (planStatus.value === 'trialing') return true
  if (planStatus.value === 'paused' || planStatus.value === 'past_due') return true
  if (planStatus.value === 'canceled') return true
  return false
})

const bannerClass = computed(() => {
  if (planStatus.value === 'trialing') {
    if (daysLeft.value !== null && daysLeft.value <= 7) return 'trial-banner--warning'
    return 'trial-banner--ok'
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
/*
 * Onfactu: banner integrado en el sidebar navy (#070322) con la paleta
 * corporativa. El sidebar hereda flex-direction:column a sus hijos, así que
 * forzamos display explícito con !important para evitar que el texto salga
 * en vertical.
 *
 * Paleta:
 *   - verde corporativo #38d587 (logo, botones activos).
 *   - navy #070322 (fondo sidebar).
 *   - blanco sobre oscuro para el cuerpo; acento en color cuando hay urgencia.
 */
.trial-banner {
  display: block !important;
  padding: 12px;
  border-radius: 10px;
  margin: 10px 8px;
  font-size: 12px;
  line-height: 1.4;
  word-wrap: break-word;
  overflow-wrap: break-word;
  box-sizing: border-box;
  border: 1px solid;
}

/* Estado OK (trial con margen): verde corporativo */
.trial-banner--ok {
  background: rgba(56, 213, 135, 0.12);    /* #38d587 con alpha */
  border-color: rgba(56, 213, 135, 0.35);
  color: #ffffff;
}
.trial-banner--ok .trial-banner__icon,
.trial-banner--ok .trial-banner__title {
  color: #38d587;
}
.trial-banner--ok .trial-banner__button {
  background: #38d587;
  color: #070322 !important;
}

/* Estado WARNING (<= 7 días de trial): naranja */
.trial-banner--warning {
  background: rgba(251, 146, 60, 0.12);
  border-color: rgba(251, 146, 60, 0.35);
  color: #ffffff;
}
.trial-banner--warning .trial-banner__icon,
.trial-banner--warning .trial-banner__title {
  color: #fb923c;
}
.trial-banner--warning .trial-banner__button {
  background: #fb923c;
  color: #070322 !important;
}

/* Estado DANGER (gracia / cancelado): rojo */
.trial-banner--danger {
  background: rgba(248, 113, 113, 0.12);
  border-color: rgba(248, 113, 113, 0.35);
  color: #ffffff;
}
.trial-banner--danger .trial-banner__icon,
.trial-banner--danger .trial-banner__title {
  color: #f87171;
}
.trial-banner--danger .trial-banner__button {
  background: #f87171;
  color: #ffffff !important;
}

.trial-banner__header {
  display: flex !important;
  align-items: center;
  gap: 6px;
  margin-bottom: 4px;
}
.trial-banner__icon {
  width: 16px !important;
  height: 16px !important;
  flex-shrink: 0;
}
.trial-banner__title {
  display: inline !important;
  font-weight: 700;
  font-size: 12px;
  line-height: 1.3;
  word-break: normal;
  white-space: normal;
}
.trial-banner__subtitle {
  display: block !important;
  font-weight: 400;
  margin: 0;
  opacity: 0.8;
  font-size: 11px;
  line-height: 1.35;
  word-break: normal;
  white-space: normal;
  color: rgba(255, 255, 255, 0.75);
}
.trial-banner__button {
  display: block !important;
  margin-top: 8px;
  padding: 7px 10px;
  border-radius: 6px;
  font-weight: 700;
  text-decoration: none;
  text-align: center;
  font-size: 11px;
  line-height: 1.2;
  word-break: normal;
  white-space: normal;
  transition: opacity 0.15s;
}
.trial-banner__button:hover {
  opacity: 0.85;
}
</style>
