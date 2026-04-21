/**
 * Store Pinia: delivery-note
 *
 * Gestiona el estado de los albaranes en el frontend.
 * Replica COMPLETAMENTE el patrón de invoice.js:
 * - CRUD, estados
 * - Getters de cálculo (subtotal, impuestos, descuentos, total)
 * - Gestión de ítems
 * - Campo show_prices para controlar visibilidad de precios en PDF
 */

import axios from 'axios'
import moment from 'moment'
import Guid from 'guid'
import _ from 'lodash'
import { defineStore } from 'pinia'
import { useRoute } from 'vue-router'
import { handleError } from '@/scripts/helpers/error-handling'
import deliveryNoteItemStub from '../stub/delivery-note-item'
import taxStub from '../stub/tax'
import deliveryNoteStub from '../stub/delivery-note'

import { useNotificationStore } from '@/scripts/stores/notification'
import { useCustomerStore } from './customer'
import { useTaxTypeStore } from './tax-type'
import { useCompanyStore } from './company'
import { useItemStore } from './item'
import { useUserStore } from './user'
import { useNotesStore } from './note'

export const useDeliveryNoteStore = (useWindow = false) => {
  const defineStoreFunc = useWindow ? window.pinia.defineStore : defineStore
  const { global } = window.i18n
  const notificationStore = useNotificationStore()

  return defineStoreFunc({
    id: 'deliveryNote',

    state: () => ({
      templates: [],
      deliveryNotes: [],
      selectedDeliveryNotes: [],
      selectAllField: false,
      deliveryNoteTotalCount: 0,
      showExchangeRate: false,
      isFetchingInitialSettings: false,
      isFetchingDeliveryNote: false,
      // Onfactu — numeración diferida:
      suggestedDeliveryNoteNumber: null,
      newDeliveryNote: { ...deliveryNoteStub() },
    }),

    getters: {
      isEdit: (state) => !!state.newDeliveryNote.id,

      getSubTotal() {
        return this.newDeliveryNote.items.reduce((a, b) => a + b['total'], 0)
      },

      getTotalSimpleTax() {
        return _.sumBy(this.newDeliveryNote.taxes, (tax) =>
          !tax.compound_tax ? tax.amount : 0
        )
      },

      getTotalCompoundTax() {
        return _.sumBy(this.newDeliveryNote.taxes, (tax) =>
          tax.compound_tax ? tax.amount : 0
        )
      },

      getTotalTax() {
        if (
          this.newDeliveryNote.tax_per_item === 'NO' ||
          this.newDeliveryNote.tax_per_item === null
        ) {
          return this.getTotalSimpleTax + this.getTotalCompoundTax
        }
        return _.sumBy(this.newDeliveryNote.items, (item) => item.tax)
      },

      getSubtotalWithDiscount() {
        return this.getSubTotal - this.newDeliveryNote.discount_val
      },

      getTotal() {
        return this.getSubtotalWithDiscount + this.getTotalTax
      },
    },

    actions: {
      resetCurrentDeliveryNote() {
        this.newDeliveryNote = { ...deliveryNoteStub() }
      },

      /**
       * Carga configuración inicial para el formulario de crear/editar.
       */
      async fetchDeliveryNoteInitialSettings(isEdit) {
        const companyStore = useCompanyStore()
        const customerStore = useCustomerStore()
        const itemStore = useItemStore()
        const taxTypeStore = useTaxTypeStore()
        const route = useRoute()
        const userStore = useUserStore()
        const notesStore = useNotesStore()

        this.isFetchingInitialSettings = true
        this.newDeliveryNote.selectedCurrency = companyStore.selectedCompanyCurrency

        if (route.query.customer) {
          let response = await customerStore.fetchCustomer(route.query.customer)
          this.newDeliveryNote.customer = response.data.data
          this.newDeliveryNote.customer_id = response.data.data.id
        }

        let editActions = []

        if (!isEdit) {
          await notesStore.fetchNotes()
          this.newDeliveryNote.notes = notesStore.getDefaultNoteForType('Invoice')?.notes
          this.newDeliveryNote.tax_per_item = companyStore.selectedCompanySettings.tax_per_item
          this.newDeliveryNote.discount_per_item = companyStore.selectedCompanySettings.discount_per_item
          this.newDeliveryNote.delivery_note_date = moment().format('YYYY-MM-DD')
          this.newDeliveryNote.delivery_date = moment().add(3, 'days').format('YYYY-MM-DD')
        } else {
          editActions = [this.fetchDeliveryNote(route.params.id)]
        }

        Promise.all([
          itemStore.fetchItems({ filter: {}, orderByField: '', orderBy: '' }),
          this.resetSelectedNote(),
          this.fetchInvoiceTemplates(),
          this.getNextNumber(),
          taxTypeStore.fetchTaxTypes({ limit: 'all' }),
          ...editActions,
        ]).then(async ([res1, res2, res3, res4, res5, res6]) => {
          if (!isEdit) {
            // Onfactu — numeración diferida: pre-rellenamos + guardamos sugerencia
            if (res4.data) {
              this.newDeliveryNote.delivery_note_number = res4.data.nextNumber
              this.suggestedDeliveryNoteNumber = res4.data.nextNumber
            }
            if (res3.data && this.templates.length) {
              let defaultTpl = this.templates.find(t => t.name === 'invoice4')
              this.setTemplate(defaultTpl ? 'invoice4' : this.templates[0].name)
            }
          } else if (res6) {
            if (res4.data) {
              this.suggestedDeliveryNoteNumber = res4.data.nextNumber
            }
            this.setDeliveryNoteData(res6.data.data)
          }

          this.isFetchingInitialSettings = false
        })
      },

      setDeliveryNoteData(deliveryNote) {
        Object.assign(this.newDeliveryNote, deliveryNote)

        if (this.newDeliveryNote.tax_per_item === 'YES') {
          this.newDeliveryNote.items.forEach((_i) => {
            if (_i.taxes && !_i.taxes.length)
              _i.taxes.push({ ...taxStub, id: Guid.raw() })
          })
        }

        if (this.newDeliveryNote.discount_per_item === 'YES') {
          this.newDeliveryNote.items.forEach((_i, index) => {
            if (_i.discount_type === 'fixed')
              this.newDeliveryNote.items[index].discount = _i.discount / 100
          })
        } else {
          if (this.newDeliveryNote.discount_type === 'fixed')
            this.newDeliveryNote.discount = this.newDeliveryNote.discount / 100
        }
      },

      /** Selecciona un cliente por ID (llamado desde BaseCustomerSelectPopup) */
      selectCustomer(id) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/customers/${id}`)
            .then((response) => {
              this.newDeliveryNote.customer = response.data.data
              this.newDeliveryNote.customer_id = response.data.data.id
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      // Gestión de ítems
      addItem() {
        this.newDeliveryNote.items.push({
          ...deliveryNoteItemStub,
          id: Guid.raw(),
          taxes: [{ ...taxStub, id: Guid.raw() }],
        })
      },

      updateItem(data) {
        Object.assign(this.newDeliveryNote.items[data.index], { ...data })
      },

      removeItem(index) {
        this.newDeliveryNote.items.splice(index, 1)
      },

      deselectItem(index) {
        this.newDeliveryNote.items[index] = {
          ...deliveryNoteItemStub,
          id: Guid.raw(),
          taxes: [{ ...taxStub, id: Guid.raw() }],
        }
      },

      // Templates y notas
      fetchInvoiceTemplates(params) {
        return new Promise((resolve, reject) => {
          axios
            .get('/api/v1/invoices/templates', { params })
            .then((response) => {
              this.templates = response.data.invoiceTemplates
              resolve(response)
            })
            .catch((err) => { handleError(err); reject(err) })
        })
      },

      setTemplate(data) { this.newDeliveryNote.template_name = data },
      selectNote(data) { this.newDeliveryNote.selectedNote = data },
      resetSelectedNote() { this.newDeliveryNote.selectedNote = null },
      resetSelectedCustomer() {
        this.newDeliveryNote.customer = null
        this.newDeliveryNote.customer_id = null
      },

      getNextNumber(params, setState = false) {
        return new Promise((resolve, reject) => {
          axios
            .get('/api/v1/next-number?key=delivery_note', { params })
            .then((response) => {
              if (setState) {
                this.newDeliveryNote.delivery_note_number = response.data.nextNumber
              }
              resolve(response)
            })
            .catch((err) => { handleError(err); reject(err) })
        })
      },

      // CRUD
      fetchDeliveryNotes(params) {
        return new Promise((resolve, reject) => {
          axios.get('/api/v1/delivery-notes', { params })
            .then((response) => {
              this.deliveryNotes = response.data.data
              this.deliveryNoteTotalCount =
                response.data.delivery_note_total_count ?? response.data.total ?? 0
              resolve(response)
            })
            .catch((err) => { handleError(err); reject(err) })
        })
      },

      fetchDeliveryNote(id) {
        return new Promise((resolve, reject) => {
          axios.get(`/api/v1/delivery-notes/${id}`)
            .then((response) => {
              this.setDeliveryNoteData(response.data.data)
              resolve(response)
            })
            .catch((err) => { handleError(err); reject(err) })
        })
      },

      addDeliveryNote(data) {
        return new Promise((resolve, reject) => {
          axios.post('/api/v1/delivery-notes', data)
            .then((response) => {
              notificationStore.showNotification({
                type: 'success',
                message: 'Albarán creado correctamente',
              })
              resolve(response)
            })
            .catch((err) => { handleError(err); reject(err) })
        })
      },

      updateDeliveryNote(data) {
        return new Promise((resolve, reject) => {
          axios.put(`/api/v1/delivery-notes/${data.id}`, data)
            .then((response) => {
              notificationStore.showNotification({
                type: 'success',
                message: 'Albarán actualizado',
              })
              resolve(response)
            })
            .catch((err) => { handleError(err); reject(err) })
        })
      },

      deleteDeliveryNote(id) {
        return new Promise((resolve, reject) => {
          axios.post('/api/v1/delivery-notes/delete', { ids: [id] })
            .then((response) => { resolve(response) })
            .catch((err) => { handleError(err); reject(err) })
        })
      },

      cloneDeliveryNote(data) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/delivery-notes/${data.id}/clone`, data)
            .then((response) => {
              notificationStore.showNotification({
                type: 'success',
                message: 'Albarán clonado correctamente',
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      markAsSent(data) {
        return new Promise((resolve, reject) => {
          axios.post(`/api/v1/delivery-notes/${data.id}/status`, { status: 'SENT' })
            .then((response) => { resolve(response) })
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

      sendDeliveryNote(data) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/delivery-notes/${data.id}/send`, data)
            .then((response) => {
              notificationStore.showNotification({
                type: 'success',
                message: 'Albarán enviado correctamente',
              })
              resolve(response)
            })
            .catch((err) => {
              // Onfactu — 409 de colisión manejada por la view
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

      markAsDelivered(data) {
        return new Promise((resolve, reject) => {
          axios.post(`/api/v1/delivery-notes/${data.id}/status`, { status: 'DELIVERED' })
            .then((response) => { resolve(response) })
            .catch((err) => { handleError(err); reject(err) })
        })
      },
    },
  })()
}
