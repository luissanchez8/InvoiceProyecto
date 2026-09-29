<?php

namespace App\Http\Requests;

use App\Models\CompanySetting;
use App\Support\IvaGastos;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class ExpenseRequest extends FormRequest
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
     */
    public function rules(): array
    {
        $companyCurrency = CompanySetting::getSetting('currency', $this->header('company'));

        $rules = [
            'expense_date' => [
                'required',
            ],
            'expense_category_id' => [
                'required',
            ],
            'exchange_rate' => [
                'nullable',
            ],
            'payment_method_id' => [
                'nullable',
            ],
            // Onfactu v.1.15.0: con el IVA desglosado, el total lo calcula el servidor
            'amount' => [
                Rule::requiredIf(fn () => ! $this->conDesglose()),
            ],
            'con_desglose' => ['nullable'],
            'lineas_iva' => ['nullable'],
            'retencion_porcentaje' => ['nullable', 'numeric'],
            'proveedor_nombre' => ['nullable', 'string', 'max:190'],
            'proveedor_nif' => ['nullable', 'string', 'max:30'],
            'numero_factura' => ['nullable', 'string', 'max:60'],
            'customer_id' => [
                'nullable',
            ],
            'notes' => [
                'nullable',
            ],
            'currency_id' => [
                'required',
            ],
            'attachment_receipt' => [
                'nullable',
                'file',
                'mimes:jpg,png,pdf,doc,docx,xls,xlsx,ppt,pptx',
                'max:20000',
            ],
        ];

        if ($companyCurrency && $this->currency_id) {
            if ($companyCurrency !== $this->currency_id) {
                $rules['exchange_rate'] = [
                    'required',
                ];
            }
        }

        return $rules;
    }

    public function getExpensePayload()
    {
        $company_currency = CompanySetting::getSetting('currency', $this->header('company'));
        $current_currency = $this->currency_id;
        $exchange_rate = $company_currency != $current_currency ? $this->exchange_rate : 1;

        $desglose = $this->desglose();
        $amount = $desglose ? $desglose['total'] : $this->amount;

        return collect($this->validated())
            ->except(['lineas_iva', 'con_desglose', 'retencion_porcentaje'])
            ->merge([
                'creator_id' => $this->user()->id,
                'company_id' => $this->header('company'),
                'exchange_rate' => $exchange_rate,
                'amount' => $amount,
                'base_amount' => $amount * $exchange_rate,
                'currency_id' => $current_currency,
                'proveedor_nombre' => $this->textoLimpio('proveedor_nombre'),
                'proveedor_nif' => $this->nifLimpio(),
                'numero_factura' => $this->textoLimpio('numero_factura'),
                // Onfactu v.1.15.0: totales del desglose, o vacíos si no lo tiene
                'con_desglose' => (bool) $desglose,
                'base_imponible' => $desglose['base_imponible'] ?? null,
                'cuota_iva' => $desglose['cuota_iva'] ?? null,
                'cuota_deducible' => $desglose['cuota_deducible'] ?? null,
                'cuota_autoliquidada' => $desglose['cuota_autoliquidada'] ?? null,
                'retencion_porcentaje' => $desglose ? $desglose['retencion_porcentaje'] : null,
                'retencion' => $desglose['retencion'] ?? null,
            ])
            ->toArray();
    }

    /** Onfactu v.1.15.0: si el gasto viene con el IVA desglosado. */
    public function conDesglose(): bool
    {
        return filter_var($this->input('con_desglose', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Onfactu v.1.15.0: el desglose calculado por el servidor, o null si el
     * gasto va sin desglose. Las líneas llegan en JSON, porque el formulario
     * se envía como multipart por el justificante.
     */
    public function desglose(): ?array
    {
        if (! $this->conDesglose()) {
            return null;
        }

        return $this->desgloseCalculado ??= IvaGastos::calcular(
            $this->lineasRecibidas(),
            $this->input('retencion_porcentaje', 0)
        );
    }

    private ?array $desgloseCalculado = null;

    private function lineasRecibidas(): array
    {
        $lineas = $this->input('lineas_iva', []);
        if (is_string($lineas)) {
            $lineas = json_decode($lineas, true);
        }

        return is_array($lineas) ? $lineas : [];
    }

    private function textoLimpio(string $campo): ?string
    {
        $v = trim((string) $this->input($campo, ''));

        return $v === '' ? null : $v;
    }

    private function nifLimpio(): ?string
    {
        $v = strtoupper(preg_replace('/[\s\-\.]/', '', (string) $this->input('proveedor_nif', '')));

        return $v === '' ? null : $v;
    }
}
