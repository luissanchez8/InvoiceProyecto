/*
 * Onfactu v.1.15.0 — Estado y cálculo del desglose de IVA de un gasto.
 *
 * Lo comparten la tabla de tipos (DesgloseIvaGasto.vue) y el cuadro de
 * totales (TotalesGasto.vue), como en las facturas lo comparten la tabla de
 * artículos y el total. Importes en céntimos.
 *
 * El servidor lo recalcula todo al guardar (App\Support\IvaGastos): aquí solo
 * se calcula para enseñarlo mientras se escribe.
 */
import { ref, reactive, computed } from 'vue'
import axios from 'axios'

export function useDesgloseIva(store) {
  const gasto = computed(() => store.currentExpense)
  const tipos = ref([])
  const retenciones = ref([0, 7, 15, 19])
  const filas = reactive([])
  const listo = ref(false)

  const porClave = computed(() => Object.fromEntries(tipos.value.map((t) => [t.clave, t])))
  const porcentaje = (tipo) => porClave.value[tipo]?.porcentaje ?? 0
  const esAutoliquidacion = (tipo) => !!porClave.value[tipo]?.autoliquidacion

  const cuota = (f) => Math.round((f.base * porcentaje(f.tipo)) / 100)
  const totalFila = (f) => f.base + (esAutoliquidacion(f.tipo) ? 0 : cuota(f))

  const resumen = computed(() => {
    let base = 0
    let iva = 0
    let autoliquidado = 0
    filas.forEach((f) => {
      base += f.base
      if (esAutoliquidacion(f.tipo)) autoliquidado += cuota(f)
      else iva += cuota(f)
    })
    const retencion = Math.round((base * (gasto.value.retencion_porcentaje || 0)) / 100)
    return { base, iva, autoliquidado, retencion, total: base + iva - retencion }
  })

  // Del formulario al gasto: lo que se envía al guardar
  function volcar() {
    gasto.value.lineas_iva = filas.map((f) => ({ tipo: f.tipo, base: f.base, deducible: !f.noDeducible }))
    gasto.value.amount = resumen.value.total
  }

  // Los importes llegan en euros desde BaseMoney. Si no cambian, no se toca
  // nada: así escribir en un campo no rebota en el otro.
  function cambiarBase(i, euros) {
    const base = Math.round((parseFloat(euros) || 0) * 100)
    if (base === filas[i].base) return
    filas[i].base = base
    volcar()
  }

  function cambiarTotal(i, euros) {
    const f = filas[i]
    const total = Math.round((parseFloat(euros) || 0) * 100)
    if (total === totalFila(f)) return
    f.base = esAutoliquidacion(f.tipo) ? total : Math.round((total * 100) / (100 + porcentaje(f.tipo)))
    volcar()
  }

  function anadir() {
    filas.push({ id: Date.now() + filas.length, tipo: 'iva21', base: 0, noDeducible: false })
    volcar()
  }

  function quitar(i) {
    filas.splice(i, 1)
    volcar()
  }

  // Del gasto al formulario: al abrir uno guardado o uno nuevo
  function cargar() {
    const lineas = gasto.value.lineas_iva?.length ? gasto.value.lineas_iva : [{ tipo: 'iva21', base: 0, deducible: true }]
    filas.splice(0, filas.length, ...lineas.map((l, n) => ({
      id: n + 1,
      tipo: l.tipo,
      base: Number(l.base) || 0,
      noDeducible: l.deducible === false,
    })))
    if (gasto.value.retencion_porcentaje == null) gasto.value.retencion_porcentaje = 0
    gasto.value.retencion_porcentaje = Number(gasto.value.retencion_porcentaje)
    volcar()
  }

  async function iniciar() {
    try {
      const res = await axios.get('/api/v1/expenses/iva/catalogo')
      tipos.value = res.data.tipos
      retenciones.value = res.data.retenciones
    } catch (e) {
      tipos.value = [{ clave: 'iva21', nombre: 'IVA 21 %', porcentaje: 21, autoliquidacion: false }]
    }
    cargar()
    listo.value = true
  }

  // reactive: los componentes lo reciben como prop y leen los valores sin .value
  return reactive({
    gasto, tipos, retenciones, filas, listo, resumen,
    porcentaje, esAutoliquidacion, cuota, totalFila,
    cambiarBase, cambiarTotal, anadir, quitar, volcar, cargar, iniciar,
  })
}
