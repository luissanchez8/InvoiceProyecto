<!--
  Onfactu v.1.18.0 — Ventana de novedades. Sale sola la primera vez que el
  usuario entra después de una versión con novedades, y se vuelve a abrir con
  el botón de la barra de arriba (BotonNovedades). Enseña la última versión;
  las anteriores, detrás de "Ver más".
-->
<template>
  <!-- Ventana propia (no BaseModal): BaseModal toma el ancho del almacén de
       modales compartido y salía a pantalla completa -->
  <Teleport to="body">
    <div
      v-if="store.abierta"
      class="fixed inset-0 z-40 flex items-center justify-center p-4 bg-gray-500/75"
      @click.self="store.cerrar()"
    >
      <div class="w-full max-w-lg overflow-hidden bg-white rounded-lg shadow-xl" role="dialog" aria-modal="true">
        <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-200">
          <span class="flex items-center justify-center w-9 h-9 rounded-full bg-[#38d587]/15">
            <BaseIcon name="SparklesIcon" class="w-5 h-5 text-[#1a6b3a]" />
          </span>
          <h3 class="text-lg font-medium text-gray-900">{{ $t('novedades.titulo') }}</h3>
        </div>

        <div class="px-6 py-5 space-y-6 overflow-y-auto text-sm max-h-[65vh]">
          <section v-for="n in visibles" :key="n.version">
            <p class="mb-3 text-xs font-medium tracking-wide text-gray-400 uppercase">{{ fecha(n.fecha) }}</p>
            <ul class="space-y-3">
              <li v-for="(p, i) in n.puntos" :key="i" class="flex gap-3">
                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-[#38d587]" />
                <div>
                  <p class="font-medium text-gray-900">{{ p.titulo }}</p>
                  <p class="text-gray-600">
                    {{ p.texto }}
                    <router-link
                      v-if="p.enlace"
                      :to="p.enlace"
                      class="ml-1 font-medium whitespace-nowrap text-primary-500"
                      @click="store.cerrar()"
                    >{{ $t('novedades.ir') }} &rarr;</router-link>
                  </p>
                </div>
              </li>
            </ul>
          </section>

          <button
            v-if="!verTodo && anteriores.length"
            type="button"
            class="text-sm font-medium text-primary-500"
            @click="verTodo = true"
          >{{ $t('novedades.ver_mas') }}</button>
        </div>

        <div class="flex justify-end px-6 py-4 border-t border-gray-200">
          <BaseButton variant="primary" @click="store.cerrar()">{{ $t('novedades.entendido') }}</BaseButton>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch, onMounted, onUnmounted } from 'vue'
import { useNovedadesStore } from '@/scripts/admin/stores/novedades'

const store = useNovedadesStore()
const verTodo = ref(false)

function alPulsar(e) {
  if (e.key === 'Escape' && store.abierta) store.cerrar()
}

onMounted(() => {
  if (!store.cargada) store.cargar()
  window.addEventListener('keydown', alPulsar)
})
onUnmounted(() => window.removeEventListener('keydown', alPulsar))

watch(() => store.abierta, (v) => { if (v) verTodo.value = false })

// Solo la última versión; las anteriores (vistas o no), detrás de "Ver más"
const principales = computed(() => store.lista.slice(0, 1))
const anteriores = computed(() => store.lista.filter((n) => !principales.value.includes(n)))
const visibles = computed(() => (verTodo.value ? store.lista : principales.value))

function fecha(f) {
  return new Date(f + 'T12:00:00').toLocaleDateString('es-ES', { day: 'numeric', month: 'long', year: 'numeric' })
}
</script>
