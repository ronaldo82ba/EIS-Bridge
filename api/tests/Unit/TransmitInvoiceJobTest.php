<?php

namespace Tests\Unit;

use App\Jobs\RetryFailedTransmissionJob;
use App\Jobs\TransmitInvoiceJob;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Vendor;
use App\Services\Eis\EisClient;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class TransmitInvoiceJobTest extends TestCase
{
    public function test_transmission_failure_dispatches_retry_job(): void
    {
        $vendor = Vendor::create([
            'name' => 'Transmit Vendor',
            'api_key' => hash('sha256', 'transmit-key'),
            'status' => 'active',
        ]);

        Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'MRC-TX',
            'name' => 'Transmit Merchant',
            'tin' => '123-456-789-000',
        ]);

        $invoice = Invoice::create([
            'bridge_transaction_id' => 'EB-TX-001',
            'transaction_id' => 'POS-TX-001',
            'merchant_code' => 'MRC-TX',
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => ['transaction_id' => 'POS-TX-001'],
            'signed_json' => ['payload' => ['transaction_id' => 'POS-TX-001'], 'signature' => 'sig'],
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

        Queue::fake([RetryFailedTransmissionJob::class]);

        (new TransmitInvoiceJob($invoice->id))->handle($client);

        $invoice->refresh();

        $this->assertSame('transmission_failed', $invoice->processing_status);
        $this->assertSame('rejected', $invoice->eis_status);

        Queue::assertPushed(RetryFailedTransmissionJob::class, function (RetryFailedTransmissionJob $job) use ($invoice) {
            return $job->invoiceId === $invoice->id && $job->attempt === 1;
        });
    }
}
