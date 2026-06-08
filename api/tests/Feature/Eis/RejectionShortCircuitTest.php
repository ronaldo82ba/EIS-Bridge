<?php

namespace Tests\Feature\Eis;

use App\Jobs\RetryFailedTransmissionJob;
use App\Jobs\TransmitInvoiceJob;
use App\Jobs\WebhookDeliveryJob;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Vendor;
use App\Services\Eis\EisClient;
use App\Services\Eis\EisResponseParser;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class RejectionShortCircuitTest extends TestCase
{
    public function test_parser_treats_http_200_rejected_body_as_failure(): void
    {
        $parser = new EisResponseParser;

        $result = $parser->parse([
            'status' => 'rejected',
            'message' => 'Invalid TIN format',
        ], 200);

        $this->assertFalse($result['success']);
        $this->assertSame('rejected', $result['eis_status']);
    }

    public function test_parser_treats_http_200_acknowledged_body_as_success(): void
    {
        $parser = new EisResponseParser;

        $result = $parser->parse([
            'status' => 'acknowledged',
            'reference_no' => 'EIS-REF-001',
        ], 200);

        $this->assertTrue($result['success']);
        $this->assertSame('acknowledged', $result['eis_status']);
    }

    public function test_parser_treats_http_422_validation_failed_as_rejection(): void
    {
        $parser = new EisResponseParser;

        $result = $parser->parse([
            'status' => 'validation_failed',
            'message' => 'Invalid document type',
        ], 422);

        $this->assertFalse($result['success']);
        $this->assertSame('rejected', $result['eis_status']);
    }

    public function test_parser_treats_http_503_as_transient_failure(): void
    {
        $parser = new EisResponseParser;

        $result = $parser->parse([
            'status' => 'error',
            'message' => 'Service unavailable',
        ], 503);

        $this->assertFalse($result['success']);
        $this->assertSame('failed', $result['eis_status']);
    }

    public function test_eis_rejection_skips_retry_and_fires_rejected_webhook_only(): void
    {
        $vendor = Vendor::create([
            'name' => 'Reject Vendor',
            'api_key' => hash('sha256', 'reject-key'),
            'webhook_url' => 'https://vendor.example/hook',
            'webhook_secret' => 'secret',
            'status' => 'active',
        ]);

        Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'MRC-REJ',
            'name' => 'Reject Merchant',
            'tin' => '123-456-789-000',
        ]);

        $invoice = Invoice::create([
            'bridge_transaction_id' => 'EB-REJ-001',
            'transaction_id' => 'POS-REJ-001',
            'merchant_code' => 'MRC-REJ',
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => ['transaction_id' => 'POS-REJ-001'],
            'signed_json' => ['payload' => ['transaction_id' => 'POS-REJ-001'], 'signature' => 'sig'],
            'processing_status' => 'signed',
        ]);

        $client = Mockery::mock(EisClient::class);
        $client->shouldReceive('send')
            ->once()
            ->andReturn([
                'success' => false,
                'eis_status' => 'rejected',
                'eis_reference_no' => null,
                'error' => 'Rejected by EIS',
            ]);

        $this->app->instance(EisClient::class, $client);

        Queue::fake([RetryFailedTransmissionJob::class, WebhookDeliveryJob::class]);

        (new TransmitInvoiceJob($invoice->id))->handle($client);

        $invoice->refresh();

        $this->assertSame('transmission_failed', $invoice->processing_status);
        $this->assertSame('rejected', $invoice->eis_status);

        Queue::assertNotPushed(RetryFailedTransmissionJob::class);
        Queue::assertPushed(WebhookDeliveryJob::class, function (WebhookDeliveryJob $job) {
            return $job->event === 'transaction.eis_rejected';
        });
        Queue::assertNotPushed(WebhookDeliveryJob::class, function (WebhookDeliveryJob $job) {
            return $job->event === 'transaction.eis_failed';
        });
    }

    public function test_transient_failure_dispatches_retry_and_fires_failed_webhook(): void
    {
        $vendor = Vendor::create([
            'name' => 'Fail Vendor',
            'api_key' => hash('sha256', 'fail-key'),
            'webhook_url' => 'https://vendor.example/hook',
            'webhook_secret' => 'secret',
            'status' => 'active',
        ]);

        Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'MRC-FAIL',
            'name' => 'Fail Merchant',
            'tin' => '123-456-789-000',
        ]);

        $invoice = Invoice::create([
            'bridge_transaction_id' => 'EB-FAIL-001',
            'transaction_id' => 'POS-FAIL-001',
            'merchant_code' => 'MRC-FAIL',
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => ['transaction_id' => 'POS-FAIL-001'],
            'signed_json' => ['payload' => ['transaction_id' => 'POS-FAIL-001'], 'signature' => 'sig'],
            'processing_status' => 'signed',
        ]);

        $client = Mockery::mock(EisClient::class);
        $client->shouldReceive('send')
            ->once()
            ->andReturn([
                'success' => false,
                'eis_status' => 'failed',
                'eis_reference_no' => null,
                'error' => 'Temporary outage',
            ]);

        $this->app->instance(EisClient::class, $client);

        Queue::fake([RetryFailedTransmissionJob::class, WebhookDeliveryJob::class]);

        (new TransmitInvoiceJob($invoice->id))->handle($client);

        $invoice->refresh();

        $this->assertSame('transmission_failed', $invoice->processing_status);
        $this->assertSame('failed', $invoice->eis_status);

        Queue::assertPushed(RetryFailedTransmissionJob::class, function (RetryFailedTransmissionJob $job) use ($invoice) {
            return $job->invoiceId === $invoice->id && $job->attempt === 1;
        });
        Queue::assertPushed(WebhookDeliveryJob::class, function (WebhookDeliveryJob $job) {
            return $job->event === 'transaction.eis_failed';
        });
        Queue::assertNotPushed(WebhookDeliveryJob::class, function (WebhookDeliveryJob $job) {
            return $job->event === 'transaction.eis_rejected';
        });
    }
}
