<?php
namespace App\Http\Controllers\V1\Admin\ProformaInvoice;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendInvoiceRequest;
use App\Mail\SendInvoiceMail;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\ProformaInvoice;
use Illuminate\Support\Facades\Mail;
class SendProformaInvoiceController extends Controller
{
    public function __invoke(SendInvoiceRequest $request, ProformaInvoice $proforma_invoice)
    {
        $this->authorize('send proforma invoice', $proforma_invoice);
        $data = $request->all();
        $data['invoice'] = $proforma_invoice->toArray();
        $data['invoice']['invoice_number'] = $proforma_invoice->proforma_invoice_number;
        $data['customer'] = $proforma_invoice->customer->toArray();
        $data['company'] = Company::find($proforma_invoice->company_id);
        $data['subject'] = $proforma_invoice->getEmailString($data['subject'] ?? '');
        $data['body'] = $proforma_invoice->getEmailString($data['body'] ?? '');
        $attachPdf = CompanySetting::getSetting('invoice_email_attachment', $proforma_invoice->company_id) !== 'NO';
        $data['attach']['data'] = $attachPdf ? $proforma_invoice->getPDFData() : null;
        Mail::to($data['to'])->send(new SendInvoiceMail($data));
        if ($proforma_invoice->status == ProformaInvoice::STATUS_DRAFT) {
            $proforma_invoice->status = ProformaInvoice::STATUS_SENT;
            $proforma_invoice->sent = true;
            $proforma_invoice->save();
        }
        return response()->json(['success' => true]);
    }
}
