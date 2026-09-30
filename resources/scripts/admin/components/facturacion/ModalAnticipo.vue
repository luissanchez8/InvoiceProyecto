<!--
  Onfactu v.1.17.0 — Facturar un anticipo de un presupuesto o una proforma.
  Por porcentaje del total o por importe (con impuestos). Crea una factura en
  borrador y la abre. Reglas y cálculo: App\Services\Facturacion\FacturarAnticipo.
-->
<template>
  <BaseModal :show="show" @close="$emit('close')">
    <template #header>
      <span>{{ $t('facturacion.anticipo') }} · {{ numero }}</span>
    </template>

    <div class="p-6 space-y-4 text-sm">
      <p class="text-gray-600">{{ $t('facturacion.anticipo_ayuda') }}</p>

      <div class="flex gap-6">
        <BaseRadio id="anticipo-porcentaje" v-model="modo" value="porcentaje" name="modo-anticipo" :label="$t('facturacion.porcentaje')" />
        <BaseRadio id="anticipo-importe" v-model="modo" value="importe" name="modo-anticipo" :label="$t('facturacion.importe')" />
      </div>

      <BaseInputGroup :label="modo === 'porcentaje' ? '%' : $t('facturacion.importe')" :error="error">
        <BaseInput v-model="valor" type="number" step="0.01" min="0" />
      </BaseInputGroup>

      <div class="space-y-1 text-gray-600">
        <div class="flex justify-between">
          <span>{{ $t('facturacion.total_documento') }}</span>
          <BaseFormatMoney :amount="doc.total" :currency="moneda" />
        </div>
        <div v-if="anticipado > 0" class="flex justify-between">
          <span>{{ $t('facturacion.ya_anticipado') }}</span>
          <BaseFormatMoney :amount="anticipado" :currency="moneda" />
        </div>
        <div v-if="importe > 0" class="flex justify-between pt-2 font-medium text-gray-900 border-t border-gray-100">
          <span>{{ $t('facturacion.resultado') }}</span>
          <BaseFormatMoney :amount="importe" :currency="moneda" />
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200">
        <BaseButton variant="white" @click="$emit('close')">{{ $t('general.cancel') }}</BaseButton>
        <BaseButton variant="primary" :loading="enviando" :disabled="importe <= 0" @click="crear">
          {{ $t('facturacion.crear_factura') }}
        </BaseButton>
      </div>
    </template>
  </BaseModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import axios from 'axios'
import { useNotificationStore } from '@/scripts/stores/notification'

const props = defineProps({
  show: { type: Boolean, default: false },
  tipo: { type: String, required: true },     // estimate | proforma
  doc: { type: Object, required: true },
  numero: { type: String, default: '' },
})
const emit = defineEmits(['close'])
const router = useRouter()
const { t } = useI18n()
const avisos = useNotificationStore()

const modo = ref('porcentaje')
const valor = ref('')
const error = ref('')
const enviando = ref(false)

const moneda = computed(() => props.doc?.currency || props.doc?.customer?.currency)
const anticipado = computed(() => props.doc?.facturacion?.anticipado || 0)

// Aproximado: el servidor lo recalcula línea a línea y puede variar en céntimos
const importe = computed(() => {
  const v = parseFloat(valor.value)
  if (!v || v <= 0) return 0
  return modo.value === 'porcentaje' ? Math.round((props.doc.total * v) / 100) : Math.round(v * 100)
})

watch(() => props.show, (v) => { if (v) { valor.value = ''; error.value = ''; modo.value = 'porcentaje' } })

async function crear() {
  enviando.value = true
  error.value = ''
  try {
    const { data } = await axios.post('/api/v1/facturacion/anticipo', {
      tipo: props.tipo, id: props.doc.id, modo: modo.value, valor: parseFloat(valor.value),
    })
    avisos.showNotification({ type: 'success', message: t('facturacion.factura_creada') })
    emit('close')
    router.push(`/admin/invoices/${data.data.id}/view`)
  } catch (e) {
    error.value = e.response?.data?.message || ''
  } finally {
    enviando.value = false
  }
}
</script>
