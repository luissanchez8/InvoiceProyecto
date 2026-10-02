// Onfactu v.1.18.0 — Ventana de novedades: qué hay y qué no ha visto el usuario.
// Servidor: NovedadesController. Contenido: config/novedades.php.
import axios from 'axios'
import { defineStore } from 'pinia'

export const useNovedadesStore = defineStore('novedades', {
  state: () => ({
    lista: [],
    sinVer: [],
    abierta: false,
    cargada: false,
  }),

  getters: {
    hayNuevas: (state) => state.sinVer.length > 0,
  },

  actions: {
    async cargar() {
      try {
        const { data } = await axios.get('/api/v1/novedades')
        this.lista = data.novedades || []
        this.sinVer = data.sin_ver || []
        this.cargada = true
        if (this.sinVer.length) this.abierta = true
      } catch (e) {
        // Sin novedades antes que un error en pantalla
      }
    },

    abrir() {
      this.abierta = true
    },

    async cerrar() {
      this.abierta = false
      if (!this.sinVer.length) return
      this.sinVer = []
      try {
        await axios.post('/api/v1/novedades/vistas')
      } catch (e) {
        // Si no se guarda, volverá a salir la próxima vez
      }
    },
  },
})
