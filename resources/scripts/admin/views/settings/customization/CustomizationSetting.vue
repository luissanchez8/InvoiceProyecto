<!--
  Ajustes → Personalización

  Sistema de tabs manual (sin BaseTabGroup/Headless UI) para evitar el bug
  "Maximum recursive updates" que ocurre al mezclar tabs con componentes
  que mutan estado reactivo al montarse.

  Tabs en orden: Facturas, Presupuestos, Facturas Proforma, Albaranes, Pagos, Artículos.
  Cada contenido se renderiza con v-if para cargar solo el tab activo.
-->
<template>
  <div class="relative">
    <BaseCard container-class="px-4 py-5 sm:px-8 sm:py-2">
      <!-- Barra de tabs manual -->
      <div class="flex border-b border-gray-200 overflow-x-auto">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          :class="[
            'px-8 py-2 text-sm leading-5 font-medium border-b-2 mt-4 focus:outline-none whitespace-nowrap',
            activeTab === tab.id
              ? 'border-primary-400 text-black font-medium'
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
          ]"
          @click="activeTab = tab.id"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- Contenido del tab activo -->
      <div class="py-4 mt-px">
        <InvoicesTab v-if="activeTab === 'invoices'" />
        <EstimatesTab v-if="activeTab === 'estimates'" />
        <ProformaInvoicesTab v-if="activeTab === 'proforma'" />
        <DeliveryNotesTab v-if="activeTab === 'delivery'" />
        <PaymentsTab v-if="activeTab === 'payments'" />
        <ItemsTab v-if="activeTab === 'items'" />
      </div>
    </BaseCard>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'

// Todos los tabs de contenido
import InvoicesTab from '@/scripts/admin/views/settings/customization/invoices/InvoicesTab.vue'
import EstimatesTab from '@/scripts/admin/views/settings/customization/estimates/EstimatesTab.vue'
import PaymentsTab from '@/scripts/admin/views/settings/customization/payments/PaymentsTab.vue'
import ItemsTab from '@/scripts/admin/views/settings/customization/items/ItemsTab.vue'
import ProformaInvoicesTab from '@/scripts/admin/views/settings/customization/proforma-invoices/ProformaInvoicesTab.vue'
import DeliveryNotesTab from '@/scripts/admin/views/settings/customization/delivery-notes/DeliveryNotesTab.vue'

const { t } = useI18n()

// Tab activo por defecto: Facturas
const activeTab = ref('invoices')

// Definición de tabs en el orden solicitado
const tabs = computed(() => [
  { id: 'invoices', label: t('settings.customization.invoices.title') },
  { id: 'estimates', label: t('settings.customization.estimates.title') },
  { id: 'proforma', label: t('proforma_invoices') },
  { id: 'delivery', label: t('delivery_notes') },
  { id: 'payments', label: t('settings.customization.payments.title') },
  { id: 'items', label: t('settings.customization.items.title') },
])
</script>
