import { handleError } from '@/scripts/customer/helpers/error-handling'
const { defineStore } = window.pinia
import axios from 'axios'
export const useProformaInvoiceStore = defineStore({
  id: 'customerProformaInvoiceStore',
  state: () => ({
    totalProformas: 0,
    proformas: [],
    selectedViewProforma: [],
  }),
  actions: {
    fetchProformas(params, slug) {
      return new Promise((resolve, reject) => {
        axios
          .get(`/api/v1/${slug}/customer/proforma-invoices`, { params })
          .then((response) => {
            this.proformas = response.data.data
            this.totalProformas = response.data.meta.proformaTotalCount
            resolve(response)
          })
          .catch((err) => { handleError(err); reject(err) })
      })
    },
    fetchViewProforma(params, slug) {
      return new Promise((resolve, reject) => {
        axios
          .get(`/api/v1/${slug}/customer/proforma-invoices/${params.id}`, { params })
          .then((response) => {
            this.selectedViewProforma = response.data.data
            resolve(response)
          })
          .catch((err) => { handleError(err); reject(err) })
      })
    },
  },
})
