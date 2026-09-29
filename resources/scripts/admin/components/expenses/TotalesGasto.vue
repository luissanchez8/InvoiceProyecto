<!--
  Onfactu v.1.15.0 — Cuadro de totales del gasto, con el mismo aspecto que el
  de las facturas: base, IVA, retención (que se elige aquí, como el descuento
  en las facturas) y total a pagar. Ver composables/useDesgloseIva.js.
-->
<template>
  <div class="px-5 py-4 bg-white border border-gray-200 border-solid rounded md:min-w-[390px] min-w-[300px]">
    <div class="flex items-center justify-between w-full py-1">
      <label :class="ETIQUETA">{{ $t('gastos_iva.base') }}</label>
      <span class="text-lg text-black"><BaseFormatMoney :amount="desglose.resumen.base" :currency="currency" /></span>
    </div>

    <div class="flex items-center justify-between w-full py-1">
      <label :class="ETIQUETA">{{ $t('gastos_iva.iva') }}</label>
      <span class="text-lg text-black"><BaseFormatMoney :amount="desglose.resumen.iva" :currency="currency" /></span>
    </div>

    <div v-if="desglose.resumen.autoliquidado" class="flex items-center justify-between w-full py-1">
      <label :class="ETIQUETA">{{ $t('gastos_iva.autoliquidado') }}</label>
      <span class="text-sm text-gray-500"><BaseFormatMoney :amount="desglose.resumen.autoliquidado" :currency="currency" /></span>
    </div>

    <div class="flex items-center justify-between w-full py-1">
      <label :class="ETIQUETA">{{ $t('gastos_iva.retencion') }}</label>
      <div class="flex items-center gap-3 ml-4">
        <div class="w-36">
          <BaseMultiselect
            v-model="desglose.gasto.retencion_porcentaje"
            :options="opciones"
            value-prop="valor"
            label="texto"
            track-by="texto"
            :can-deselect="false"
            :can-clear="false"
            @update:modelValue="desglose.volcar()"
          />
        </div>
        <span v-if="desglose.resumen.retencion" class="text-lg text-black whitespace-nowrap">
          - <BaseFormatMoney :amount="desglose.resumen.retencion" :currency="currency" />
        </span>
      </div>
    </div>

    <div class="flex items-center justify-between w-full pt-4 mt-4 border-t border-gray-200 border-solid">
      <label :class="ETIQUETA">{{ $t('gastos_iva.total_pagar') }}</label>
      <span class="text-lg font-semibold text-primary-400">
        <BaseFormatMoney :amount="desglose.resumen.total" :currency="currency" />
      </span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  desglose: { type: Object, required: true },
  currency: { type: Object, default: null },
})

const { t } = useI18n()
const ETIQUETA = 'text-sm font-semibold leading-5 text-gray-400 uppercase'

const opciones = computed(() =>
  props.desglose.retenciones.map((r) => ({ valor: r, texto: r === 0 ? t('gastos_iva.sin_retencion') : r + ' %' }))
)
</script>
