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
              window.location.href = '/login'
              resolve(response)
            })
            .catch((err) => {
              window.location.href = '/login'
              reject(err)
            })
        })
      },
    },
  })()
}
