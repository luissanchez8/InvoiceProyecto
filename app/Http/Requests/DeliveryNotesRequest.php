<?php

/**
 * Request DeliveryNotesRequest — Validación de albaranes
 *
 * Valida los datos del formulario de creación/edición de albaranes.
 * Incluye el campo show_prices para controlar visibilidad de precios en PDF.
 */

namespace App\Http\Requests;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\DeliveryNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeliveryNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'delivery_note_date' => ['required'],
            'delivery_date' => ['nullable'],
            'customer_id' => ['required'],
            'delivery_note_number' => [
                // Onfactu: opcional si se guarda como borrador.
                Rule::requiredIf(fn () => ($this->status ?? null) !== DeliveryNote::STATUS_DRAFT),
                'nullable',
                Rule::unique('delivery_notes')->where('company_id', $this->header('company')),
            ],
            'exchange_rate' => ['nullable'],
            'discount' => ['numeric', 'required'],
            'discount_val' => ['integer', 'required'],
            'sub_total' => ['numeric', 'required'],
            'total' => ['numeric', 'max:999999999999', 'required'],
            'tax' => ['required'],
            'template_name' => ['required'],
            'show_prices' => ['boolean'],
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
            $rules['delivery_note_number'] = [
                // Onfactu: opcional si se sigue guardando como borrador.
                Rule::requiredIf(fn () => ($this->status ?? null) !== DeliveryNote::STATUS_DRAFT),
                'nullable',
                Rule::unique('delivery_notes')
                    ->ignore($this->route('delivery_note')->id)
                    ->where('company_id', $this->header('company')),
            ];
        }

        return $rules;
    }

    /**
     * Prepara el payload para DeliveryNote::createDeliveryNote()
     * Incluye show_prices para controlar visibilidad en PDF.
     */
    public function getDeliveryNotePayload(): array
    {
        $company_currency = CompanySetting::getSetting('currency', $this->header('company'));
        $current_currency = $this->currency_id;
        $exchange_rate = $company_currency != $current_currency ? $this->exchange_rate : 1;
        $currency = Customer::find($this->customer_id)->currency_id;

        // Onfactu: respetar el status que envía el frontend.
        $status = $this->status
            ?? ($this->has('deliveryNoteSend')
                ? DeliveryNote::STATUS_SENT
                : DeliveryNote::STATUS_DRAFT);

        return collect($this->except('items', 'taxes'))
            ->merge([
                'creator_id' => $this->user()->id ?? null,
                'status' => $status,
                'company_id' => $this->header('company'),
                'tax_per_item' => CompanySetting::getSetting('tax_per_item', $this->header('company')) ?? 'NO',
                'discount_per_item' => CompanySetting::getSetting('discount_per_item', $this->header('company')) ?? 'NO',
                'show_prices' => $this->show_prices ?? true,
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
