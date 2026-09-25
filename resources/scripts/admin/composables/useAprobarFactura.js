/**
 * Onfactu v.1.13.0 — Qué factura se está aprobando.
 *
 * El menú de acciones de una factura (InvoiceIndexDropdown) no puede tener el
 * diálogo dentro: se cierra al pulsar y lo desmontaría. Así que el menú pide la
 * aprobación aquí y la pantalla (lista o ficha) pinta el diálogo.
 */
import { ref } from 'vue'

const facturaAAprobar = ref(null)

export function useAprobarFactura() {
  return {
    facturaAAprobar,
    pedirAprobacion: (factura) => { facturaAAprobar.value = factura },
    cerrarAprobacion: () => { facturaAAprobar.value = null },
  }
}
