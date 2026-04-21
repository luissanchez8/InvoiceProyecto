<?php

/**
 * Request DeliveryNotesRequest — Validación de albaranes
 *
 * Valida los datos del formulario de creación/edición de albaranes.
 * Incluye el campo show_prices para controlar visibilidad de precios en PDF.
 *
 * Onfactu — numeración diferida:
 *  - delivery_note_number es OPCIONAL al crear/editar un borrador.
 *  - Si el usuario lo escribe, debe ser único en la empresa.
 *  - Si se deja vacío, se asigna automáticamente al enviar (SENT).
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
                'nullable',
                'string',
                'max:100',
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
            $deliveryNote = $this->route('delivery_note');

            // Si el albarán ya está en estado emitido (no DRAFT), el número
            // no se puede cambiar.
            if ($deliveryNote && $deliveryNote->status !== DeliveryNote::STATUS_DRAFT && ! empty($deliveryNote->delivery_note_number)) {
                $rules['delivery_note_number'] = [
                    'required',
                    Rule::in([$deliveryNote->delivery_note_number]),
                ];
            } else {
                $rules['delivery_note_number'] = [
                    'nullable',
                    'string',
                    'max:100',
                    Rule::unique('delivery_notes')
                        ->ignore($deliveryNote->id)
                        ->where('company_id', $this->header('company')),
                ];
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'delivery_note_number.unique' => 'Ya existe un albarán con ese número en esta empresa. Elige otro o deja el campo vacío para asignar uno automáticamente al enviar.',
            'delivery_note_number.in' => 'No se puede cambiar el número de un albarán ya enviado.',
        ];
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

        return collect($this->except('items', 'taxes'))
            ->merge([
                'creator_id' => $this->user()->id ?? null,
                'status' => $this->has('deliveryNoteSend')
                    ? DeliveryNote::STATUS_SENT
                    : DeliveryNote::STATUS_DRAFT,
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
