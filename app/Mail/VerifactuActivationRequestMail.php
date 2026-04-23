<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifactuActivationRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public ?Company $company,
        public int $requestId,
    ) {}

    public function envelope(): Envelope
    {
        $subject = 'Solicitud activación VeriFactu #' . $this->requestId;

        if ($this->company && $this->company->name) {
            $subject .= ' - ' . $this->company->name;
        }

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verifactu_activation_request',
            with: [
                'user'      => $this->user,
                'company'   => $this->company,
                'requestId' => $this->requestId,
                'subdomain' => config('app.url'),
            ],
        );
    }
}
