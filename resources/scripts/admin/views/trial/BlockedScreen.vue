<template>
  <div class="trial-blocked">
    <div class="trial-blocked__card">
      <div class="trial-blocked__icon">
        <BaseIcon name="LockClosedIcon" class="h-16 w-16 text-red-500" />
      </div>

      <h1 class="trial-blocked__title">
        {{ title }}
      </h1>

      <p class="trial-blocked__message">
        {{ message }}
      </p>

      <div v-if="graceEndsAt" class="trial-blocked__countdown">
        <p>{{ $t('trial.blocked_grace_deadline') }}</p>
        <strong>{{ formattedGraceEndsAt }}</strong>
      </div>

      <div class="trial-blocked__actions">
        <a
          v-if="portalUrl"
          :href="portalUrl"
          target="_blank"
          rel="noopener"
          class="trial-blocked__button trial-blocked__button--primary"
        >
          <BaseIcon name="CreditCardIcon" class="h-5 w-5 mr-2" />
          {{ $t('trial.add_payment_method') }}
        </a>

        <a
          href="mailto:soporte@onfactu.com"
          class="trial-blocked__button trial-blocked__button--secondary"
        >
          <BaseIcon name="EnvelopeIcon" class="h-5 w-5 mr-2" />
          {{ $t('trial.contact_support') }}
        </a>
      </div>

      <button
        class="trial-blocked__logout"
        @click="logout"
      >
        {{ $t('navigation.logout') }}
      </button>
    </div>
  </div>
</template>

<script setup>
/**
 * Onfactu: pantalla que se muestra cuando el backend devuelve 402
 * (subscription bloqueada). El frontend redirige aquí automáticamente
 * y el usuario ve el mensaje + botón para añadir tarjeta.
 */
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const planStatus = ref(route.query.reason || '')
const trialEndsAt = ref(route.query.trial_ends_at || '')
const graceEndsAt = ref(route.query.grace_ends_at || '')
const portalUrl = ref(null)

onMounted(async () => {
  // Si faltan datos en la URL, pedirlos al backend.
  try {
    const { data } = await axios.get('/api/v1/stripe/plan-status')
    if (!planStatus.value) planStatus.value = data.plan_status
    if (!graceEndsAt.value) graceEndsAt.value = data.grace_ends_at || ''
    portalUrl.value = data.portal_url
  } catch (err) {
    console.warn('BlockedScreen: no se pudo cargar plan-status', err)
  }
})

const title = computed(() => {
  if (planStatus.value === 'trial_grace_write_blocked') return t('trial.blocked_grace_title')
  if (planStatus.value === 'trial_grace_expired')       return t('trial.blocked_expired_title')
  if (planStatus.value === 'subscription_canceled')     return t('trial.blocked_canceled_title')
  if (planStatus.value === 'paused')                    return t('trial.blocked_grace_title')
  if (planStatus.value === 'canceled')                  return t('trial.blocked_canceled_title')
  return t('trial.blocked_generic_title')
})

const message = computed(() => {
  if (planStatus.value === 'trial_grace_write_blocked') return t('trial.blocked_grace_message')
  if (planStatus.value === 'trial_grace_expired')       return t('trial.blocked_expired_message')
  if (planStatus.value === 'subscription_canceled')     return t('trial.blocked_canceled_message')
  if (planStatus.value === 'paused')                    return t('trial.blocked_grace_message')
  if (planStatus.value === 'canceled')                  return t('trial.blocked_canceled_message')
  return t('trial.blocked_generic_message')
})

const formattedGraceEndsAt = computed(() => {
  if (!graceEndsAt.value) return ''
  try {
    const d = new Date(graceEndsAt.value)
    return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'long', year: 'numeric' })
  } catch (e) {
    return graceEndsAt.value
  }
})

async function logout() {
  try {
    await axios.post('/api/v1/auth/logout')
  } catch (e) { /* silent */ }
  window.location.href = '/admin/login'
}
</script>

<style scoped>
.trial-blocked {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f9fafb;
  padding: 20px;
}
.trial-blocked__card {
  max-width: 520px;
  width: 100%;
  background: white;
  padding: 40px 32px;
  border-radius: 12px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.08);
  text-align: center;
}
.trial-blocked__icon {
  display: flex;
  justify-content: center;
  margin-bottom: 20px;
}
.trial-blocked__title {
  font-size: 24px;
  font-weight: 700;
  color: #111827;
  margin: 0 0 12px 0;
}
.trial-blocked__message {
  color: #4b5563;
  font-size: 15px;
  line-height: 1.6;
  margin: 0 0 20px 0;
}
.trial-blocked__countdown {
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  padding: 14px;
  margin-bottom: 24px;
}
.trial-blocked__countdown p {
  margin: 0 0 4px 0;
  font-size: 13px;
  color: #991b1b;
}
.trial-blocked__countdown strong {
  color: #991b1b;
  font-size: 16px;
}
.trial-blocked__actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 20px;
}
.trial-blocked__button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 12px 18px;
  border-radius: 8px;
  font-weight: 600;
  text-decoration: none;
  font-size: 15px;
}
.trial-blocked__button--primary {
  background: #059669;
  color: white;
}
.trial-blocked__button--primary:hover { background: #047857; }
.trial-blocked__button--secondary {
  background: white;
  color: #4b5563;
  border: 1px solid #d1d5db;
}
.trial-blocked__button--secondary:hover { background: #f3f4f6; }

.trial-blocked__logout {
  background: none;
  border: none;
  color: #6b7280;
  font-size: 13px;
  cursor: pointer;
  text-decoration: underline;
  padding: 4px;
}
.trial-blocked__logout:hover { color: #111827; }
</style>
