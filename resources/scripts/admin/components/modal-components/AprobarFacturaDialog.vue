<!--
  Onfactu v.1.13.0 — Diálogo para aprobar una factura.

  Avisa de lo que significa aprobar (número definitivo, ya no se puede
  modificar) y llama al servidor. Si la fecha no encaja con el orden de las
  facturas aprobadas, o es de un mes cerrado, enseña el motivo y, si sirve,
  ofrece aprobarla con la fecha de hoy.

  Props:  factura  la factura a aprobar ({ id, invoice_number }), o null para
                   tenerlo cerrado
  Emite:  cerrar                  al cancelar
          aprobada(factura, vf)   al aprobar; vf es true si se envió a
                                  VeriFactu, false si falló y null si no se usa
-->
<template>
  <TransitionRoot as="template" :show="!!factura">
    <Dialog as="div" static class="fixed inset-0 z-20 overflow-y-auto" :open="!!factura" @close="cancelar">
      <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <TransitionChild
          as="template"
          enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
          leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0"
        >
          <DialogOverlay class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" />
        </TransitionChild>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <TransitionChild
          as="template"
          enter="ease-out duration-300"
          enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
          enter-to="opacity-100 translate-y-0 sm:scale-100"
          leave="ease-in duration-200"
          leave-from="opacity-100 translate-y-0 sm:scale-100"
          leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
          <div class="relative inline-block px-6 pt-6 pb-6 overflow-hidden text-left align-bottom transition-all bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
            <div class="text-center">
              <IconoAprobada class="w-8 h-8 mx-auto" />
              <DialogTitle as="h3" class="mt-3 text-xl font-semibold leading-6 text-gray-900">
                {{ $t('estados.aprobar_titulo') }}
              </DialogTitle>
              <p class="mt-3 text-sm leading-relaxed text-gray-600">
                {{ factura?.invoice_number
                  ? $t('estados.aprobar_con_numero', { numero: factura.invoice_number })
                  : $t('estados.aprobar_siguiente_numero') }}
                {{ $t('estados.aprobar_aviso') }}
              </p>
              <p v-if="verifactuActivo" class="mt-2 text-sm text-gray-500">
                {{ $t('estados.aprobar_verifactu') }}
              </p>
            </div>

            <div v-if="error" class="px-4 py-3 mt-4 text-sm text-left border rounded-md text-amber-800 bg-amber-50 border-amber-200">
              {{ error }}
            </div>

            <div class="flex flex-col items-center gap-3 mt-6">
              <BaseButton
                v-if="!error || !puedeUsarHoy"
                variant="primary"
                class="justify-center w-full"
                :loading="cargando"
                :disabled="cargando || (!!error && !puedeUsarHoy)"
                @click="aprobar(false)"
              >
                {{ $t('estados.aprobar') }}
              </BaseButton>
              <BaseButton
                v-else
                variant="primary"
                class="justify-center w-full"
                :loading="cargando"
                :disabled="cargando"
                @click="aprobar(true)"
              >
                {{ $t('estados.aprobar_con_fecha_hoy') }}
              </BaseButton>
              <button type="button" class="text-sm text-gray-400 hover:text-gray-500" :disabled="cargando" @click="cancelar">
                {{ $t('general.cancel') }}
              </button>
            </div>
          </div>
        </TransitionChild>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Dialog, DialogOverlay, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { useInvoiceStore } from '@/scripts/admin/stores/invoice'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import IconoAprobada from '@/scripts/components/icons/estados/IconoAprobada.vue'

const props = defineProps({
  factura: { type: Object, default: null },
})
const emit = defineEmits(['cerrar', 'aprobada'])

const invoiceStore = useInvoiceStore()
const companyStore = useCompanyStore()

const cargando = ref(false)
const error = ref(null)
const puedeUsarHoy = ref(false)

const verifactuActivo = computed(() => companyStore.selectedCompanySettings?.verifactu_enabled === 'YES')

watch(() => props.factura?.id, () => {
  error.value = null
  puedeUsarHoy.value = false
})

async function aprobar(usarFechaHoy) {
  if (!props.factura) return
  cargando.value = true
  error.value = null
  try {
    const res = await invoiceStore.approveInvoice(props.factura.id, { usar_fecha_hoy: usarFechaHoy })
    emit('aprobada', res.data.data, res.data.verifactu)
  } catch (err) {
    const datos = err?.response?.data
    if (err?.response?.status === 422 && datos?.message) {
      error.value = datos.message
      puedeUsarHoy.value = !!datos.puede_usar_hoy
    }
  }
  cargando.value = false
}

function cancelar() {
  if (!cargando.value) emit('cerrar')
}
</script>
