/**
 * Store Pinia: proforma-invoice
 *
 * Gestiona el estado de las facturas proforma en el frontend.
 * Replica COMPLETAMENTE el patrón de invoice.js:
 * - CRUD, estados, conversión a factura
 * - Getters de cálculo (subtotal, impuestos, descuentos, total)
 * - Gestión de ítems (añadir, actualizar, eliminar líneas)
 * - Carga de configuración inicial (fetchInitialSettings)
 * - Templates, notas, clientes
 */

import axios from 'axios'
import moment from 'moment'
import Guid from 'guid'
import _ from 'lodash'
import { defineStore } from 'pinia'
import { useRoute } from 'vue-router'
import { handleError } from '@/scripts/helpers/error-handling'
import proformaInvoiceItemStub from '../stub/proforma-invoice-item'
import taxStub from '../stub/tax'
import proformaInvoiceStub from '../stub/proforma-invoice'

import { useNotificationStore } from '@/scripts/stores/notification'
import { useCustomerStore } from './customer'
import { useTaxTypeStore } from './tax-type'
import { useCompanyStore } from './company'
import { useItemStore } from './item'
import { useUserStore } from './user'
import { useNotesStore } from './note'

export const useProformaInvoiceStore = (useWindow = false) => {
  const defineStoreFunc = useWindow ? window.pinia.defineStore : defineStore
  const { global } = window.i18n
  const notificationStore = useNotificationStore()

  return defineStoreFunc({
    id: 'proformaInvoice',

    state: () => ({
      templates: [],
      proformaInvoices: [],
      selectedProformaInvoices: [],
      selectAllField: false,
      proformaInvoiceTotalCount: 0,
      showExchangeRate: false,
      isFetchingInitialSettings: false,
      isFetchingProformaInvoice: false,
      // Onfactu — numeración diferida:
      suggestedProformaInvoiceNumber: null,
      suggestedProformaInvoiceNumberIsSkipped: false,
      naturalNextProformaInvoiceNumber: null,
      newProformaInvoice: { ...proformaInvoiceStub() },
    }),

    // =================================================================
    // GETTERS — Cálculos de subtotal, impuestos, descuentos y total
    // Misma lógica que invoice.js pero sobre newProformaInvoice
    // =================================================================
    getters: {
      isEdit: (state) => !!state.newProformaInvoice.id,

      /** Suma de totales de todas las líneas */
      getSubTotal() {
        return this.newProformaInvoice.items.reduce((a, b) => a + b['total'], 0)
      },

      /** Suma de impuestos simples (no compuestos) a nivel de documento */
      getTotalSimpleTax() {
        return _.sumBy(this.newProformaInvoice.taxes, (tax) =>
          !tax.compound_tax ? tax.amount : 0
        )
      },

      /** Suma de impuestos compuestos a nivel de documento */
      getTotalCompoundTax() {
        return _.sumBy(this.newProformaInvoice.taxes, (tax) =>
          tax.compound_tax ? tax.amount : 0
        )
      },

      /** Total de impuestos (por documento o por ítem) */
      getTotalTax() {
        if (
          this.newProformaInvoice.tax_per_item === 'NO' ||
          this.newProformaInvoice.tax_per_item === null
        ) {
          return this.getTotalSimpleTax + this.getTotalCompoundTax
        }
        return _.sumBy(this.newProformaInvoice.items, (item) => item.tax)
      },

      /** Subtotal menos descuento global */
      getSubtotalWithDiscount() {
        return this.getSubTotal - this.newProformaInvoice.discount_val
      },

      /** Total final = subtotal - descuento + impuestos */
      getTotal() {
        return this.getSubtotalWithDiscount + this.getTotalTax
      },
    },

    actions: {
      // =================================================================
      // RESET Y CONFIGURACIÓN INICIAL
      // =================================================================

      resetCurrentProformaInvoice() {
        this.newProformaInvoice = { ...proformaInvoiceStub() }
      },

      /**
       * Carga configuración inicial para el formulario de crear/editar.
       * En modo crear: fecha actual, número siguiente, notas por defecto, templates.
       * En modo editar: carga los datos de la proforma existente.
       */
      async fetchProformaInvoiceInitialSettings(isEdit) {
        const companyStore = useCompanyStore()
        const customerStore = useCustomerStore()
        const itemStore = useItemStore()
        const taxTypeStore = useTaxTypeStore()
        const route = useRoute()
        const userStore = useUserStore()
        const notesStore = useNotesStore()

        this.isFetchingInitialSettings = true
        this.newProformaInvoice.selectedCurrency = companyStore.selectedCompanyCurrency

        // Si viene un customer por query param, precargarlo
        if (route.query.customer) {
          let response = await customerStore.fetchCustomer(route.query.customer)
          this.newProformaInvoice.customer = response.data.data
          this.newProformaInvoice.customer_id = response.data.data.id
        }

        let editActions = []

        if (!isEdit) {
          // Modo CREAR: cargar valores por defecto
          await notesStore.fetchNotes()
          this.newProformaInvoice.notes = notesStore.getDefaultNoteForType('Invoice')?.notes
          this.newProformaInvoice.tax_per_item = companyStore.selectedCompanySettings.tax_per_item
          this.newProformaInvoice.discount_per_item = companyStore.selectedCompanySettings.discount_per_item
          this.newProformaInvoice.proforma_invoice_date = moment().format('YYYY-MM-DD')
          // Fecha de validez = hoy + 30 días por defecto
          this.newProformaInvoice.expiry_date = moment().add(30, 'days').format('YYYY-MM-DD')
        } else {
          // Modo EDITAR: cargar datos existentes
          editActions = [this.fetchProformaInvoice(route.params.id)]
        }

        // Cargar items, templates, siguiente número y tipos de impuesto en paralelo
        Promise.all([
          itemStore.fetchItems({ filter: {}, orderByField: '', orderBy: '' }),
          this.resetSelectedNote(),
          this.fetchInvoiceTemplates(),
          // Onfactu: en edit pasamos model_id para excluir el propio documento
          // de la comprobación de "ocupado" en la sugerencia.
          this.getNextNumber(isEdit ? { model_id: route.params.id } : undefined),
          taxTypeStore.fetchTaxTypes({ limit: 'all' }),
          ...editActions,
        ]).then(async ([res1, res2, res3, res4, res5, res6]) => {
          if (!isEdit) {
            // Onfactu — numeración diferida: pre-rellenamos + guardamos sugerencia + isSkipped + naturalNext
            if (res4.data) {
              this.newProformaInvoice.proforma_invoice_number = res4.data.nextNumber
              this.suggestedProformaInvoiceNumber = res4.data.nextNumber
              this.suggestedProformaInvoiceNumberIsSkipped = !!res4.data.isSkipped
              this.naturalNextProformaInvoiceNumber = res4.data.naturalNext || res4.data.nextNumber
            }
            // Asignar template por defecto
            if (res3.data && this.templates.length) {
              let defaultTpl = this.templates.find(t => t.name === 'invoice4')
              this.setTemplate(defaultTpl ? 'invoice4' : this.templates[0].name)
            }
          } else if (res6) {
            // En edición guardamos la sugerencia actual para comparar
            if (res4.data) {
              this.suggestedProformaInvoiceNumber = res4.data.nextNumber
              this.suggestedProformaInvoiceNumberIsSkipped = !!res4.data.isSkipped
              this.naturalNextProformaInvoiceNumber = res4.data.naturalNext || res4.data.nextNumber
            }
            // Poblar datos de la proforma
            this.setProformaInvoiceData(res6.data.data)
          }

          this.isFetchingInitialSettings = false
        })
      },

      // =================================================================
      // GESTIÓN DE DATOS DEL FORMULARIO
      // =================================================================

      /** Puebla el store con datos de una proforma existente (para edición) */
      setProformaInvoiceData(proformaInvoice) {
        Object.assign(this.newProformaInvoice, proformaInvoice)

        // Si tax_per_item, asegurar que cada ítem tiene al menos un tax stub
        if (this.newProformaInvoice.tax_per_item === 'YES') {
          this.newProformaInvoice.items.forEach((_i) => {
            if (_i.taxes && !_i.taxes.length)
              _i.taxes.push({ ...taxStub, id: Guid.raw() })
          })
        }

        // Convertir descuentos fijos de céntimos a unidades para el formulario
        if (this.newProformaInvoice.discount_per_item === 'YES') {
          this.newProformaInvoice.items.forEach((_i, index) => {
            if (_i.discount_type === 'fixed')
              this.newProformaInvoice.items[index].discount = _i.discount / 100
          })
        } else {
          if (this.newProformaInvoice.discount_type === 'fixed')
            this.newProformaInvoice.discount = this.newProformaInvoice.discount / 100
        }
      },

      /** Selecciona un cliente por ID (llamado desde BaseCustomerSelectPopup) */
      selectCustomer(id) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/customers/${id}`)
            .then((response) => {
              this.newProformaInvoice.customer = response.data.data
              this.newProformaInvoice.customer_id = response.data.data.id
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      // =================================================================
      // GESTIÓN DE ÍTEMS — Añadir, actualizar, eliminar líneas
      // =================================================================

      addItem() {
        this.newProformaInvoice.items.push({
          ...proformaInvoiceItemStub,
          id: Guid.raw(),
          taxes: [{ ...taxStub, id: Guid.raw() }],
        })
      },

      updateItem(data) {
        Object.assign(this.newProformaInvoice.items[data.index], { ...data })
      },

      removeItem(index) {
        this.newProformaInvoice.items.splice(index, 1)
      },

      deselectItem(index) {
        this.newProformaInvoice.items[index] = {
          ...proformaInvoiceItemStub,
          id: Guid.raw(),
          taxes: [{ ...taxStub, id: Guid.raw() }],
        }
      },

      // =================================================================
      // TEMPLATES Y NOTAS
      // =================================================================

      /** Carga las plantillas disponibles (reutiliza las de factura) */
      fetchInvoiceTemplates(params) {
        return new Promise((resolve, reject) => {
          axios
            .get('/api/v1/invoices/templates', { params })
            .then((response) => {
              this.templates = response.data.invoiceTemplates
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      setTemplate(data) {
        this.newProformaInvoice.template_name = data
      },

      selectNote(data) {
        this.newProformaInvoice.selectedNote = null
        this.newProformaInvoice.selectedNote = data
      },

      resetSelectedNote() {
        this.newProformaInvoice.selectedNote = null
      },

      resetSelectedCustomer() {
        this.newProformaInvoice.customer = null
        this.newProformaInvoice.customer_id = null
      },

      /** Obtiene el siguiente número de proforma */
      getNextNumber(params, setState = false) {
        return new Promise((resolve, reject) => {
          axios
            .get('/api/v1/next-number?key=proforma_invoice', { params })
            .then((response) => {
              if (setState) {
                this.newProformaInvoice.proforma_invoice_number = response.data.nextNumber
              }
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      // =================================================================
      // CRUD — API calls
      // =================================================================

      fetchProformaInvoices(params) {
        return new Promise((resolve, reject) => {
          axios
            .get('/api/v1/proforma-invoices', { params })
            .then((response) => {
              this.proformaInvoices = response.data.data
              this.proformaInvoiceTotalCount =
                response.data.proforma_invoice_total_count ?? response.data.total ?? 0
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchProformaInvoice(id) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/proforma-invoices/${id}`)
            .then((response) => {
              this.setProformaInvoiceData(response.data.data)
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      addProformaInvoice(data) {
        return new Promise((resolve, reject) => {
          axios
            .post('/api/v1/proforma-invoices', data)
            .then((response) => {
              notificationStore.showNotification({
                type: 'success',
                message: 'Factura proforma creada correctamente',
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      updateProformaInvoice(data) {
        return new Promise((resolve, reject) => {
          axios
            .put(`/api/v1/proforma-invoices/${data.id}`, data)
            .then((response) => {
              notificationStore.showNotification({
                type: 'success',
                message: 'Factura proforma actualizada',
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      deleteProformaInvoice(id) {
        return new Promise((resolve, reject) => {
          axios
            .post('/api/v1/proforma-invoices/delete', { ids: [id] })
            .then((response) => {
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      convertToInvoice(id) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/proforma-invoices/${id}/convert`)
            .then((response) => {
              notificationStore.showNotification({
                type: 'success',
                message: 'Proforma convertida a factura',
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      cloneProformaInvoice(data) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/proforma-invoices/${data.id}/clone`, data)
            .then((response) => {
              notificationStore.showNotification({
                type: 'success',
                message: 'Factura proforma clonada correctamente',
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      // Onfactu — convertir proforma a factura
      convertToInvoice(id) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/proforma-invoices/${id}/convert`)
            .then((response) => {
              resolve(response)
            })
            .catch((err) => {
              // No mostramos toast aquí — lo gestiona quien llama
              reject(err)
            })
        })
      },

      sendProformaInvoice(data) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/proforma-invoices/${data.id}/send`, data)
            .then((response) => {
              notificationStore.showNotification({
                type: 'success',
                message: 'Factura proforma enviada correctamente',
              })
              resolve(response)
            })
            .catch((err) => {
              // Onfactu — numeración diferida: 409 manejada por la view
              const status = err?.response?.status
              const errorCode = err?.response?.data?.error_code
              const isCollision = status === 409 && errorCode === 'number_collision'
              if (!isCollision) {
                handleError(err)
              }
              reject(err)
            })
        })
      },

      markAsSent(data) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/proforma-invoices/${data.id}/status`, { status: 'SENT' })
            .then((response) => { resolve(response) })
            .catch((err) => {
              const status = err?.response?.status
              const errorCode = err?.response?.data?.error_code
              const isCollision = status === 409 && errorCode === 'number_collision'
              if (!isCollision) {
                handleError(err)
              }
              reject(err)
            })
        })
      },
    },
  })()
}
