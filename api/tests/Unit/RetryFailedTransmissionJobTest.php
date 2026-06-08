<?php

namespace Tests\Unit;

use App\Jobs\RetryFailedTransmissionJob;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Vendor;
use App\Services\Eis\EisClient;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class RetryFailedTransmissionJobTest extends TestCase
{
    private function seedInvoice(): Invoice
    {
        $vendor = Vendor::create([
            'name' => 'Retry Vendor',
            'api_key' => hash('sha256', 'retry-key'),
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'MRC-RETRY',
            'name' => 'Retry Merchant',
            'tin' => '123-456-789-000',
        ]);

        $branch = Branch::create([
            'merchant_id' => $merchant->id,
            'branch_code' => 'BR001',
            'name' => 'Main Branch',
        ]);

        Device::create([
            'branch_id' => $branch->id,
            'pos_device_id' => 'POS01',
            'name' => 'POS Terminal 01',
        ]);

        return Invoice::create([
            'bridge_transaction_id' => 'EB-RETRY-001',
            'transaction_id' => 'POS-RETRY-001',
            'merchant_code' => 'MRC-RETRY',
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => ['transaction_id' => 'POS-RETRY-001'],
            'signed_json' => ['payload' => ['transaction_id' => 'POS-RETRY-001'], 'signature' => 'sig'],
            'processing_status' => 'transmission_failed',
            'eis_status' => 'failed',
        ]);
    }

    public function test_calculate_backoff_uses_configured_schedule(): void
    {
        config([
            'eis.retry_backoff' => [60, 300, 900, 3600, 7200],
        ]);

        $job = new RetryFailedTransmissionJob(1, 1);

        $this->assertSame(60, $job->calculateBackoff(1));
        $this->assertSame(300, $job->calculateBackoff(2));
        $this->assertSame(7200, $job->calculateBackoff(99));
    }

    public function test_rejection_finalizes_immediately_without_retry(): void
    {
        config(['eis.retry_max_attempts' => 5]);

        $invoice = $this->seedInvoice();

        $client = Mockery::mock(EisClient::class);
        $client->shouldReceive('send')
            ->once()
            ->andReturn([
                'success' => false,
                'eis_status' => 'rejected',
                'eis_reference_no' => null,
                'error' => 'EIS rejected',
            ]);

        $this->app->instance(EisClient::class, $client);

        Queue::fake();

        (new RetryFailedTransmissionJob($invoice->id, 2))->handle($client);

        $invoice->refresh();

        $this->assertSame('transmission_failed', $invoice->processing_status);
        $this->assertSame('rejected', $invoice->eis_status);
        Queue::assertNothingPushed();
    }

    public function test_max_attempts_sets_retry_failed_status(): void
    {
        config(['eis.retry_max_attempts' => 1]);

        $invoice = $this->seedInvoice();

        $client = Mockery::mock(EisClient::class);
        $client->shouldReceive('send')
            ->once()
            ->andReturn([
                'success' => false,
                'eis_status' => 'failed',
                'eis_reference_no' => null,
                'error' => 'EIS unavailable',
            ]);

        $this->app->instance(EisClient::class, $client);

        Queue::fake();

        (new RetryFailedTransmissionJob($invoice->id, 1))->handle($client);

        $invoice->refresh();

        $this->assertSame('retry_failed', $invoice->processing_status);
        $this->assertSame('failed', $invoice->eis_status);
        Queue::assertNothingPushed();
    }

    public function test_failure_below_max_dispatches_next_retry(): void
    {
        config(['eis.retry_max_attempts' => 5]);

        $invoice = $this->seedInvoice();

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

        Queue::fake();

        (new RetryFailedTransmissionJob($invoice->id, 2))->handle($client);

        Queue::assertPushed(RetryFailedTransmissionJob::class, function (RetryFailedTransmissionJob $job) use ($invoice) {
            return $job->invoiceId === $invoice->id && $job->attempt === 3;
        });
    }

    public function test_success_updates_invoice_and_does_not_retry(): void
    {
        $invoice = $this->seedInvoice();

        $client = Mockery::mock(EisClient::class);
        $client->shouldReceive('send')
            ->once()
            ->andReturn([
                'success' => true,
                'eis_status' => 'acknowledged',
                'eis_reference_no' => 'EIS-REF-123',
            ]);

        $this->app->instance(EisClient::class, $client);

        Queue::fake();

        (new RetryFailedTransmissionJob($invoice->id, 1))->handle($client);

        $invoice->refresh();

        $this->assertSame('sent', $invoice->processing_status);
        $this->assertSame('acknowledged', $invoice->eis_status);
        $this->assertSame('EIS-REF-123', $invoice->eis_reference_no);
        Queue::assertNothingPushed();
    }
}
