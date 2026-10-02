<?php

namespace App\Http\Controllers\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\EstimateResource;
use App\Models\EmailLog;
use App\Models\Estimate;
use Illuminate\Http\Request;

class EstimatePdfController extends Controller
{
    public function getPdf(EmailLog $emailLog, Request $request)
    {
        $estimate = Estimate::find($emailLog->mailable_id);

        if (! $emailLog->isExpired()) {
            // v.1.18.0: no cuenta si lo abre alguien de la empresa con su sesión,
            // y el aviso es el de AvisoVisto (diseño de Onfactu)
            if ($estimate && ! auth()->check() && ($estimate->status == Estimate::STATUS_SENT || $estimate->status == Estimate::STATUS_DRAFT)) {
                $estimate->status = Estimate::STATUS_VIEWED;
                $estimate->save();
                \App\Services\AvisoVisto::presupuesto($estimate);
            }

            return $estimate->getGeneratedPDFOrStream('estimate');
        }

        abort(403, 'Link Expired.');
    }

    public function getEstimate(EmailLog $emailLog)
    {
        $estimate = Estimate::find($emailLog->mailable_id);

        return new EstimateResource($estimate);
    }
}
