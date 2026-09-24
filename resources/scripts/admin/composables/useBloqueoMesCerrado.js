import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { useNotificationStore } from '@/scripts/stores/notification'

/**
 * Onfactu — No deja abrir en edición un documento de un mes cerrado.
 *
 * El bloqueo real está en el servidor (middleware CheckMonthClosed): aunque
 * esto fallara, el cambio no se guardaría. Esto evita que el cliente rellene
 * un formulario que luego no puede guardar.
 *
 * Se llama con una línea desde cada formulario de creación y edición:
 *   useBloqueoMesCerrado('expenses')
 * Solo actúa en edición (rutas que terminan en ".edit").
 *
 * @param {string} tipo  invoices | estimates | expenses | payments | proforma-invoices | delivery-notes
 */
export function useBloqueoMesCerrado(tipo) {
  const route = useRoute()
  const router = useRouter()
  const notificationStore = useNotificationStore()

  onMounted(async () => {
    if (!String(route.name || '').endsWith('.edit') || !route.params.id) return

    try {
      const { data } = await axios.get(`/api/v1/closed-months/check/${tipo}/${route.params.id}`)
      if (!data.cerrado) return

      notificationStore.showNotification({ type: 'error', message: data.message })

      // Vuelve a donde estaba (la lista o la ficha); si entró por enlace directo, a la lista
      if (window.history.length > 1) router.back()
      else router.replace(`/admin/${tipo}`)
    } catch (e) {
      // Si la comprobación falla, el servidor sigue impidiendo guardar
    }
  })
}
