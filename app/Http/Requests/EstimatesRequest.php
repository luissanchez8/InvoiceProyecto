<?php

namespace App\Http\Requests;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Estimate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EstimatesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Onfactu — numeración diferida:
     *  - estimate_number es OPCIONAL al crear/editar un borrador.
     *  - Si el usuario lo escribe, debe ser único en la empresa.
     *  - Si se deja vacío, se asignará automáticamente al enviar (SENT).
     */
    public function rules(): array
    {
        $rules = [
            'estimate_date' => [
                'required',
            ],
            'expiry_date' => [
                'nullable',
            ],
            'customer_id' => [
                'required',
            ],
            'estimate_number' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('estimates')->where('company_id', $this->header('company')),
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
                'integer',
                'required',
            ],
            'total' => [
                'integer',
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
            'items.*.description' => [
                'nullable',
            ],
            'items.*' => [
                'required',
                'max:255',
            ],
            'items.*.name' => [
                'required',
            ],
            'items.*.quantity' => [
                'numeric',
                'required',
            ],
            'items.*.price' => [
                'integer',
                'required',
            ],
        ];

        $companyCurrency = CompanySetting::getSetting('currency', $this->header('company'));

        $customer = Customer::find($this->customer_id);

        if ($companyCurrency && $customer) {
            if ((string) $customer->currency_id !== $companyCurrency) {
                $rules['exchange_rate'] = [
                    'required',
                ];
            }
        }

        if ($this->isMethod('PUT')) {
            $estimate = $this->route('estimate');

            // Si el presupuesto ya está ENVIADO/ACEPTADO (no DRAFT), el número
            // no se puede cambiar (ya se comunicó al cliente).
            if ($estimate && $estimate->status !== Estimate::STATUS_DRAFT && ! empty($estimate->estimate_number)) {
                $rules['estimate_number'] = [
                    'required',
                    Rule::in([$estimate->estimate_number]),
                ];
            } else {
                $rules['estimate_number'] = [
                    'nullable',
                    'string',
                    'max:100',
                    Rule::unique('estimates')
                        ->ignore($estimate->id)
                        ->where('company_id', $this->header('company')),
                ];
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'estimate_number.unique' => 'Ya existe un presupuesto con ese número en esta empresa. Elige otro o deja el campo vacío para asignar uno automáticamente al enviar.',
            'estimate_number.in' => 'No se puede cambiar el número de un presupuesto ya enviado.',
        ];
    }

    public function getEstimatePayload()
    {
        $company_currency = CompanySetting::getSetting('currency', $this->header('company'));
        $current_currency = $this->currency_id;
        $exchange_rate = $company_currency != $current_currency ? $this->exchange_rate : 1;
        $currency = Customer::find($this->customer_id)->currency_id;

        return collect($this->except('items', 'taxes'))
            ->merge([
                'creator_id' => $this->user()->id ?? null,
                'status' => $this->has('estimateSend') ? Estimate::STATUS_SENT : Estimate::STATUS_DRAFT,
                'company_id' => $this->header('company'),
                'tax_per_item' => CompanySetting::getSetting('tax_per_item', $this->header('company')) ?? 'NO ',
                'discount_per_item' => CompanySetting::getSetting('discount_per_item', $this->header('company')) ?? 'NO',
                'exchange_rate' => $exchange_rate,
                'base_discount_val' => $this->discount_val * $exchange_rate,
                'base_sub_total' => $this->sub_total * $exchange_rate,
                'base_total' => $this->total * $exchange_rate,
                'base_tax' => $this->tax * $exchange_rate,
                'currency_id' => $currency,
            ])
            ->toArray();
    }
}
