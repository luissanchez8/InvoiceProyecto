<?php

/**
 * Request ProformaInvoicesRequest — Validación de facturas proforma
 *
 * Valida los datos del formulario de creación/edición de facturas proforma.
 * Replica la lógica de InvoicesRequest pero sin campos de pago.
 * El método getProformaInvoicePayload() prepara los datos para el modelo.
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
                // Onfactu: opcional si se guarda como borrador.
                Rule::requiredIf(fn () => ($this->status ?? null) !== ProformaInvoice::STATUS_DRAFT),
                'nullable',
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

        // Tipo de cambio requerido si la moneda del cliente difiere de la empresa
        $companyCurrency = CompanySetting::getSetting('currency', $this->header('company'));
        $customer = Customer::find($this->customer_id);
        if ($customer && $companyCurrency && (string) $customer->currency_id !== $companyCurrency) {
            $rules['exchange_rate'] = ['required'];
        }

        // En PUT, ignorar el registro actual para la validación de unicidad
        if ($this->isMethod('PUT')) {
            $rules['proforma_invoice_number'] = [
                // Onfactu: opcional si se sigue guardando como borrador.
                Rule::requiredIf(fn () => ($this->status ?? null) !== ProformaInvoice::STATUS_DRAFT),
                'nullable',
                Rule::unique('proforma_invoices')
                    ->ignore($this->route('proforma_invoice')->id)
                    ->where('company_id', $this->header('company')),
            ];
        }

        return $rules;
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

        // Onfactu: respetar el status que envía el frontend.
        $status = $this->status
            ?? ($this->has('proformaInvoiceSend')
                ? ProformaInvoice::STATUS_SENT
                : ProformaInvoice::STATUS_DRAFT);

        return collect($this->except('items', 'taxes'))
            ->merge([
                'creator_id' => $this->user()->id ?? null,
                'status' => $status,
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
    /**
     * Onfactu v.1.9.5: Nombres amigables para los campos en los mensajes de error.
     */
    public function attributes(): array
    {
        return [
            'proforma_invoice_number' => 'número de proforma',
            'proforma_invoice_date' => 'fecha de la proforma',
            'customer_id' => 'cliente',
            'items' => 'artículos',
            'items.*.name' => 'nombre del artículo',
            'items.*.description' => 'descripción del artículo',
            'items.*.quantity' => 'cantidad',
            'items.*.price' => 'precio',
        ];
    }

    /**
     * Onfactu v.1.9.5: Mensajes específicos en español.
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Debes añadir al menos un artículo.',
            'items.*.name.required' => 'El nombre del artículo es obligatorio.',
            'items.*.name.max' => 'El nombre del artículo no puede tener más de :max caracteres.',
            'items.*.name.string' => 'El nombre del artículo debe ser texto.',
            'items.*.quantity.required' => 'La cantidad es obligatoria.',
            'items.*.quantity.numeric' => 'La cantidad debe ser numérica.',
            'items.*.price.required' => 'El precio es obligatorio.',
            'items.*.price.numeric' => 'El precio debe ser numérico.',
        ];
    }

}
