<!--
  Onfactu v.1.17.0 — Recuadro de facturación en la pantalla de un presupuesto,
  proforma o albarán: sus facturas (final y anticipos), lo pendiente, el
  presupuesto del que sale o lo que salió de él y, en los presupuestos, la
  respuesta del cliente desde el enlace del correo.
  No sale nada si no hay nada que contar. Datos: EstadoFacturacion::detalle.
-->
<template>
  <div v-if="hayAlgo" class="p-4 mt-6 space-y-3 text-sm bg-white border border-gray-200 rounded-md">
    <div v-if="respuesta" class="text-gray-700">
      <span :class="doc.status === 'REJECTED' ? 'text-red-700' : 'text-[#1a6b3a]'" class="font-medium">{{ respuesta }}</span>
      <span v-if="doc.respuesta_nombre" class="ml-2 text-gray-500">{{ $t('facturacion.respuesta_por', { nombre: doc.respuesta_nombre }) }}</span>
      <p v-if="doc.respuesta_comentario" class="mt-1 italic text-gray-600">"{{ doc.respuesta_comentario }}"</p>
    </div>

    <div v-if="f.estado !== 'PENDIENTE'" class="flex flex-wrap items-center gap-3">
      <EstadoFacturacionBadge :estado="f.estado" />
      <span v-if="f.bloqueado" class="text-gray-500">{{ $t('facturacion.bloqueado') }}</span>
    </div>

    <ul v-if="f.facturas?.length" class="divide-y divide-gray-100">
      <li v-for="fac in f.facturas" :key="fac.id" class="flex items-center justify-between py-1.5">
        <router-link :to="`/admin/invoices/${fac.id}/view`" class="font-medium text-primary-500">
          {{ $t(`facturacion.tipo_${fac.tipo}`) }} {{ fac.numero || $t('facturacion.sin_numero') }}
        </router-link>
        <span class="text-gray-500">{{ fac.fecha }}</span>
        <BaseFormatMoney :amount="fac.total" :currency="moneda" class="font-medium" />
      </li>
    </ul>

    <div v-if="f.anticipado > 0 && f.estado !== 'FACTURADO'" class="flex gap-6 text-gray-600">
      <span>{{ $t('facturacion.anticipado') }}: <BaseFormatMoney :amount="f.anticipado" :currency="moneda" /></span>
      <span>{{ $t('facturacion.pendiente') }}: <BaseFormatMoney :amount="f.pendiente" :currency="moneda" /></span>
    </div>

    <p v-if="f.origen" class="text-gray-600">
      <router-link :to="`/admin/estimates/${f.origen.id}/view`" class="text-primary-500">
        {{ $t('facturacion.origen', { numero: f.origen.numero }) }}
      </router-link>
    </p>

    <div v-if="f.derivados?.length" class="text-gray-600">
      {{ $t('facturacion.derivados') }}:
      <router-link
        v-for="(d, i) in f.derivados"
        :key="d.tipo + d.id"
        :to="rutaDerivado(d)"
        class="text-primary-500"
      >{{ d.numero }}{{ i < f.derivados.length - 1 ? ', ' : '' }}</router-link>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EstadoFacturacionBadge from './EstadoFacturacionBadge.vue'

const props = defineProps({
  doc: { type: Object, required: true },
})
const { t } = useI18n()

const f = computed(() => props.doc?.facturacion || { estado: 'PENDIENTE' })
const moneda = computed(() => props.doc?.currency || props.doc?.customer?.currency)

const respuesta = computed(() => {
  if (!props.doc?.respuesta_at || !['ACCEPTED', 'REJECTED'].includes(props.doc.status)) return ''
  const fecha = new Date(props.doc.respuesta_at).toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' })
  return t(`facturacion.respuesta_${props.doc.status}`, { fecha })
})

const hayAlgo = computed(() =>
  !!respuesta.value || f.value.estado !== 'PENDIENTE' || f.value.facturas?.length || f.value.origen || f.value.derivados?.length
)

function rutaDerivado(d) {
  return d.tipo === 'proforma' ? `/admin/proforma-invoices/${d.id}/view` : `/admin/delivery-notes/${d.id}/view`
}
</script>
