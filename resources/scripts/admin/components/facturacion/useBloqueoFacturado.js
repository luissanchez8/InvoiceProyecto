/**
 * Onfactu v.1.17.0 — Un presupuesto, proforma o albarán facturado no se edita.
 * Si se abre su pantalla de edición (por un enlace guardado, por ejemplo),
 * avisa y vuelve a la pantalla del documento. El servidor lo bloquea igual
 * (CheckDocumentoFacturado); esto evita rellenar un formulario para nada.
 */
import { watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useNotificationStore } from '@/scripts/stores/notification'

export function useBloqueoFacturado(documento, esEdicion, rutaVista) {
  const router = useRouter()
  const { t } = useI18n()
  const avisos = useNotificationStore()

  watch(
    () => [esEdicion.value, documento()?.billing_status, documento()?.id],
    ([edicion, estado, id]) => {
      if (edicion && estado === 'FACTURADO' && id) {
        avisos.showNotification({ type: 'error', message: t('facturacion.bloqueado') })
        router.replace(rutaVista(id))
      }
    },
    { immediate: true }
  )
}
