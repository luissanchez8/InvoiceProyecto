<!--
  Onfactu v.1.18.0 — Botón "Exportar" de las listas de facturas y gastos.
  Descarga lo que se ve en la lista, con sus filtros, en Excel o en PDF.
  Servidor: ExportarListadoController.
-->
<template>
  <BaseDropdown class="ml-4" wrapper-class="flex">
    <template #activator>
      <BaseButton variant="primary-outline" :loading="cargando">
        {{ $t('exportar.boton') }}
        <template #right="slotProps">
          <BaseIcon name="ArrowDownTrayIcon" :class="slotProps.class" />
        </template>
      </BaseButton>
    </template>

    <BaseDropdownItem @click="descargar('excel')">
      <BaseIcon name="TableCellsIcon" class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500" />
      {{ $t('exportar.excel') }}
    </BaseDropdownItem>
    <BaseDropdownItem @click="descargar('pdf')">
      <BaseIcon name="DocumentTextIcon" class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500" />
      {{ $t('exportar.pdf') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  tipo: { type: String, required: true },          // facturas | gastos
  filtros: { type: Object, default: () => ({}) },
})
const cargando = ref(false)
const avisos = useNotificationStore()
const { t } = useI18n()

async function descargar(formato) {
  cargando.value = true
  try {
    const params = Object.fromEntries(Object.entries(props.filtros).filter(([, v]) => v !== '' && v !== null && v !== undefined))
    const res = await axios.get(`/api/v1/exportar/${props.tipo}/${formato}`, { params, responseType: 'blob' })
    const nombre = (res.headers['content-disposition'] || '').match(/filename="?([^"]+)"?/)?.[1]
      || `${props.tipo}.${formato === 'excel' ? 'csv' : 'pdf'}`
    const enlace = document.createElement('a')
    enlace.href = URL.createObjectURL(res.data)
    enlace.download = nombre
    document.body.appendChild(enlace)
    enlace.click()
    enlace.remove()
    setTimeout(() => URL.revokeObjectURL(enlace.href), 10000)
  } catch (e) {
    avisos.showNotification({ type: 'error', message: t('exportar.error') })
  } finally {
    cargando.value = false
  }
}
</script>
