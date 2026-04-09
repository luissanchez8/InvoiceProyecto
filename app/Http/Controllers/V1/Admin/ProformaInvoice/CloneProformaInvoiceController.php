<?php

namespace App\Http\Controllers\V1\Admin\ProformaInvoice;

use App\Http\Controllers\Controller;
use App\Models\ProformaInvoice;
use App\Services\SerialNumberFormatter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Vinkla\Hashids\Facades\Hashids;

class CloneProformaInvoiceController extends Controller
{
    public function __invoke(Request $request, ProformaInvoice $proforma_invoice)
    {
        $this->authorize('create', ProformaInvoice::class);

        $serial = (new SerialNumberFormatter)
            ->setModel($proforma_invoice)
            ->setCompany($proforma_invoice->company_id)
            ->setCustomer($proforma_invoice->customer_id)
            ->setNextNumbers();

        $exchange_rate = $proforma_invoice->exchange_rate;

        $newProforma = ProformaInvoice::create([
            'proforma_invoice_date' => Carbon::now()->format('Y-m-d'),
            'expiry_date' => $proforma_invoice->expiry_date,
            'proforma_invoice_number' => $serial->getNextNumber(),
            'sequence_number' => $serial->nextSequenceNumber,
            'customer_sequence_number' => $serial->nextCustomerSequenceNumber,
            'reference_number' => $proforma_invoice->reference_number,
            'customer_id' => $proforma_invoice->customer_id,
            'company_id' => $request->header('company'),
            'template_name' => $proforma_invoice->template_name,
            'status' => ProformaInvoice::STATUS_DRAFT,
            'sub_total' => $proforma_invoice->sub_total,
            'discount' => $proforma_invoice->discount,
            'discount_type' => $proforma_invoice->discount_type,
            'discount_val' => $proforma_invoice->discount_val,
            'total' => $proforma_invoice->total,
            'tax_per_item' => $proforma_invoice->tax_per_item,
            'discount_per_item' => $proforma_invoice->discount_per_item,
            'tax' => $proforma_invoice->tax,
            'notes' => $proforma_invoice->notes,
            'exchange_rate' => $exchange_rate,
            'base_total' => $proforma_invoice->total * $exchange_rate,
            'base_discount_val' => $proforma_invoice->discount_val * $exchange_rate,
            'base_sub_total' => $proforma_invoice->sub_total * $exchange_rate,
            'base_tax' => $proforma_invoice->tax * $exchange_rate,
            'currency_id' => $proforma_invoice->currency_id,
            'sales_tax_type' => $proforma_invoice->sales_tax_type,
            'sales_tax_address_type' => $proforma_invoice->sales_tax_address_type,
        ]);

        $newProforma->unique_hash = Hashids::connection(ProformaInvoice::class)->encode($newProforma->id);
        $newProforma->save();

        $proforma_invoice->load('items.taxes');

        foreach ($proforma_invoice->items->toArray() as $item) {
            $item['company_id'] = $request->header('company');
            $item['exchange_rate'] = $exchange_rate;
            $item['base_price'] = $item['price'] * $exchange_rate;
            $item['base_discount_val'] = $item['discount_val'] * $exchange_rate;
            $item['base_tax'] = $item['tax'] * $exchange_rate;
            $item['base_total'] = $item['total'] * $exchange_rate;

            $newItem = $newProforma->items()->create($item);

            if (! empty($item['taxes'])) {
                foreach ($item['taxes'] as $tax) {
                    $tax['company_id'] = $request->header('company');
                    if ($tax['amount']) {
                        $newItem->taxes()->create($tax);
                    }
                }
            }
        }

        if ($proforma_invoice->taxes) {
            foreach ($proforma_invoice->taxes->toArray() as $tax) {
                $tax['company_id'] = $request->header('company');
                $newProforma->taxes()->create($tax);
            }
        }

        return response()->json([
            'success' => true,
            'proforma_invoice' => $newProforma,
        ]);
    }
}
