<?php

namespace App\Http\Controllers\V1\Admin\Estimate;

use App\Http\Controllers\Controller;
use App\Models\DeliveryNote;
use App\Models\Estimate;
use App\Services\SerialNumberFormatter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Vinkla\Hashids\Facades\Hashids;

class ConvertEstimateToDeliveryNoteController extends Controller
{
    public function __invoke(Request $request, Estimate $estimate)
    {
        $this->authorize('create', DeliveryNote::class);

        $estimate->load(['items', 'items.taxes', 'customer', 'taxes']);

        $exchange_rate = $estimate->exchange_rate;

        $serial = (new SerialNumberFormatter)
            ->setModel(new DeliveryNote)
            ->setCompany($estimate->company_id)
            ->setCustomer($estimate->customer_id)
            ->setNextNumbers();

        $deliveryNote = DeliveryNote::create([
            'creator_id' => Auth::id(),
            'delivery_note_date' => Carbon::now()->format('Y-m-d'),
            'delivery_date' => null,
            'delivery_note_number' => $serial->getNextNumber(),
            'sequence_number' => $serial->nextSequenceNumber,
            'customer_sequence_number' => $serial->nextCustomerSequenceNumber,
            'reference_number' => $estimate->reference_number,
            'customer_id' => $estimate->customer_id,
            'company_id' => $request->header('company'),
            'template_name' => 'invoice4',
            'status' => DeliveryNote::STATUS_DRAFT,
            'sub_total' => $estimate->sub_total,
            'discount' => $estimate->discount,
            'discount_type' => $estimate->discount_type,
            'discount_val' => $estimate->discount_val,
            'total' => $estimate->total,
            'tax_per_item' => $estimate->tax_per_item,
            'discount_per_item' => $estimate->discount_per_item,
            'tax' => $estimate->tax,
            'notes' => $estimate->notes,
            'show_prices' => true,
            'exchange_rate' => $exchange_rate,
            'base_discount_val' => $estimate->discount_val * $exchange_rate,
            'base_sub_total' => $estimate->sub_total * $exchange_rate,
            'base_total' => $estimate->total * $exchange_rate,
            'base_tax' => $estimate->tax * $exchange_rate,
            'currency_id' => $estimate->currency_id,
            'sales_tax_type' => $estimate->sales_tax_type,
            'sales_tax_address_type' => $estimate->sales_tax_address_type,
        ]);

        $deliveryNote->unique_hash = Hashids::connection(DeliveryNote::class)->encode($deliveryNote->id);
        $deliveryNote->save();

        foreach ($estimate->items->toArray() as $item) {
            $item['company_id'] = $request->header('company');
            $item['exchange_rate'] = $exchange_rate;
            $item['base_price'] = $item['price'] * $exchange_rate;
            $item['base_discount_val'] = $item['discount_val'] * $exchange_rate;
            $item['base_tax'] = $item['tax'] * $exchange_rate;
            $item['base_total'] = $item['total'] * $exchange_rate;

            $newItem = $deliveryNote->items()->create($item);

            if (! empty($item['taxes'])) {
                foreach ($item['taxes'] as $tax) {
                    $tax['company_id'] = $request->header('company');
                    if ($tax['amount']) {
                        $newItem->taxes()->create($tax);
                    }
                }
            }
        }

        if ($estimate->taxes) {
            foreach ($estimate->taxes->toArray() as $tax) {
                $tax['company_id'] = $request->header('company');
                $tax['exchange_rate'] = $exchange_rate;
                $tax['base_amount'] = $tax['amount'] * $exchange_rate;
                $tax['currency_id'] = $estimate->currency_id;
                unset($tax['estimate_id']);
                $deliveryNote->taxes()->create($tax);
            }
        }

        $estimate->checkForEstimateConvertAction();

        return response()->json([
            'success' => true,
            'delivery_note' => DeliveryNote::with(['items', 'customer', 'taxes'])->find($deliveryNote->id),
        ]);
    }
}
