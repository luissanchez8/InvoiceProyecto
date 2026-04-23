import '../sass/invoiceshelf.scss'
import 'v-tooltip/dist/v-tooltip.css'
import '@/scripts/plugins/axios.js'
import * as VueRouter from 'vue-router'
import router from '@/scripts/router/index'
import * as pinia from 'pinia'
import * as Vue from 'vue'
import * as Vuelidate from '@vuelidate/core'

import.meta.glob([
  '../static/img/**',
  '../static/fonts/**',
]);

window.pinia = pinia
window.Vuelidate = Vuelidate
import InvoiceShelf from './InvoiceShelf.js'

window.Vue = Vue
window.router = router
window.VueRouter = VueRouter

window.InvoiceShelf = new InvoiceShelf()
// ─── Onfactu: Interceptor 402 para bloqueo por estado de plan ────────────────
// Si el backend devuelve 402, el middleware CheckPlanStatus ha bloqueado la
// petición. Redirigimos al usuario a la pantalla de bloqueo.
import axios from 'axios'
axios.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error?.response?.status === 402) {
      const data = error.response.data || {}
      const currentPath = window.location.pathname
      if (!currentPath.includes('/admin/trial-blocked')) {
        const qs = new URLSearchParams({
          reason:        data.error || '',
          trial_ends_at: data.trial_ends_at || '',
          grace_ends_at: data.grace_ends_at || '',
        }).toString()
        window.location.href = `/admin/trial-blocked?${qs}`
      }
    }
    return Promise.reject(error)
  }
)
