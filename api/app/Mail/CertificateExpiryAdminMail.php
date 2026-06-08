<?php

namespace App\Mail;

use App\Models\CertificateAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CertificateExpiryAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CertificateAlert $alert) {}

    public function envelope(): Envelope
    {
        $merchant = $this->alert->certificate?->merchant?->name ?? 'Unknown merchant';

        return new Envelope(
            subject: "Certificate expiry alert: {$merchant} ({$this->alert->level})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.certificate-expiry-admin',
        );
    }
}
