<?php

namespace App\Jobs;

use App\Mail\CertificateExpiryAdminMail;
use App\Models\CertificateAlert;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendCertificateAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $certificateAlertId) {}

    public function handle(WebhookDispatcher $webhookDispatcher): void
    {
        $alert = CertificateAlert::query()
            ->with(['certificate.merchant.vendor'])
            ->find($this->certificateAlertId);

        if (! $alert || ! $alert->certificate) {
            return;
        }

        $merchant = $alert->certificate->merchant;
        $adminEmail = config('alerts.admin_email');

        if ($adminEmail && ! $alert->notified_admin) {
            Mail::to($adminEmail)->send(new CertificateExpiryAdminMail($alert));
            $alert->update(['notified_admin' => true]);
        }

        if (! $alert->notified_vendor && $merchant) {
            $webhookDispatcher->dispatchCertificateExpiryAlert($merchant, $alert);
            $alert->update(['notified_vendor' => true]);
        }
    }
}
