<!--
  Onfactu v.1.18.0 — Crear una factura rectificativa. Sustituye a la
  confirmación simple de antes para pedir el motivo, que es obligatorio
  (el reglamento de facturación pide que conste la causa) y sale en el PDF.
  Rectifica siempre la factura entera. Reglas: RectifyInvoiceController.
-->
<template>
  <BaseModal :show="show" @close="cerrar">
    <template #header>
      <span>{{ $t('invoices.confirm_rectify_title') }} · {{ factura?.invoice_number }}</span>
    </template>

    <div class="p-6 space-y-4 text-sm">
      <p class="text-gray-600">
        {{ factura?.is_rectificative
          ? $t('invoices.confirm_rectify_rectificativa', { number: factura?.invoice_number, original: factura?.rectified_invoice_number })
          : $t('invoices.confirm_rectify', { number: factura?.invoice_number }) }}
      </p>

      <BaseInputGroup :label="$t('invoices.rectify_reason')" :error="error" required>
        <BaseTextarea
          v-model="motivo"
          rows="3"
          :placeholder="$t('invoices.rectify_reason_placeholder')"
          maxlength="500"
        />
      </BaseInputGroup>
    </div>

    <template #footer>
      <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200">
        <BaseButton variant="white" @click="cerrar">{{ $t('general.cancel') }}</BaseButton>
        <BaseButton variant="danger" :loading="enviando" :disabled="!motivo.trim()" @click="crear">
          {{ $t('invoices.create_rectificative') }}
        </BaseButton>
      </div>
    </template>
  </BaseModal>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useInvoiceStore } from '@/scripts/admin/stores/invoice'

const props = defineProps({
  show: { type: Boolean, default: false },
  factura: { type: Object, default: null },
})
const emit = defineEmits(['close', 'creada'])
const invoiceStore = useInvoiceStore()

const motivo = ref('')
const error = ref('')
const enviando = ref(false)

watch(() => props.show, (v) => {
  if (v) {
    motivo.value = ''
    error.value = ''
  }
})

function cerrar() {
  if (!enviando.value) emit('close')
}

async function crear() {
  if (!motivo.value.trim()) return
  enviando.value = true
  error.value = ''
  try {
    const res = await invoiceStore.rectifyInvoice(props.factura.id, motivo.value.trim())
    emit('creada', res.data.data)
  } catch (e) {
    error.value = e?.response?.data?.errors?.motivo?.[0] || ''
  } finally {
    enviando.value = false
  }
}
</script>
