<?php

namespace Tests\Unit;

use App\Jobs\WebhookDeliveryJob;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Vendor;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookDispatcherTest extends TestCase
{
    public function test_dispatch_event_resolves_vendor_via_merchant_relationship(): void
    {
        $vendor = Vendor::create([
            'name' => 'Dispatcher Vendor',
            'api_key' => hash('sha256', 'dispatcher-key'),
            'webhook_url' => 'https://vendor.example/hook',
            'webhook_secret' => 'secret',
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'MRC-DISP',
            'name' => 'Dispatcher Merchant',
            'tin' => '123-456-789-000',
        ]);

        $invoice = Invoice::create([
            'bridge_transaction_id' => 'EB-DISP-001',
            'transaction_id' => 'POS-DISP-001',
            'merchant_code' => $merchant->merchant_code,
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => ['transaction_id' => 'POS-DISP-001'],
            'processing_status' => 'sent',
            'eis_status' => 'acknowledged',
            'eis_reference_no' => 'EIS-DISP',
        ]);

        Queue::fake();

        WebhookDispatcher::dispatchEvent($invoice, 'transaction.eis_acknowledged');

        Queue::assertPushed(WebhookDeliveryJob::class, function (WebhookDeliveryJob $job) use ($vendor, $invoice) {
            return $job->vendorId === $vendor->id
                && $job->invoiceId === $invoice->id
                && $job->event === 'transaction.eis_acknowledged'
                && $job->payload['event'] === 'transaction.eis_acknowledged'
                && $job->payload['data']['bridge_transaction_id'] === $invoice->bridge_transaction_id;
        });
    }

    public function test_dispatch_event_skips_when_vendor_has_no_webhook_url(): void
    {
        $vendor = Vendor::create([
            'name' => 'No Hook Vendor',
            'api_key' => hash('sha256', 'no-hook-key'),
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'MRC-NOHOOK',
            'name' => 'No Hook Merchant',
            'tin' => '123-456-789-000',
        ]);

        $invoice = Invoice::create([
            'bridge_transaction_id' => 'EB-NOHOOK-001',
            'transaction_id' => 'POS-NOHOOK-001',
            'merchant_code' => $merchant->merchant_code,
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => ['transaction_id' => 'POS-NOHOOK-001'],
            'processing_status' => 'sent',
        ]);

        Queue::fake();

        WebhookDispatcher::dispatchEvent($invoice, 'transaction.eis_acknowledged');

        Queue::assertNothingPushed();
    }
}
