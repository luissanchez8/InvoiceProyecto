/**
 * Stub: proforma-invoice
 *
 * Estructura de datos por defecto para una nueva factura proforma.
 * Se usa como estado inicial del formulario de creación.
 */

import Guid from 'guid'
import proformaInvoiceItemStub from './proforma-invoice-item'

export default function () {
  return {
    id: null,
    proforma_invoice_number: '',
    customer: null,
    customer_id: null,
    template_name: null,
    proforma_invoice_date: '',
    expiry_date: '',
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
    taxes: [],
    items: [{ ...proformaInvoiceItemStub, id: Guid.raw(), taxes: [] }],
    customFields: [],
    fields: [],
    selectedNote: null,
    selectedCurrency: '',
  }
}
