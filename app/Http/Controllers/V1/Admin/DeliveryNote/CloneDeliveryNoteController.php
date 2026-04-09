<?php

namespace App\Http\Controllers\V1\Admin\DeliveryNote;

use App\Http\Controllers\Controller;
use App\Models\DeliveryNote;
use App\Services\SerialNumberFormatter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Vinkla\Hashids\Facades\Hashids;

class CloneDeliveryNoteController extends Controller
{
    public function __invoke(Request $request, DeliveryNote $delivery_note)
    {
        $this->authorize('create', DeliveryNote::class);

        $serial = (new SerialNumberFormatter)
            ->setModel($delivery_note)
            ->setCompany($delivery_note->company_id)
            ->setCustomer($delivery_note->customer_id)
            ->setNextNumbers();

        $exchange_rate = $delivery_note->exchange_rate;

        $newNote = DeliveryNote::create([
            'delivery_note_date' => Carbon::now()->format('Y-m-d'),
            'delivery_date' => $delivery_note->delivery_date,
            'delivery_note_number' => $serial->getNextNumber(),
            'sequence_number' => $serial->nextSequenceNumber,
            'customer_sequence_number' => $serial->nextCustomerSequenceNumber,
            'reference_number' => $delivery_note->reference_number,
            'customer_id' => $delivery_note->customer_id,
            'company_id' => $request->header('company'),
            'template_name' => $delivery_note->template_name,
            'status' => DeliveryNote::STATUS_DRAFT,
            'sub_total' => $delivery_note->sub_total,
            'discount' => $delivery_note->discount,
            'discount_type' => $delivery_note->discount_type,
            'discount_val' => $delivery_note->discount_val,
            'total' => $delivery_note->total,
            'tax_per_item' => $delivery_note->tax_per_item,
            'discount_per_item' => $delivery_note->discount_per_item,
            'tax' => $delivery_note->tax,
            'notes' => $delivery_note->notes,
            'show_prices' => $delivery_note->show_prices,
            'exchange_rate' => $exchange_rate,
            'base_total' => $delivery_note->total * $exchange_rate,
            'base_discount_val' => $delivery_note->discount_val * $exchange_rate,
            'base_sub_total' => $delivery_note->sub_total * $exchange_rate,
            'base_tax' => $delivery_note->tax * $exchange_rate,
            'currency_id' => $delivery_note->currency_id,
            'sales_tax_type' => $delivery_note->sales_tax_type,
            'sales_tax_address_type' => $delivery_note->sales_tax_address_type,
        ]);

        $newNote->unique_hash = Hashids::connection(DeliveryNote::class)->encode($newNote->id);
        $newNote->save();

        $delivery_note->load('items.taxes');

        foreach ($delivery_note->items->toArray() as $item) {
            $item['company_id'] = $request->header('company');
            $item['exchange_rate'] = $exchange_rate;
            $item['base_price'] = $item['price'] * $exchange_rate;
            $item['base_discount_val'] = $item['discount_val'] * $exchange_rate;
            $item['base_tax'] = $item['tax'] * $exchange_rate;
            $item['base_total'] = $item['total'] * $exchange_rate;

            $newItem = $newNote->items()->create($item);

            if (! empty($item['taxes'])) {
                foreach ($item['taxes'] as $tax) {
                    $tax['company_id'] = $request->header('company');
                    if ($tax['amount']) {
                        $newItem->taxes()->create($tax);
                    }
                }
            }
        }

        if ($delivery_note->taxes) {
            foreach ($delivery_note->taxes->toArray() as $tax) {
                $tax['company_id'] = $request->header('company');
                $newNote->taxes()->create($tax);
            }
        }

        return response()->json([
            'success' => true,
            'delivery_note' => $newNote,
        ]);
    }
}
