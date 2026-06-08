<?php

namespace App\Services\Webhooks;

use App\Jobs\WebhookDeliveryJob;
use App\Models\CertificateAlert;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Vendor;

class WebhookDispatcher
{
    public static function dispatchEvent(Invoice $invoice, string $event): void
    {
        $vendor = self::resolveVendor($invoice);

        if (! $vendor || empty($vendor->webhook_url)) {
            return;
        }

        $payload = [
            'event' => $event,
            'data' => [
                'bridge_transaction_id' => $invoice->bridge_transaction_id,
                'transaction_id' => $invoice->transaction_id,
                'eis_status' => $invoice->eis_status,
                'eis_reference_no' => $invoice->eis_reference_no,
            ],
        ];

        WebhookDeliveryJob::dispatch($vendor->id, $invoice->id, $event, $payload)
            ->onQueue('webhooks');
    }

    public function dispatchForInvoice(Invoice $invoice, string $event): void
    {
        self::dispatchEvent($invoice, $event);
    }

    public function dispatchCertificateExpiryAlert(Merchant $merchant, CertificateAlert $alert): void
    {
        $vendor = $merchant->vendor;

        if (! $vendor || empty($vendor->webhook_url)) {
            return;
        }

        $certificate = $alert->certificate;

        $payload = [
            'event' => 'certificate.expiry_alert',
            'data' => [
                'merchant' => $merchant->name,
                'merchant_code' => $merchant->merchant_code,
                'level' => $alert->level,
                'expires_at' => $certificate?->expires_at?->toDateString(),
            ],
        ];

        WebhookDeliveryJob::dispatch($vendor->id, null, 'certificate.expiry_alert', $payload)
            ->onQueue('webhooks');
    }

    public function dispatchTest(Vendor $vendor): void
    {
        if (empty($vendor->webhook_url)) {
            return;
        }

        $payload = [
            'event' => 'webhook.test',
            'data' => [
                'message' => 'Test webhook from EIS Bridge.',
                'vendor_id' => $vendor->id,
            ],
        ];

        WebhookDeliveryJob::dispatch($vendor->id, null, 'webhook.test', $payload)
            ->onQueue('webhooks');
    }

    private static function resolveVendor(Invoice $invoice): ?Vendor
    {
        $merchant = Merchant::where('merchant_code', $invoice->merchant_code)->first();

        return $merchant?->vendor;
    }
}
