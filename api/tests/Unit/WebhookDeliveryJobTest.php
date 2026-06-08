<?php

namespace Tests\Unit;

use App\Jobs\WebhookDeliveryJob;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Vendor;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookDeliveryJobTest extends TestCase
{
    private function seedVendorWithInvoice(): array
    {
        $vendor = Vendor::create([
            'name' => 'Webhook Vendor',
            'api_key' => hash('sha256', 'webhook-key'),
            'webhook_url' => 'https://vendor.example/webhooks/eis',
            'webhook_secret' => 'super-secret-key',
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'MRC-WH',
            'name' => 'Webhook Merchant',
            'tin' => '123-456-789-000',
        ]);

        $invoice = Invoice::create([
            'bridge_transaction_id' => 'EB-WH-001',
            'transaction_id' => 'POS-WH-001',
            'merchant_code' => $merchant->merchant_code,
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => ['transaction_id' => 'POS-WH-001'],
            'processing_status' => 'sent',
            'eis_status' => 'acknowledged',
            'eis_reference_no' => 'EIS-REF-WH',
        ]);

        return [$vendor, $invoice];
    }

    public function test_delivery_sends_hmac_signature_and_logs_success(): void
    {
        [$vendor, $invoice] = $this->seedVendorWithInvoice();

        $payload = [
            'event' => 'transaction.eis_acknowledged',
            'data' => [
                'bridge_transaction_id' => $invoice->bridge_transaction_id,
                'transaction_id' => $invoice->transaction_id,
                'eis_status' => $invoice->eis_status,
                'eis_reference_no' => $invoice->eis_reference_no,
            ],
        ];

        Http::fake(function ($request) use ($vendor, $payload) {
            $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $expectedSignature = hash_hmac('sha256', $body, $vendor->webhook_secret);

            $this->assertSame('https://vendor.example/webhooks/eis', $request->url());
            $this->assertSame($expectedSignature, $request->header('X-EISBridge-Signature')[0] ?? null);

            return Http::response(['ok' => true], 200);
        });

        (new WebhookDeliveryJob($vendor->id, $invoice->id, 'transaction.eis_acknowledged', $payload))->handle();

        $delivery = WebhookDelivery::first();

        $this->assertNotNull($delivery);
        $this->assertSame($vendor->id, $delivery->vendor_id);
        $this->assertSame($invoice->id, $delivery->invoice_id);
        $this->assertSame('transaction.eis_acknowledged', $delivery->event);
        $this->assertSame(200, $delivery->status_code);
        $this->assertTrue($delivery->success);
    }

    public function test_delivery_logs_failure_and_marks_job_failed_for_retry(): void
    {
        [$vendor, $invoice] = $this->seedVendorWithInvoice();

        Http::fake([
            'https://vendor.example/*' => Http::response('server error', 500),
        ]);

        $job = new WebhookDeliveryJob($vendor->id, $invoice->id, 'transaction.eis_rejected', [
            'event' => 'transaction.eis_rejected',
            'data' => [
                'bridge_transaction_id' => $invoice->bridge_transaction_id,
                'transaction_id' => $invoice->transaction_id,
                'eis_status' => 'failed',
                'eis_reference_no' => null,
            ],
        ]);

        try {
            $job->handle();
            $this->fail('Expected webhook delivery to fail.');
        } catch (\RuntimeException) {
            // expected
        }

        $delivery = WebhookDelivery::first();

        $this->assertNotNull($delivery);
        $this->assertSame(500, $delivery->status_code);
        $this->assertFalse($delivery->success);
    }

    public function test_backoff_uses_thirty_seconds_per_attempt(): void
    {
        $job = new WebhookDeliveryJob(1, null, 'webhook.test');

        $this->assertSame([30, 60, 90, 120], $job->backoff());
    }
}
