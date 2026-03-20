/**
 * Stub: delivery-note
 *
 * Estructura de datos por defecto para un nuevo albarán.
 * Incluye show_prices (true por defecto) para controlar
 * si los precios se muestran en el PDF.
 */

import Guid from 'guid'
import deliveryNoteItemStub from './delivery-note-item'

export default function () {
  return {
    id: null,
    delivery_note_number: '',
    customer: null,
    customer_id: null,
    template_name: null,
    delivery_note_date: '',
    delivery_date: '',
    notes: '',
    discount: 0,
    discount_type: 'fixed',
    discount_val: 0,
    reference_number: null,
    tax: 0,
    sub_total: 0,
    total: 0,
    tax_per_item: null,
    discount_per_item: null,
    show_prices: true, // Controla si los precios aparecen en el PDF
    taxes: [],
    items: [{ ...deliveryNoteItemStub, id: Guid.raw(), taxes: [] }],
    customFields: [],
    fields: [],
    selectedNote: null,
    selectedCurrency: '',
  }
}
