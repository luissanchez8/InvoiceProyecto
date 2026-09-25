import axios from 'axios'
import moment from 'moment'
import Guid from 'guid'
import _ from 'lodash'
import { defineStore } from 'pinia'
import { useRoute } from 'vue-router'
import { handleError } from '@/scripts/helpers/error-handling'
import invoiceItemStub from '../stub/invoice-item'
import taxStub from '../stub/tax'
import invoiceStub from '../stub/invoice'

import { useNotificationStore } from '@/scripts/stores/notification'
import { useCustomerStore } from './customer'
import { useTaxTypeStore } from './tax-type'
import { useCompanyStore } from './company'
import { useItemStore } from './item'
import { useUserStore } from './user'
import { useNotesStore } from './note'

export const useInvoiceStore = (useWindow = false) => {
  const defineStoreFunc = useWindow ? window.pinia.defineStore : defineStore
  const { global } = window.i18n
  const notificationStore = useNotificationStore()

  return defineStoreFunc({
    id: 'invoice',
    state: () => ({
      templates: [],
      invoices: [],
      selectedInvoices: [],
      selectAllField: false,
      invoiceTotalCount: 0,
      showExchangeRate: false,
      isFetchingInitialSettings: false,
      isFetchingInvoice: false,

      // Onfactu — numeración diferida:
      // Guardamos la última sugerencia del secuencial para poder comparar
      // con lo que el usuario tiene en el input:
      //  - Si input === suggestedInvoiceNumber → no ha tocado, enviar null
      //    al backend al guardar como borrador.
      //  - Si input !== suggestedInvoiceNumber → el usuario puso un número
      //    manual, se envía tal cual.
      suggestedInvoiceNumber: null,
      // isSkipped indica si la sugerencia es un "hueco" por encima del MAX+1
      // puro. Si true, al guardar como borrador con la sugerencia intacta
      // NO se descarta (se persiste el número concreto).
      suggestedInvoiceNumberIsSkipped: false,
      // naturalNextInvoiceNumber es el siguiente secuencial puro (MAX+1
      // formateado) sin tener en cuenta colisiones. Sirve para el aviso
      // amber: si el numero del documento difiere de naturalNext, el
      // usuario está saltando la numeración real (independiente de los
      // huecos que haya por borradores existentes).
      naturalNextInvoiceNumber: null,

      newInvoice: {
        ...invoiceStub(),
      },
    }),

    getters: {
      getInvoice: (state) => (id) => {
        let invId = parseInt(id)
        return state.invoices.find((invoice) => invoice.id === invId)
      },

      getSubTotal() {
        return this.newInvoice.items.reduce(function (a, b) {
          return a + b['total']
        }, 0)
      },

      getTotalSimpleTax() {
        return _.sumBy(this.newInvoice.taxes, function (tax) {
          if (!tax.compound_tax) {
            return tax.amount
          }
          return 0
        })
      },

      getTotalCompoundTax() {
        return _.sumBy(this.newInvoice.taxes, function (tax) {
          if (tax.compound_tax) {
            return tax.amount
          }
          return 0
        })
      },

      getTotalTax() {
        if (
          this.newInvoice.tax_per_item === 'NO' ||
          this.newInvoice.tax_per_item === null
        ) {
          return this.getTotalSimpleTax + this.getTotalCompoundTax
        }
        return _.sumBy(this.newInvoice.items, function (tax) {
          return tax.tax
        })
      },

      getSubtotalWithDiscount() {
        return this.getSubTotal - this.newInvoice.discount_val
      },

      getTotal() {
        return this.getSubtotalWithDiscount + this.getTotalTax
      },

      isEdit: (state) => (state.newInvoice.id ? true : false),
    },

    actions: {
      resetCurrentInvoice() {
        this.newInvoice = {
          ...invoiceStub(),
        }
      },

      previewInvoice(params) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/invoices/${params.id}/send/preview`, { params })
            .then((response) => {
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchInvoices(params) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/invoices`, { params })
            .then((response) => {
              this.invoices = response.data.data
              this.invoiceTotalCount = response.data.meta.invoice_total_count
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchInvoice(id) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/invoices/${id}`)
            .then((response) => {
              this.setInvoiceData(response.data.data)
              this.setCustomerAddresses(this.newInvoice.customer)
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      setInvoiceData(invoice) {
        Object.assign(this.newInvoice, invoice)

        if (this.newInvoice.tax_per_item === 'YES') {
          this.newInvoice.items.forEach((_i) => {
            if (_i.taxes && !_i.taxes.length)
              _i.taxes.push({ ...taxStub, id: Guid.raw() })
          })
        }

        if (this.newInvoice.discount_per_item === 'YES') {
          this.newInvoice.items.forEach((_i, index) => {
            if (_i.discount_type === 'fixed')
              this.newInvoice.items[index].discount = _i.discount / 100
          })
        }
        else {
          if (this.newInvoice.discount_type === 'fixed')
            this.newInvoice.discount = this.newInvoice.discount / 100
        }
      },

      setCustomerAddresses(customer) {
        const customer_business = customer.customer_business

        if (customer_business?.billing_address)
          this.newInvoice.customer.billing_address = customer_business.billing_address

        if (customer_business?.shipping_address)
          this.newInvoice.customer.shipping_address = customer_business.shipping_address
      },

      addSalesTaxUs() {
        const taxTypeStore = useTaxTypeStore()
        let salesTax = { ...taxStub }
        let found = this.newInvoice.taxes.find((_t) => _t.name === 'Sales Tax' && _t.type === 'MODULE')
        if (found) {
          for (const key in found) {
            if (Object.prototype.hasOwnProperty.call(salesTax, key)) {
              salesTax[key] = found[key]
            }
          }
          salesTax.id = found.tax_type_id
          taxTypeStore.taxTypes.push(salesTax)
        }
      },

      sendInvoice(data) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/invoices/${data.id}/send`, data)
            .then((response) => {
              notificationStore.showNotification({
                type: 'success',
                message: global.t('invoices.invoice_sent_successfully'),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      addInvoice(data) {
        return new Promise((resolve, reject) => {
          axios
            .post('/api/v1/invoices', data)
            .then((response) => {
              this.invoices = [...this.invoices, response.data.invoice]

              notificationStore.showNotification({
                type: 'success',
                message: global.t('invoices.created_message'),
              })

              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      deleteInvoice(id) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/invoices/delete`, id)
            .then((response) => {
              let index = this.invoices.findIndex(
                (invoice) => invoice.id === id
              )
              this.invoices.splice(index, 1)

              notificationStore.showNotification({
                type: 'success',
                message: global.t('invoices.deleted_message', 1),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      deleteMultipleInvoices(id) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/invoices/delete`, { ids: this.selectedInvoices })
            .then((response) => {
              this.selectedInvoices.forEach((invoice) => {
                let index = this.invoices.findIndex(
                  (_inv) => _inv.id === invoice.id
                )
                this.invoices.splice(index, 1)
              })
              this.selectedInvoices = []

              notificationStore.showNotification({
                type: 'success',
                message: global.tc('invoices.deleted_message', 2),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      updateInvoice(data) {
        return new Promise((resolve, reject) => {
          axios
            .put(`/api/v1/invoices/${data.id}`, data)
            .then((response) => {
              let pos = this.invoices.findIndex(
                (invoice) => invoice.id === response.data.data.id
              )
              this.invoices[pos] = response.data.data

              notificationStore.showNotification({
                type: 'success',
                message: global.t('invoices.updated_message'),
              })

              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      // Onfactu: crear factura rectificativa a partir de una factura.
      rectifyInvoice(id) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/invoices/${id}/rectify`)
            .then((response) => {
              notificationStore.showNotification({
                type: 'success',
                message: global.t('invoices.rectified_successfully'),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },
      cloneInvoice(data) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/invoices/${data.id}/clone`, data)
            .then((response) => {
              notificationStore.showNotification({
                type: 'success',
                message: global.t('invoices.cloned_successfully'),
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
          axios
            .post(`/api/v1/invoices/${data.id}/status`, data)
            .then((response) => {
              let pos = this.invoices.findIndex(
                (invoices) => invoices.id === data.id
              )

              if (this.invoices[pos]) {
                // Onfactu v.1.13: marcarla como enviada no cambia el estado
                this.invoices[pos].sent = true
              }

              notificationStore.showNotification({
                type: 'success',
                message: global.t('invoices.mark_as_sent_successfully'),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      // Onfactu v.1.13: aprobar es el único paso para salir del borrador
      approveInvoice(id, params = {}) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/invoices/${id}/approve`, params)
            .then((response) => {
              const aprobada = response.data?.data
              if (aprobada && Array.isArray(this.invoices)) {
                const pos = this.invoices.findIndex((f) => f && f.id === id)
                if (pos >= 0) Object.assign(this.invoices[pos], aprobada)
              }
              resolve(response)
            })
            .catch((err) => {
              // Los motivos para no aprobar (fecha, mes cerrado...) los enseña el
              // diálogo de aprobar; el resto de errores, el aviso de siempre.
              if (!(err?.response?.status === 422 && err?.response?.data?.code)) {
                handleError(err)
              }
              reject(err)
            })
        })
      },

      approveMultiple(ids) {
        return new Promise((resolve, reject) => {
          axios
            .post('/api/v1/invoices/approve-multiple', { ids })
            .then((response) => resolve(response))
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      // Onfactu v.1.13: darla por cobrada sin registrar un cobro (antes era
      // "Marcar como completada")
      markAsPaid(id) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/invoices/${id}/status`, { id, status: 'PAID' })
            .then((response) => {
              notificationStore.showNotification({
                type: 'success',
                message: global.t('estados.marcada_cobrada'),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      getNextNumber(params, setState = false) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/next-number?key=invoice`, { params })
            .then((response) => {
              if (setState) {
                this.newInvoice.invoice_number = response.data.nextNumber
              }
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      searchInvoice(data) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/invoices?${data}`)
            .then((response) => {
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      selectInvoice(data) {
        this.selectedInvoices = data
        if (this.selectedInvoices.length === this.invoices.length) {
          this.selectAllField = true
        } else {
          this.selectAllField = false
        }
      },

      selectAllInvoices() {
        if (this.selectedInvoices.length === this.invoices.length) {
          this.selectedInvoices = []
          this.selectAllField = false
        } else {
          let allInvoiceIds = this.invoices.map((invoice) => invoice.id)
          this.selectedInvoices = allInvoiceIds
          this.selectAllField = true
        }
      },

      selectCustomer(id) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/customers/${id}`)
            .then((response) => {
              this.newInvoice.customer = response.data.data
              this.newInvoice.customer_id = response.data.data.id
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchInvoiceTemplates(params) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/invoices/templates`, { params })
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

      selectNote(data) {
        this.newInvoice.selectedNote = null
        this.newInvoice.selectedNote = data
      },

      setTemplate(data) {
        this.newInvoice.template_name = data
      },

      resetSelectedCustomer() {
        this.newInvoice.customer = null
        this.newInvoice.customer_id = null
      },

      addItem() {
        this.newInvoice.items.push({
          ...invoiceItemStub,
          id: Guid.raw(),
          taxes: [{ ...taxStub, id: Guid.raw() }],
        })
      },

      updateItem(data) {
        Object.assign(this.newInvoice.items[data.index], { ...data })
      },

      removeItem(index) {
        this.newInvoice.items.splice(index, 1)
      },

      deselectItem(index) {
        this.newInvoice.items[index] = {
          ...invoiceItemStub,
          id: Guid.raw(),
          taxes: [{ ...taxStub, id: Guid.raw() }],
        }
      },

      resetSelectedNote() {
        this.newInvoice.selectedNote = null
      },

      // On Load actions
      async fetchInvoiceInitialSettings(isEdit) {
        const companyStore = useCompanyStore()
        const customerStore = useCustomerStore()
        const itemStore = useItemStore()
        const taxTypeStore = useTaxTypeStore()
        const route = useRoute()
        const userStore = useUserStore()
        const notesStore = useNotesStore()

        this.isFetchingInitialSettings = true

        this.newInvoice.selectedCurrency = companyStore.selectedCompanyCurrency

        if (route.query.customer) {
          let response = await customerStore.fetchCustomer(route.query.customer)
          this.newInvoice.customer = response.data.data
          this.newInvoice.customer_id = response.data.data.id
        }

        let editActions = []
        
        if (!isEdit) {
          await notesStore.fetchNotes()
          this.newInvoice.notes = notesStore.getDefaultNoteForType('Invoice')?.notes
          this.newInvoice.tax_per_item =
            companyStore.selectedCompanySettings.tax_per_item
          this.newInvoice.sales_tax_type = companyStore.selectedCompanySettings.sales_tax_type
          this.newInvoice.sales_tax_address_type = companyStore.selectedCompanySettings.sales_tax_address_type
          this.newInvoice.discount_per_item =
            companyStore.selectedCompanySettings.discount_per_item

          // v.1.9.4 — Onfactu: IVA 21% por defecto al crear factura.
          // Si la empresa tiene un tax_type con percent=21 y nombre que
          // contiene "IVA 21", lo añadimos automáticamente. El usuario
          // puede borrarlo si no le aplica.
          try {
            await taxTypeStore.fetchTaxTypes({ limit: 'all' })
            const ivaDefault = (taxTypeStore.taxTypes || []).find(t =>
              Number(t.percent) === 21 && /IVA\s*21/i.test(t.name || '')
            )
            if (ivaDefault && this.newInvoice.taxes.length === 0) {
              this.newInvoice.taxes.push({
                id: ivaDefault.id,
                tax_type_id: ivaDefault.id,
                name: ivaDefault.name,
                percent: parseFloat(ivaDefault.percent),
                amount: 0,
                compound_tax: !!ivaDefault.compound_tax,
                collective_tax: 0,
                calculation_type: ivaDefault.calculation_type || 'percentage',
                fixed_amount: ivaDefault.fixed_amount || 0,
              })
            }
          } catch (e) {
            // Si no se pueden cargar tax types, no romper la creación
            console.error('Error cargando IVA 21% por defecto:', e)
          }

          let dateFormat = 'YYYY-MM-DD';
          if (companyStore.selectedCompanySettings.invoice_use_time === 'YES') {
            dateFormat += ' HH:mm'
          }

          this.newInvoice.invoice_date = moment().format(dateFormat)
          if (companyStore.selectedCompanySettings.invoice_set_due_date_automatically === 'YES') {
            this.newInvoice.due_date = moment()
              .add(companyStore.selectedCompanySettings.invoice_due_date_days, 'days')
              .format('YYYY-MM-DD')
          }
        } else {
          editActions = [this.fetchInvoice(route.params.id)]
        }

        // Onfactu — numeración diferida:
        // En edit, pasamos model_id para que el backend excluya el propio
        // documento al calcular la sugerencia. Sin esto, una factura borrador
        // con número "INV-000040" se contaría como ocupada y la sugerencia
        // saltaría a 41, provocando un falso aviso de "saltando numeración".
        const nextNumberParams = isEdit ? { model_id: route.params.id } : undefined

        Promise.all([
          itemStore.fetchItems({
            filter: {},
            orderByField: '',
            orderBy: '',
          }),
          this.resetSelectedNote(),
          this.fetchInvoiceTemplates(),
          this.getNextNumber(nextNumberParams),
          taxTypeStore.fetchTaxTypes({ limit: 'all' }),
          ...editActions,
        ])
          .then(async ([res1, res2, res3, res4, res5, res6]) => {
            if (!isEdit) {
              // Onfactu — numeración diferida (opción C):
              // Pre-rellenamos el input con el siguiente número sugerido y
              // guardamos aparte la sugerencia + flag isSkipped + naturalNext.
              // Al guardar como borrador:
              //  - Si es "clean" y no la tocó → null (libera el número).
              //  - Si es "skipped" y no la tocó → se persiste literal (reserva
              //    ese hueco concreto en la secuencia).
              if (res4.data) {
                // Onfactu v.1.13: el número se asigna al aprobar; no se prerellena
                this.newInvoice.invoice_number = null
                this.suggestedInvoiceNumber = res4.data.nextNumber
                this.suggestedInvoiceNumberIsSkipped = !!res4.data.isSkipped
                this.naturalNextInvoiceNumber = res4.data.naturalNext || res4.data.nextNumber
              }

              if (res3.data) {
                // Forzar invoice4 como plantilla universal
                this.setTemplate('invoice4')
                this.newInvoice.template_name = 'invoice4'
              }
            } else {
              // En edición: guardamos la sugerencia actual para comparar
              // al marcar como enviado / aprobar.
              if (res4.data) {
                this.suggestedInvoiceNumber = res4.data.nextNumber
                this.suggestedInvoiceNumberIsSkipped = !!res4.data.isSkipped
                this.naturalNextInvoiceNumber = res4.data.naturalNext || res4.data.nextNumber

                // Onfactu — borrador sin numero: prerellenar con la sugerencia.
                // Pasa con las facturas que vienen de convertir un presupuesto:
                // nacen sin numero (numeracion diferida) y el campo aparecia
                // vacio, cuando al crear una factura normal sale prerelleno.
                // Solo se rellena si esta vacio: nunca pisa un numero existente.
                // Onfactu v.1.13: ya no se prerellena; el número se asigna al aprobar
              }
              this.addSalesTaxUs()
            }

            this.isFetchingInitialSettings = false
          })
          .catch((err) => {
            handleError(err)
            reject(err)
          })
      },
    },
  })()
}
