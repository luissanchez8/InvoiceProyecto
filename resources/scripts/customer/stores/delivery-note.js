import { handleError } from '@/scripts/customer/helpers/error-handling'
const { defineStore } = window.pinia
import axios from 'axios'
export const useDeliveryNoteStore = defineStore({
  id: 'customerDeliveryNoteStore',
  state: () => ({
    totalDeliveryNotes: 0,
    deliveryNotes: [],
    selectedViewDeliveryNote: [],
  }),
  actions: {
    fetchDeliveryNotes(params, slug) {
      return new Promise((resolve, reject) => {
        axios
          .get(`/api/v1/${slug}/customer/delivery-notes`, { params })
          .then((response) => {
            this.deliveryNotes = response.data.data
            this.totalDeliveryNotes = response.data.meta.deliveryNoteTotalCount
            resolve(response)
          })
          .catch((err) => { handleError(err); reject(err) })
      })
    },
    fetchViewDeliveryNote(params, slug) {
      return new Promise((resolve, reject) => {
        axios
          .get(`/api/v1/${slug}/customer/delivery-notes/${params.id}`, { params })
          .then((response) => {
            this.selectedViewDeliveryNote = response.data.data
            resolve(response)
          })
          .catch((err) => { handleError(err); reject(err) })
      })
    },
  },
})
