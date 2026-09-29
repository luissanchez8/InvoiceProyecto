<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'expense_date' => $this->expense_date,
            'amount' => $this->amount,
            'notes' => $this->notes,
            'customer_id' => $this->customer_id,
            'attachment_receipt_url' => $this->receipt_url,
            'attachment_receipt' => $this->receipt,
            'attachment_receipt_meta' => $this->receipt_meta,
            'company_id' => $this->company_id,
            'expense_category_id' => $this->expense_category_id,
            'creator_id' => $this->creator_id,
            'formatted_expense_date' => $this->formattedExpenseDate,
            'formatted_created_at' => $this->formattedCreatedAt,
            'exchange_rate' => $this->exchange_rate,
            'currency_id' => $this->currency_id,
            'base_amount' => $this->base_amount,
            'payment_method_id' => $this->payment_method_id,
            // Onfactu v.1.15.0: proveedor e IVA
            'proveedor_nombre' => $this->proveedor_nombre,
            'proveedor_nif' => $this->proveedor_nif,
            'numero_factura' => $this->numero_factura,
            'con_desglose' => (bool) $this->con_desglose,
            'base_imponible' => $this->base_imponible,
            'cuota_iva' => $this->cuota_iva,
            'cuota_deducible' => $this->cuota_deducible,
            'cuota_autoliquidada' => $this->cuota_autoliquidada,
            'retencion_porcentaje' => $this->retencion_porcentaje,
            'retencion' => $this->retencion,
            'lineas_iva' => $this->con_desglose
                ? $this->lineasIva->map(fn ($l) => [
                    'tipo' => $l->tipo, 'nombre' => $l->nombre, 'porcentaje' => $l->porcentaje,
                    'base' => $l->base, 'cuota' => $l->cuota,
                    'deducible' => $l->deducible, 'autoliquidacion' => $l->autoliquidacion,
                ])->values()
                : [],
            'customer' => $this->when($this->customer()->exists(), function () {
                return new CustomerResource($this->customer);
            }),
            'expense_category' => $this->when($this->category()->exists(), function () {
                return new ExpenseCategoryResource($this->category);
            }),
            'creator' => $this->when($this->creator()->exists(), function () {
                return new UserResource($this->creator);
            }),
            'fields' => $this->when($this->fields()->exists(), function () {
                return CustomFieldValueResource::collection($this->fields);
            }),
            'company' => $this->when($this->company()->exists(), function () {
                return new CompanyResource($this->company);
            }),
            'currency' => $this->when($this->currency()->exists(), function () {
                return new CurrencyResource($this->currency);
            }),
            'payment_method' => $this->when($this->paymentMethod()->exists(), function () {
                return new PaymentMethodResource($this->paymentMethod);
            }),
        ];
    }
}
