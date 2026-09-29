import moment from 'moment'

export default {
  expense_category_id: null,
  expense_date: moment().format('YYYY-MM-DD'),
  amount: 0,
  notes: '',
  attachment_receipt: null,
  customer_id: '',
  currency_id: '',
  payment_method_id: '',
  receiptFiles: [],
  customFields: [],
  fields: [],
  in_use: false,
  selectedCurrency: null,
  // Onfactu v.1.15.0: proveedor e IVA desglosado. Los gastos nuevos van
  // siempre con desglose; los de antes llegan con con_desglose a false.
  proveedor_nombre: '',
  proveedor_nif: '',
  numero_factura: '',
  con_desglose: true,
  lineas_iva: [],
  retencion_porcentaje: 0,
}
