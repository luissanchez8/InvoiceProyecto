<!--
  Onfactu v.1.18.0 — Ventana de novedades. Sale sola la primera vez que el
  usuario entra después de una versión con novedades, y se vuelve a abrir con
  el botón de la barra de arriba (BotonNovedades).

  Tarjeta pequeña y oscura, con una novedad por diapositiva: se pasa con las
  flechas, los puntos, deslizando el dedo o con las teclas de flecha. Arriba,
  una pestaña por versión (la última, abierta). Contenido: config/novedades.php.
-->
<template>
  <Teleport to="body">
    <transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-100 ease-in"
      leave-to-class="opacity-0"
    >
      <div
        v-if="store.abierta && version"
        class="fixed inset-0 z-40 flex items-center justify-center p-4 bg-[#070322]/40"
        @click.self="store.cerrar()"
      >
        <div
          class="relative w-full max-w-[340px] overflow-hidden rounded-2xl bg-[#070322] text-white shadow-2xl select-none"
          role="dialog"
          aria-modal="true"
          :aria-label="$t('novedades.titulo')"
          @touchstart.passive="tocarInicio"
          @touchend.passive="tocarFin"
        >
          <!-- Cabecera: título, versiones y cerrar -->
          <div class="flex items-center justify-between px-4 pt-3">
            <div class="flex items-center gap-2 text-xs font-medium text-[#38d587]">
              <BaseIcon name="SparklesIcon" class="w-4 h-4" />
              {{ $t('novedades.titulo') }}
            </div>
            <button
              type="button"
              class="p-1 -mr-1 text-white/50 hover:text-white"
              :aria-label="$t('novedades.cerrar')"
              @click="store.cerrar()"
            >
              <BaseIcon name="XMarkIcon" class="w-4 h-4" />
            </button>
          </div>

          <div v-if="store.lista.length > 1" class="flex gap-1 px-4 mt-2">
            <button
              v-for="(n, i) in store.lista"
              :key="n.version"
              type="button"
              class="px-2 py-0.5 text-[11px] rounded-full transition"
              :class="i === iVersion ? 'bg-white/15 text-white' : 'text-white/50 hover:text-white/80'"
              @click="elegirVersion(i)"
            >{{ fecha(n.fecha) }}</button>
          </div>

          <!-- Diapositivas -->
          <div class="overflow-hidden">
            <div
              class="flex transition-transform duration-300 ease-out"
              :style="{ transform: `translateX(-${paso * 100}%)` }"
            >
              <div
                v-for="(p, i) in version.puntos"
                :key="i"
                class="w-full shrink-0 px-4 pt-3 pb-2 min-h-[118px]"
              >
                <p class="text-sm font-medium leading-snug">{{ p.titulo }}</p>
                <p class="mt-1 text-xs leading-relaxed text-white/70">{{ p.texto }}</p>
                <router-link
                  v-if="p.enlace"
                  :to="p.enlace"
                  class="inline-block mt-2 text-xs font-medium text-[#38d587] hover:underline"
                  @click="store.cerrar()"
                >{{ $t('novedades.ir') }} &rarr;</router-link>
              </div>
            </div>
          </div>

          <!-- Pie: puntos y avanzar -->
          <div class="flex items-center justify-between px-4 pb-3">
            <div class="flex items-center gap-1.5">
              <button
                v-for="(p, i) in version.puntos"
                :key="i"
                type="button"
                class="h-1.5 rounded-full transition-all"
                :class="i === paso ? 'w-4 bg-[#38d587]' : 'w-1.5 bg-white/30 hover:bg-white/50'"
                :aria-label="`${i + 1}`"
                @click="paso = i"
              />
            </div>
            <div class="flex items-center gap-1">
              <button
                v-if="paso > 0"
                type="button"
                class="p-1 text-white/60 hover:text-white"
                @click="anterior"
              >
                <BaseIcon name="ChevronLeftIcon" class="w-4 h-4" />
              </button>
              <button
                type="button"
                class="px-3 py-1 text-xs font-medium rounded-full bg-[#38d587] text-[#070322] hover:bg-[#2fc57c]"
                @click="siguiente"
              >{{ esUltimo ? $t('novedades.entendido') : $t('novedades.siguiente') }}</button>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch, onMounted, onUnmounted } from 'vue'
import { useNovedadesStore } from '@/scripts/admin/stores/novedades'

const store = useNovedadesStore()
const iVersion = ref(0)
const paso = ref(0)
let xInicio = null

const version = computed(() => store.lista[iVersion.value])
const esUltimo = computed(() => paso.value >= (version.value?.puntos.length || 1) - 1)

watch(() => store.abierta, (v) => {
  if (v) {
    iVersion.value = 0
    paso.value = 0
  }
})

function elegirVersion(i) {
  iVersion.value = i
  paso.value = 0
}

function siguiente() {
  if (esUltimo.value) store.cerrar()
  else paso.value++
}

function anterior() {
  if (paso.value > 0) paso.value--
}

function tocarInicio(e) {
  xInicio = e.changedTouches[0].clientX
}

function tocarFin(e) {
  if (xInicio === null) return
  const dx = e.changedTouches[0].clientX - xInicio
  xInicio = null
  if (dx < -40 && !esUltimo.value) paso.value++
  else if (dx > 40) anterior()
}

function alPulsar(e) {
  if (!store.abierta) return
  if (e.key === 'Escape') store.cerrar()
  else if (e.key === 'ArrowRight' && !esUltimo.value) paso.value++
  else if (e.key === 'ArrowLeft') anterior()
}

function fecha(f) {
  return new Date(f + 'T12:00:00').toLocaleDateString('es-ES', { day: 'numeric', month: 'short' })
}

onMounted(() => {
  if (!store.cargada) store.cargar()
  window.addEventListener('keydown', alPulsar)
})
onUnmounted(() => window.removeEventListener('keydown', alPulsar))
</script>
