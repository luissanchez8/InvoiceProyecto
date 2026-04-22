import { useAuthStore } from '@/scripts/admin/stores/auth'
import { useNotificationStore } from '@/scripts/stores/notification'

export const handleError = (err) => {
  const authStore = useAuthStore()
  const notificationStore = useNotificationStore()

  // Onfactu — ignorar peticiones canceladas/abortadas:
  // Cuando el usuario navega entre páginas (p.ej. tras aprobar una factura
  // que hace router.push a la vista detalle), axios aborta las peticiones
  // pendientes. Esos errores llegan aquí sin err.response y antes se
  // mostraban como "Please check your internet connection", confundiendo
  // al usuario aunque la operación haya ido bien.
  if (
    err?.code === 'ERR_CANCELED' ||
    err?.name === 'CanceledError' ||
    err?.message === 'canceled' ||
    (typeof err?.__CANCEL__ !== 'undefined' && err.__CANCEL__)
  ) {
    return // ignorar silenciosamente
  }

  // Onfactu — distinguir errores de red reales de errores de JavaScript
  // (TypeError, ReferenceError, etc.) que pueden ocurrir DENTRO de un
  // .then() o .catch() y llegan aquí sin err.response. Antes se mostraba
  // "Please check your internet connection" para CUALQUIER error sin
  // response, pero un TypeError no tiene nada que ver con conexión.
  //
  // Errores de red reales típicamente tienen:
  //  - err.code = 'ERR_NETWORK' o 'ECONNREFUSED' o 'ETIMEDOUT'
  //  - err.message que empieza por "Network Error" o "timeout"
  //  - err.isAxiosError = true
  //
  // Si llega aquí algo que NO es un error de axios y NO tiene response,
  // es muy probable un error de JavaScript dentro del flujo. En ese caso
  // logueamos en consola y NO molestamos al usuario con un toast falso.
  if (!err.response) {
    const isAxiosError = err?.isAxiosError === true || err?.name === 'AxiosError'
    const isNetworkLike =
      err?.code === 'ERR_NETWORK' ||
      err?.code === 'ECONNREFUSED' ||
      err?.code === 'ETIMEDOUT' ||
      (typeof err?.message === 'string' && (
        err.message.startsWith('Network Error') ||
        err.message.startsWith('timeout')
      ))

    if (isAxiosError && isNetworkLike) {
      notificationStore.showNotification({
        type: 'error',
        message:
          'Please check your internet connection or wait until servers are back online.',
      })
    } else {
      // Error inesperado de JavaScript (TypeError, etc.). No es de red.
      console.error('handleError: error no-HTTP no-cancelación:', err)
    }
    return
  }

  // Tiene response: es un error HTTP normal
  if (
    err.response.data &&
    (err.response.statusText === 'Unauthorized' ||
      err.response.data === ' Unauthorized.')
  ) {
    // Unauthorized and log out
    const msg = err.response.data.message
      ? err.response.data.message
      : 'Unauthorized'

    showToaster(msg)

    authStore.logout()
  } else if (err.response.data.errors) {
    // Show a notification per error
    const errors = JSON.parse(JSON.stringify(err.response.data.errors))
    for (const i in errors) {
      showError(errors[i][0])
    }
  } else if (err.response.data.error) {
    if (typeof err.response.data.error == 'boolean') showError(err.response.data?.message)
    else showError(err.response.data.error)
  } else {
    showError(err.response.data.message)
  }
}

export const showError = (error) => {
  switch (error) {
    case 'These credentials do not match our records.':
      showToaster('errors.login_invalid_credentials')
      break
    case 'invalid_key':
      showToaster('errors.invalid_provider_key')
      break

    case 'This feature is available on Starter plan and onwards!':
      showToaster('errors.starter_plan')
      break

    case 'taxes_attached':
      showToaster('settings.tax_types.already_in_use')
      break

    case 'expense_attached':
      showToaster('settings.expense_category.already_in_use')
      break

    case 'payments_attached':
      showToaster('settings.payment_modes.payments_attached')
      break
    
    case 'expenses_attached':
      showToaster('settings.payment_modes.expenses_attached')
      break

    case 'role_attached_to_users':
      showToaster('settings.roles.already_in_use')
      break

    case 'items_attached':
      showToaster('settings.customization.items.already_in_use')
      break

    case 'payment_attached_message':
      showToaster('invoices.payment_attached_message')
      break

    case 'The email has already been taken.':
      showToaster('validation.email_already_taken')
      break

    case 'Relation estimateItems exists.':
      showToaster('items.item_attached_message')
      break

    case 'Relation invoiceItems exists.':
      showToaster('items.item_attached_message')
      break

    case 'Relation taxes exists.':
      showToaster('settings.tax_types.already_in_use')
      break

    case 'Relation taxes exists.':
      showToaster('settings.tax_types.already_in_use')
      break

    case 'Relation payments exists.':
      showToaster('errors.payment_attached')
      break

    case 'The estimate number has already been taken.':
      showToaster('errors.estimate_number_used')
      break

    case 'The payment number has already been taken.':
      showToaster('errors.estimate_number_used')
      break

    case 'The invoice number has already been taken.':
      showToaster('errors.invoice_number_used')
      break

    case 'The name has already been taken.':
      showToaster('errors.name_already_taken')
      break

    case 'total_invoice_amount_must_be_more_than_paid_amount':
      showToaster('invoices.invalid_due_amount_message')
      break

    case 'you_cannot_edit_currency':
      showToaster('customers.edit_currency_not_allowed')
      break

    case 'receipt_does_not_exist':
      showToaster('errors.receipt_does_not_exist')
      break

    case 'customer_cannot_be_changed_after_payment_is_added':
      showToaster('errors.customer_cannot_be_changed_after_payment_is_added')
      break

    case 'invalid_credentials':
      showToaster('errors.invalid_credentials')
      break

    case 'not_allowed':
      showToaster('errors.not_allowed')
      break

    case 'invalid_key':
      showToaster('errors.invalid_key')
      break

    case 'invalid_state':
      showToaster('errors.invalid_state')
      break

    case 'invalid_city':
      showToaster('errors.invalid_city')
      break

    case 'invalid_postal_code':
      showToaster('errors.invalid_postal_code')
      break

    case 'invalid_format':
      showToaster('errors.invalid_format')
      break

    case 'api_error':
      showToaster('errors.api_error')
      break

    case 'feature_not_enabled':
      showToaster('errors.feature_not_enabled')
      break

    case 'request_limit_met':
      showToaster('errors.request_limit_met')
      break

    case 'address_incomplete':
      showToaster('errors.address_incomplete')
      break

    case 'invalid_address':
      showToaster('errors.invalid_address')
      break

    case 'Email could not be sent to this email address.':
      showToaster('errors.email_could_not_be_sent')
      break

    default:
      showToaster(error, false)
      break
  }
}

export const showToaster = (msg, t = true) => {
  const { global } = window.i18n
  const notificationStore = useNotificationStore()

  notificationStore.showNotification({
    type: 'error',
    message: t ? global.t(msg) : msg,
  })
}
