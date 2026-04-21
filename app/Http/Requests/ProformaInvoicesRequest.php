<?php

/**
 * Request ProformaInvoicesRequest — Validación de facturas proforma
 *
 * Valida los datos del formulario de creación/edición de facturas proforma.
 * Replica la lógica de InvoicesRequest pero sin campos de pago.
 * El método getProformaInvoicePayload() prepara los datos para el modelo.
 *
 * Onfactu — numeración diferida:
 *  - proforma_invoice_number es OPCIONAL al crear/editar un borrador.
 *  - Si el usuario lo escribe, debe ser único en la empresa.
 *  - Si se deja vacío, se asigna automáticamente al enviar (SENT).
 */

namespace App\Http\Requests;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\ProformaInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProformaInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'proforma_invoice_date' => ['required'],
            'expiry_date' => ['nullable'],
            'customer_id' => ['required'],
            'proforma_invoice_number' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('proforma_invoices')->where('company_id', $this->header('company')),
            ],
            'exchange_rate' => ['nullable'],
            'discount' => ['numeric', 'required'],
            'discount_val' => ['integer', 'required'],
            'sub_total' => ['numeric', 'required'],
            'total' => ['numeric', 'max:999999999999', 'required'],
            'tax' => ['required'],
            'template_name' => ['required'],
            'items' => ['required', 'array'],
            'items.*.name' => ['required'],
            'items.*.quantity' => ['numeric', 'required'],
            'items.*.price' => ['numeric', 'required'],
            'items.*.description' => ['nullable'],
        ];

        $companyCurrency = CompanySetting::getSetting('currency', $this->header('company'));
        $customer = Customer::find($this->customer_id);
        if ($customer && $companyCurrency && (string) $customer->currency_id !== $companyCurrency) {
            $rules['exchange_rate'] = ['required'];
        }

        if ($this->isMethod('PUT')) {
            $proforma = $this->route('proforma_invoice');

            // Si la proforma ya está en estado emitido (no DRAFT), el número
            // no se puede cambiar.
            if ($proforma && $proforma->status !== ProformaInvoice::STATUS_DRAFT && ! empty($proforma->proforma_invoice_number)) {
                $rules['proforma_invoice_number'] = [
                    'required',
                    Rule::in([$proforma->proforma_invoice_number]),
                ];
            } else {
                $rules['proforma_invoice_number'] = [
                    'nullable',
                    'string',
                    'max:100',
                    Rule::unique('proforma_invoices')
                        ->ignore($proforma->id)
                        ->where('company_id', $this->header('company')),
                ];
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'proforma_invoice_number.unique' => 'Ya existe una proforma con ese número en esta empresa. Elige otro o deja el campo vacío para asignar uno automáticamente al enviar.',
            'proforma_invoice_number.in' => 'No se puede cambiar el número de una proforma ya enviada.',
        ];
    }

    /**
     * Prepara el payload para ProformaInvoice::createProformaInvoice()
     * Similar a InvoicesRequest::getInvoicePayload() pero sin due_amount ni paid_status.
     */
    public function getProformaInvoicePayload(): array
    {
        $company_currency = CompanySetting::getSetting('currency', $this->header('company'));
        $current_currency = $this->currency_id;
        $exchange_rate = $company_currency != $current_currency ? $this->exchange_rate : 1;
        $currency = Customer::find($this->customer_id)->currency_id;

        return collect($this->except('items', 'taxes'))
            ->merge([
                'creator_id' => $this->user()->id ?? null,
                'status' => $this->has('proformaInvoiceSend')
                    ? ProformaInvoice::STATUS_SENT
                    : ProformaInvoice::STATUS_DRAFT,
                'company_id' => $this->header('company'),
                'tax_per_item' => CompanySetting::getSetting('tax_per_item', $this->header('company')) ?? 'NO',
                'discount_per_item' => CompanySetting::getSetting('discount_per_item', $this->header('company')) ?? 'NO',
                'sent' => (bool) ($this->sent ?? false),
                'viewed' => false,
                'exchange_rate' => $exchange_rate,
                'base_total' => $this->total * $exchange_rate,
                'base_discount_val' => $this->discount_val * $exchange_rate,
                'base_sub_total' => $this->sub_total * $exchange_rate,
                'base_tax' => $this->tax * $exchange_rate,
                'currency_id' => $currency,
            ])
            ->toArray();
    }
}
