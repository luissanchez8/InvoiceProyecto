<template>
  <BaseDropdownItem @click="compartir">
    <BaseIcon
      :name="ocupado ? 'ArrowPathIcon' : 'ShareIcon'"
      class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      :class="ocupado ? 'animate-spin' : ''"
    />
    {{ $t('general.share') }}
  </BaseDropdownItem>
</template>

<script setup>
/*
  Onfactu — Compartir el PDF de un documento, como opción del menú de acciones.

  Hasta ahora no había forma de compartir una factura: el único acceso al PDF
  era abrirlo en una pestaña, y en móvil el navegador ni siquiera muestra su
  barra de descarga, así que el usuario se quedaba sin salida.

  Cadena de intentos, de mejor a peor:

    1. Compartir el FICHERO (navigator.share con files). Es lo que abre el menú
       del sistema en móvil: WhatsApp, correo, Drive... Es lo que hace falta de
       verdad, porque manda la factura al destinatario como adjunto.

    2. Compartir el ENLACE, si el navegador soporta share pero no ficheros.

    3. Copiar el enlace al portapapeles, para escritorio sin Web Share API.

  Se prefiere mandar el fichero antes que el enlace porque la URL del PDF es
  pública (lleva el unique_hash) y no caduca: mejor no ir repartiéndola.
*/
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useNotificationStore } from '@/scripts/stores/notification'

const props = defineProps({
  // URL del PDF. Puede ser relativa: se resuelve a absoluta.
  url: {
    type: String,
    required: true,
  },
  // Nombre del fichero al compartirlo, sin extensión. Ej: "FAC-000123"
  nombre: {
    type: String,
    default: 'documento',
  },
})

const { t } = useI18n()
const notificationStore = useNotificationStore()
const ocupado = ref(false)

function urlAbsoluta() {
  try {
    return new URL(props.url, window.location.origin).href
  } catch (e) {
    return props.url
  }
}

function aviso(tipo, mensaje) {
  notificationStore.showNotification({ type: tipo, message: mensaje })
}

async function compartir() {
  if (ocupado.value) return

  const url = urlAbsoluta()
  const nombreFichero = `${props.nombre || 'documento'}.pdf`

  ocupado.value = true

  try {
    // ── 1. Compartir el fichero ──
    if (navigator.canShare) {
      try {
        const respuesta = await fetch(url, { credentials: 'include' })
        if (respuesta.ok) {
          const blob = await respuesta.blob()
          const fichero = new File([blob], nombreFichero, { type: 'application/pdf' })

          if (navigator.canShare({ files: [fichero] })) {
            await navigator.share({ files: [fichero], title: nombreFichero })
            return
          }
        }
      } catch (e) {
        // Si el usuario cancela el diálogo no es un error: no seguimos.
        if (e && e.name === 'AbortError') return
        // Cualquier otro fallo (CORS, memoria, PDF pesado) cae al enlace.
      }
    }

    // ── 2. Compartir el enlace ──
    if (navigator.share) {
      try {
        await navigator.share({ title: nombreFichero, url })
        return
      } catch (e) {
        if (e && e.name === 'AbortError') return
      }
    }

    // ── 3. Copiar al portapapeles ──
    await copiarEnlace(url)
  } finally {
    ocupado.value = false
  }
}

async function copiarEnlace(url) {
  try {
    await navigator.clipboard.writeText(url)
    aviso('success', t('general.link_copied'))
    return
  } catch (e) {
    // Sin permiso de portapapeles (o sin HTTPS): método de respaldo
  }

  try {
    const campo = document.createElement('textarea')
    campo.value = url
    campo.setAttribute('readonly', '')
    campo.style.position = 'absolute'
    campo.style.left = '-9999px'
    document.body.appendChild(campo)
    campo.select()
    document.execCommand('copy')
    document.body.removeChild(campo)
    aviso('success', t('general.link_copied'))
  } catch (e) {
    aviso('error', t('general.share_failed'))
  }
}
</script>
