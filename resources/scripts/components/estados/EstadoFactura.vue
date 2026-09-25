<!--
  Onfactu v.1.13.0 — Etiqueta del estado de una factura: Borrador o Aprobada,
  con su icono. Mismo estilo que las demás etiquetas de estado de Onfactu
  (BaseInvoiceStatusBadge). Mientras se envía a VeriFactu, lo indica.

  Los estados antiguos (Enviada, Vista, Completada) ya no existen tras la
  migración de la v.1.13; si apareciera alguno, se muestra como Aprobada.
-->
<template>
  <span
    class="inline-flex items-center gap-1.5 uppercase font-normal text-center whitespace-nowrap"
    :class="[pequena ? 'px-1 py-0.5 text-xs' : 'px-2 py-1 text-sm', colores]"
  >
    <IconoBorrador v-if="tipo === 'borrador'" :class="pequena ? 'w-3.5 h-3.5' : 'w-4 h-4'" class="shrink-0" />
    <IconoAprobada v-else-if="tipo === 'aprobada'" :class="pequena ? 'w-3.5 h-3.5' : 'w-4 h-4'" class="shrink-0" />
    {{ texto }}
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import IconoAprobada from '@/scripts/components/icons/estados/IconoAprobada.vue'
import IconoBorrador from '@/scripts/components/icons/estados/IconoBorrador.vue'

const props = defineProps({
  status: { type: String, default: '' },
  verifactuStatus: { type: String, default: null },
  pequena: { type: Boolean, default: false },
})

const { t } = useI18n()

const tipo = computed(() => {
  if (props.verifactuStatus === 'PENDING') return 'verifactu'
  return props.status === 'DRAFT' ? 'borrador' : 'aprobada'
})

const colores = computed(() => ({
  borrador: 'bg-orange-300 bg-opacity-30 text-orange-900',
  aprobada: 'bg-green-500 bg-opacity-25 text-green-900',
  verifactu: 'bg-amber-400 bg-opacity-30 text-amber-900 animate-pulse',
}[tipo.value]))

const texto = computed(() => ({
  borrador: t('estados.borrador'),
  aprobada: t('estados.aprobada'),
  verifactu: t('estados.pendiente_verifactu'),
}[tipo.value]))
</script>
