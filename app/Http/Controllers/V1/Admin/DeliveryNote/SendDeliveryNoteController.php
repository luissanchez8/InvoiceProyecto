<?php

namespace App\Http\Controllers\V1\Admin\DeliveryNote;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendInvoiceRequest;
use App\Mail\SendInvoiceMail;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\DeliveryNote;
use Illuminate\Support\Facades\Mail;

class SendDeliveryNoteController extends Controller
{
    public function __invoke(SendInvoiceRequest $request, DeliveryNote $delivery_note)
    {
        $this->authorize('send delivery note', $delivery_note);

        $data = $request->all();
        $data['invoice'] = $delivery_note->toArray();
        $data['invoice']['invoice_number'] = $delivery_note->delivery_note_number;
        $data['customer'] = $delivery_note->customer->toArray();
        $data['company'] = Company::find($delivery_note->company_id);

        $attachPdf = CompanySetting::getSetting('invoice_email_attachment', $delivery_note->company_id) !== 'NO';
        $data['attach']['data'] = $attachPdf ? $delivery_note->getPDFData() : null;

        Mail::to($data['to'])->send(new SendInvoiceMail($data));

        if ($delivery_note->status == DeliveryNote::STATUS_DRAFT) {
            $delivery_note->status = DeliveryNote::STATUS_SENT;
            $delivery_note->sent = true;
            $delivery_note->save();
        }

        return response()->json(['success' => true]);
    }
}
