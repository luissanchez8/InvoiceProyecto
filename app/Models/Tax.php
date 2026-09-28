<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Tax extends Model
{
    use HasFactory;

    protected $guarded = [
        'id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'percent' => 'float',
            'fixed_amount' => 'integer',
        ];
    }

    public function taxType(): BelongsTo
    {
        return $this->belongsTo(TaxType::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recurringInvoice(): BelongsTo
    {
        return $this->belongsTo(RecurringInvoice::class);
    }

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function estimateItem(): BelongsTo
    {
        return $this->belongsTo(EstimateItem::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function scopeWhereCompany($query, $company_id)
    {
        $query->where('company_id', $company_id);
    }

    public function scopeTaxAttributes($query)
    {
        $query->select(
            DB::raw('sum(base_amount) as total_tax_amount, tax_type_id')
        )->groupBy('tax_type_id');
    }

    public function scopeInvoicesBetween($query, $start, $end)
    {
        // Onfactu v.1.14.5: el IVA se declara por la fecha de la factura, se
        // cobre o no, así que cuentan todas las aprobadas del periodo (antes,
        // solo las cobradas). Las dos condiciones van agrupadas para que el
        // filtro de empresa se aplique a las dos.
        $factura = function ($query) use ($start, $end) {
            $query->where('status', Invoice::STATUS_APPROVED)
                ->whereBetween(
                    'invoice_date',
                    [$start->format('Y-m-d'), $end->format('Y-m-d')]
                );
        };

        $query->where(function ($query) use ($factura) {
            $query->whereHas('invoice', $factura)
                ->orWhereHas('invoiceItem.invoice', $factura);
        });
    }

    public function scopeWhereInvoicesFilters($query, array $filters)
    {
        $filters = collect($filters);

        if ($filters->get('from_date') && $filters->get('to_date')) {
            $start = Carbon::createFromFormat('Y-m-d', $filters->get('from_date'));
            $end = Carbon::createFromFormat('Y-m-d', $filters->get('to_date'));

            $query->invoicesBetween($start, $end);
        }
    }
}
