<!--
  Onfactu v.1.15.0 — Tipos de IVA de un gasto, con el mismo aspecto que la
  tabla de artículos de las facturas: una fila por tipo (un ticket puede
  llevar 10 % y 21 %), con la base y el total, y cualquiera de los dos calcula
  el otro. Debajo de cada tipo, la casilla de no deducible.

  El estado y el cálculo están en composables/useDesgloseIva.js, que comparte
  con el cuadro de totales (TotalesGasto.vue).
-->
<template>
  <div>
    <table class="min-w-full text-center">
      <colgroup>
        <col style="width: 44%; min-width: 260px" />
        <col style="width: 20%; min-width: 140px" />
        <col style="width: 14%; min-width: 100px" />
        <col style="width: 22%; min-width: 150px" />
      </colgroup>
      <thead class="bg-white border border-gray-200 border-solid">
        <tr>
          <th :class="[TH, 'text-left']"><span class="pl-1">{{ $t('gastos_iva.tipo') }}</span></th>
          <th :class="[TH, 'text-left']">{{ $t('gastos_iva.base') }}</th>
          <th :class="[TH, 'text-right']">{{ $t('gastos_iva.iva') }}</th>
          <th :class="[TH, 'text-right']"><span class="pr-10">{{ $t('gastos_iva.total_linea') }}</span></th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(fila, i) in desglose.filas"
          :key="fila.id"
          class="bg-white border border-gray-200 border-solid"
        >
          <td class="px-5 py-4 text-left align-top">
            <BaseMultiselect
              v-model="fila.tipo"
              :options="desglose.tipos"
              value-prop="clave"
              label="nombre"
              track-by="nombre"
              :can-deselect="false"
              :can-clear="false"
              @update:modelValue="desglose.volcar()"
            />
            <BaseCheckbox
              v-model="fila.noDeducible"
              class="mt-2"
              :label="$t('gastos_iva.no_deducible')"
              @change="desglose.volcar()"
            />
          </td>
          <td class="px-5 py-4 text-left align-top">
            <BaseMoney
              :model-value="fila.base / 100"
              :currency="currency"
              @update:modelValue="(v) => desglose.cambiarBase(i, v)"
            />
          </td>
          <td class="px-5 py-4 text-right align-top">
            <div class="flex flex-col items-end justify-center h-10 text-sm text-gray-700">
              <BaseFormatMoney :amount="desglose.cuota(fila)" :currency="currency" />
              <span v-if="desglose.esAutoliquidacion(fila.tipo)" class="text-xs text-gray-400">
                {{ $t('gastos_iva.no_se_paga') }}
              </span>
            </div>
          </td>
          <td class="px-5 py-4 text-right align-top">
            <div class="flex items-center">
              <BaseMoney
                :model-value="desglose.totalFila(fila) / 100"
                :currency="currency"
                input-class="font-base block w-full sm:text-sm border-gray-200 rounded-md text-black text-right"
                @update:modelValue="(v) => desglose.cambiarTotal(i, v)"
              />
              <div class="flex items-center justify-center w-6 h-10 ml-3">
                <BaseIcon
                  v-if="desglose.filas.length > 1"
                  class="h-5 text-gray-700 cursor-pointer"
                  name="TrashIcon"
                  @click="desglose.quitar(i)"
                />
              </div>
            </div>
          </td>
        </tr>
      </tbody>
    </table>

    <div
      v-if="desglose.filas.length < 10"
      class="flex items-center justify-center w-full px-6 py-3 text-base bg-white border border-t-0 border-gray-200 border-solid cursor-pointer text-primary-400 hover:bg-primary-100"
      @click="desglose.anadir()"
    >
      <BaseIcon name="PlusCircleIcon" class="mr-2" />
      {{ $t('gastos_iva.anadir_tipo') }}
    </div>
  </div>
</template>

<script setup>
defineProps({
  desglose: { type: Object, required: true },
  currency: { type: Object, default: null },
})

const TH = 'px-5 py-3 text-sm not-italic font-medium leading-5 text-gray-700 border-t border-b border-gray-200 border-solid'
</script>
