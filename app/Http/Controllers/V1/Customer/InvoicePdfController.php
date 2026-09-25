<?php

namespace App\Http\Controllers\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\InvoiceResource as CustomerInvoiceResource;
use App\Mail\InvoiceViewedMail;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\EmailLog;
use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoicePdfController extends Controller
{
    public function getPdf(EmailLog $emailLog, Request $request)
    {
        $invoice = Invoice::find($emailLog->mailable_id);

        if (! $emailLog->isExpired()) {
            // Onfactu v.1.13: verla no cambia el estado; se apunta que la ha visto
            // y cuándo. El aviso a la empresa sale solo la primera vez.
            if ($invoice && ! $invoice->viewed) {
                $invoice->viewed = true;
                $invoice->viewed_at = now();
                $invoice->save();
                $notifyInvoiceViewed = CompanySetting::getSetting(
                    'notify_invoice_viewed',
                    $invoice->company_id
                );

                if ($notifyInvoiceViewed == 'YES') {
                    $data['invoice'] = Invoice::findOrFail($invoice->id)->toArray();
                    $data['user'] = Customer::find($invoice->customer_id)->toArray();
                    $notificationEmail = CompanySetting::getSetting(
                        'notification_email',
                        $invoice->company_id
                    );

                    \Mail::to($notificationEmail)->send(new InvoiceViewedMail($data));
                }
            }

            if ($request->has('pdf')) {
                return $invoice->getGeneratedPDFOrStream('invoice');
            }

            return view('app')->with([
                'customer_logo' => get_company_setting('customer_portal_logo', $invoice->company_id),
                'current_theme' => get_company_setting('customer_portal_theme', $invoice->company_id),
            ]);
        }

        abort(403, 'Link Expired.');
    }

    public function getInvoice(EmailLog $emailLog)
    {
        $invoice = Invoice::find($emailLog->mailable_id);

        return new CustomerInvoiceResource($invoice);
    }
}
