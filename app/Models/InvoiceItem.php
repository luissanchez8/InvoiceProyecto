<?php

namespace App\Models;

use App\Traits\HasCustomFieldsTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class InvoiceItem extends Model
{
    use HasCustomFieldsTrait;
    use HasFactory;

    protected $guarded = [
        'id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'total' => 'integer',
            'discount' => 'float',
            'quantity' => 'float',
            'discount_val' => 'integer',
            'tax' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class);
    }

    public function recurringInvoice(): BelongsTo
    {
        return $this->belongsTo(RecurringInvoice::class);
    }

    public function scopeWhereCompany($query, $company_id)
    {
        $query->where('company_id', $company_id);
    }

    public function scopeInvoicesBetween($query, $start, $end)
    {
        // Onfactu v.1.14.5: solo facturas aprobadas
        $query->whereHas('invoice', function ($query) use ($start, $end) {
            $query->where('status', Invoice::STATUS_APPROVED)->whereBetween(
                'invoice_date',
                [$start->format('Y-m-d'), $end->format('Y-m-d')]
            );
        });
    }

    public function scopeApplyInvoiceFilters($query, array $filters)
    {
        $filters = collect($filters);

        if ($filters->get('from_date') && $filters->get('to_date')) {
            $start = Carbon::createFromFormat('Y-m-d', $filters->get('from_date'));
            $end = Carbon::createFromFormat('Y-m-d', $filters->get('to_date'));
            $query->invoicesBetween($start, $end);
        }
    }

    public function scopeItemAttributes($query)
    {
        $query->select(
            // Onfactu v.1.18.0: la cantidad lleva el signo del importe. Las
            // rectificativas restan con precio negativo y cantidad positiva, y
            // sumaban horas en vez de quitarlas (Consultoría 12 en vez de 10).
            DB::raw('sum(CASE WHEN invoice_items.base_total < 0 THEN -ABS(invoice_items.quantity) WHEN invoice_items.base_total > 0 THEN ABS(invoice_items.quantity) ELSE invoice_items.quantity END) as total_quantity, sum(invoice_items.base_total) as total_amount, invoice_items.name')
        )->groupBy('invoice_items.name');
    }
}
