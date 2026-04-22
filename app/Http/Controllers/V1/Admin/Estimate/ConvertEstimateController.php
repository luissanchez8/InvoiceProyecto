<?php

namespace App\Http\Controllers\V1\Admin\Estimate;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\CompanySetting;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Services\SerialNumberFormatter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Vinkla\Hashids\Facades\Hashids;

class ConvertEstimateController extends Controller
{
    /**
     * Convierte un presupuesto en factura.
     *
     * Onfactu — numeración diferida:
     * La factura resultante nace como BORRADOR SIN NÚMERO. El número se
     * asignará cuando el usuario la apruebe (VeriFactu) llamando a
     * Invoice::assignNumber().
     *
     * Antes de este cambio, este controller asignaba directamente
     * invoice_number + sequence_number con SerialNumberFormatter, lo que
     * podía duplicar números si ya existía un borrador con el mismo
     * número (la conversión se saltaba la validación de unicidad y la
     * lógica de salto sobre ocupados).
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request, Estimate $estimate, Invoice $invoice)
    {
        $this->authorize('create', Invoice::class);

        $estimate->load(['items', 'items.taxes', 'customer', 'taxes']);

        $invoice_date = Carbon::now();
        $due_date = null;

        $dueDateEnabled = CompanySetting::getSetting(
            'invoice_set_due_date_automatically',
            $request->header('company')
        );

        if ($dueDateEnabled === 'YES') {
            $dueDateDays = intval(CompanySetting::getSetting(
                'invoice_due_date_days',
                $request->header('company')
            ));
            $due_date = Carbon::now()->addDays($dueDateDays)->format('Y-m-d');
        }

        $templateName = $estimate->getInvoiceTemplateName();

        $exchange_rate = $estimate->exchange_rate;

        $invoice = Invoice::create([
            'creator_id' => Auth::id(),
            'invoice_date' => $invoice_date->format('Y-m-d'),
            'due_date' => $due_date,
            // Numeración diferida: nace sin número. Se asignará al aprobar.
            'invoice_number' => null,
            'sequence_number' => null,
            'customer_sequence_number' => null,
            'reference_number' => null,
            'customer_id' => $estimate->customer_id,
            'company_id' => $request->header('company'),
            'template_name' => $templateName,
            'status' => Invoice::STATUS_DRAFT,
            'paid_status' => Invoice::STATUS_UNPAID,
            'sub_total' => $estimate->sub_total,
            'discount' => $estimate->discount,
            'discount_type' => $estimate->discount_type,
            'discount_val' => $estimate->discount_val,
            'total' => $estimate->total,
            'due_amount' => $estimate->total,
            'tax_per_item' => $estimate->tax_per_item,
            'discount_per_item' => $estimate->discount_per_item,
            'tax' => $estimate->tax,
            'notes' => $estimate->notes,
            'exchange_rate' => $exchange_rate,
            'base_discount_val' => $estimate->discount_val * $exchange_rate,
            'base_sub_total' => $estimate->sub_total * $exchange_rate,
            'base_total' => $estimate->total * $exchange_rate,
            'base_tax' => $estimate->tax * $exchange_rate,
            'currency_id' => $estimate->currency_id,
            'sales_tax_type' => $estimate->sales_tax_type,
            'sales_tax_address_type' => $estimate->sales_tax_address_type,
        ]);

        $invoice->unique_hash = Hashids::connection(Invoice::class)->encode($invoice->id);
        $invoice->save();
        $invoiceItems = $estimate->items->toArray();

        foreach ($invoiceItems as $invoiceItem) {
            $invoiceItem['company_id'] = $request->header('company');
            $invoiceItem['name'] = $invoiceItem['name'];
            $estimateItem['exchange_rate'] = $exchange_rate;
            $estimateItem['base_price'] = $invoiceItem['price'] * $exchange_rate;
            $estimateItem['base_discount_val'] = $invoiceItem['discount_val'] * $exchange_rate;
            $estimateItem['base_tax'] = $invoiceItem['tax'] * $exchange_rate;
            $estimateItem['base_total'] = $invoiceItem['total'] * $exchange_rate;

            $item = $invoice->items()->create($invoiceItem);

            if (array_key_exists('taxes', $invoiceItem) && $invoiceItem['taxes']) {
                foreach ($invoiceItem['taxes'] as $tax) {
                    $tax['company_id'] = $request->header('company');

                    if ($tax['amount']) {
                        $item->taxes()->create($tax);
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

                $invoice->taxes()->create($tax);
            }
        }

        $estimate->checkForEstimateConvertAction();

        $invoice = Invoice::find($invoice->id);

        return new InvoiceResource($invoice);
    }
}
