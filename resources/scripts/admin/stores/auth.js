import axios from 'axios'
import { defineStore } from 'pinia'
import { useNotificationStore } from '@/scripts/stores/notification'
import { handleError } from '@/scripts/helpers/error-handling'

export const useAuthStore = (useWindow = false) => {
  const defineStoreFunc = useWindow ? window.pinia.defineStore : defineStore
  const { global } = window.i18n

  return defineStoreFunc({
    id: 'auth',
    state: () => ({
      status: '',

      loginData: {
        email: '',
        password: '',
        remember: '',
      },
    }),

    actions: {
      login(data) {
        return new Promise((resolve, reject) => {
          axios.get('/sanctum/csrf-cookie').then((response) => {
            if (response) {
              axios
                .post('/login', data)
                .then((response) => {
                  resolve(response)

                  setTimeout(() => {
                    this.loginData.email = ''
                    this.loginData.password = ''
                  }, 1000)
                })
                .catch((err) => {
                  handleError(err)
                  reject(err)
                })
            }
          })
        })
      },

      logout() {
        return new Promise((resolve, reject) => {
          axios
            .post('/auth/logout')
            .then((response) => {
              const notificationStore = useNotificationStore()
              notificationStore.showNotification({
                type: 'success',
                message: 'Logged out successfully.',
              })

              // Limpiar cookies de sesión para evitar 401 al reloguearse
              document.cookie.split(';').forEach((c) => {
                document.cookie = c.trim().split('=')[0] + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/'
              })

              window.router.push('/login')
              resolve(response)
            })
            .catch((err) => {
              // Si falla el logout, limpiar cookies igualmente
              document.cookie.split(';').forEach((c) => {
                document.cookie = c.trim().split('=')[0] + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/'
              })

              handleError(err)
              window.router.push('/login')
              reject(err)
            })
        })
      },
    },
  })()
}
