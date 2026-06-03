<?php

namespace App\Http\Requests;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoicesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.s
     */
    public function rules(): array
    {
        $rules = [
            'invoice_date' => [
                'required',
            ],
            'due_date' => [
                'nullable',
            ],
            'customer_id' => [
                'required',
            ],
            'invoice_number' => [
                // Onfactu: si el usuario guarda como borrador, el número no
                // se asigna (queda NULL). Solo es obligatorio para no-borrador.
                Rule::requiredIf(fn () => ($this->status ?? null) !== Invoice::STATUS_DRAFT),
                'nullable',
                Rule::unique('invoices')->where('company_id', $this->header('company')),
            ],
            'exchange_rate' => [
                'nullable',
            ],
            'discount' => [
                'numeric',
                'required',
            ],
            'discount_val' => [
                'integer',
                'required',
            ],
            'sub_total' => [
                'numeric',
                'required',
            ],
            'total' => [
                'numeric',
                'max:999999999999',
                'required',
            ],
            'tax' => [
                'required',
            ],
            'template_name' => [
                'required',
            ],
            'items' => [
                'required',
                'array',
            ],
            'items.*' => [
                'required',
                'max:255',
            ],
            'items.*.description' => [
                'nullable',
            ],
            'items.*.name' => [
                'required',
                'string',
                'max:255',
            ],
            'items.*.quantity' => [
                'numeric',
                'required',
            ],
            'items.*.price' => [
                'numeric',
                'required',
            ],
        ];

        $companyCurrency = CompanySetting::getSetting('currency', $this->header('company'));

        $customer = Customer::find($this->customer_id);

        if ($customer && $companyCurrency) {
            if ((string) $customer->currency_id !== $companyCurrency) {
                $rules['exchange_rate'] = [
                    'required',
                ];
            }
        }

        if ($this->isMethod('PUT')) {
            $rules['invoice_number'] = [
                // Onfactu: igual que en POST, opcional si se guarda como borrador.
                Rule::requiredIf(fn () => ($this->status ?? null) !== Invoice::STATUS_DRAFT),
                'nullable',
                Rule::unique('invoices')
                    ->ignore($this->route('invoice')->id)
                    ->where('company_id', $this->header('company')),
            ];
        }

        return $rules;
    }

    /**
     * Onfactu v.1.9.5: Mensajes de error en español para que el usuario los entienda.
     */
    public function messages(): array
    {
        return [
            'required' => 'Este campo es obligatorio.',
            'string' => 'Este campo debe ser texto.',
            'max' => 'Este campo no puede tener más de :max caracteres.',
            'numeric' => 'Este campo debe ser numérico.',
            'array' => 'Formato inválido.',
            'items.required' => 'Debes añadir al menos un artículo.',
            'items.*.name.required' => 'El nombre del artículo es obligatorio.',
            'items.*.name.max' => 'El nombre del artículo no puede tener más de :max caracteres.',
            'items.*.name.string' => 'El nombre del artículo debe ser texto.',
            'items.*.quantity.required' => 'La cantidad es obligatoria.',
            'items.*.quantity.numeric' => 'La cantidad debe ser numérica.',
            'items.*.price.required' => 'El precio es obligatorio.',
            'items.*.price.numeric' => 'El precio debe ser numérico.',
            'invoice_number.required' => 'El número de factura es obligatorio.',
            'invoice_date.required' => 'La fecha es obligatoria.',
            'customer_id.required' => 'Debes seleccionar un cliente.',
        ];
    }

    /**
     * Onfactu v.1.9.5: Nombres amigables para los campos en los mensajes de error.
     */
    public function attributes(): array
    {
        return [
            'invoice_number' => 'número de factura',
            'invoice_date' => 'fecha',
            'due_date' => 'fecha de vencimiento',
            'customer_id' => 'cliente',
            'items' => 'artículos',
            'items.*.name' => 'nombre del artículo',
            'items.*.description' => 'descripción del artículo',
            'items.*.quantity' => 'cantidad',
            'items.*.price' => 'precio',
        ];
    }

    public function getInvoicePayload(): array
    {
        $company_currency = CompanySetting::getSetting('currency', $this->header('company'));
        $current_currency = $this->currency_id;
        $exchange_rate = $company_currency != $current_currency ? $this->exchange_rate : 1;
        $currency = Customer::find($this->customer_id)->currency_id;

        // Onfactu: respetar el status que envía el frontend. Si no viene, caer
        // al comportamiento antiguo (STATUS_SENT si se pide enviar, DRAFT si no).
        $status = $this->status
            ?? ($this->has('invoiceSend') ? Invoice::STATUS_SENT : Invoice::STATUS_DRAFT);

        return collect($this->except('items', 'taxes'))
            ->merge([
                'creator_id' => $this->user()->id ?? null,
                'status' => $status,
                'paid_status' => Invoice::STATUS_UNPAID,
                'company_id' => $this->header('company'),
                'tax_per_item' => CompanySetting::getSetting('tax_per_item', $this->header('company')) ?? 'NO ',
                'discount_per_item' => CompanySetting::getSetting('discount_per_item', $this->header('company')) ?? 'NO',
                'due_amount' => $this->total,
                'sent' => (bool) $this->sent ?? false,
                'viewed' => (bool) $this->viewed ?? false,
                'exchange_rate' => $exchange_rate,
                'base_total' => $this->total * $exchange_rate,
                'base_discount_val' => $this->discount_val * $exchange_rate,
                'base_sub_total' => $this->sub_total * $exchange_rate,
                'base_tax' => $this->tax * $exchange_rate,
                'base_due_amount' => $this->total * $exchange_rate,
                'currency_id' => $currency,
            ])
            ->toArray();
    }
}
