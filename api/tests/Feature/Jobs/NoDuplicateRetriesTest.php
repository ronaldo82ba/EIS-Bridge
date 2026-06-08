<?php

namespace Tests\Feature\Jobs;

use App\Jobs\MapInvoiceJob;
use App\Jobs\SignInvoiceJob;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Vendor;
use App\Services\Mapping\PosToBirMapper;
use Mockery;
use Tests\TestCase;

class NoDuplicateRetriesTest extends TestCase
{
    public function test_map_invoice_job_has_single_try(): void
    {
        $job = new MapInvoiceJob(1);

        $this->assertSame(1, $job->tries);
    }

    public function test_sign_invoice_job_has_single_try(): void
    {
        $job = new SignInvoiceJob(1);

        $this->assertSame(1, $job->tries);
    }

    public function test_map_invoice_job_does_not_rethrow_after_mark_failed(): void
    {
        $vendor = Vendor::create([
            'name' => 'Map Fail Vendor',
            'api_key' => hash('sha256', 'map-fail-key'),
            'status' => 'active',
        ]);

        Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'MRC-MAP-FAIL',
            'name' => 'Map Fail Merchant',
            'tin' => '123-456-789-000',
        ]);

        $invoice = Invoice::create([
            'bridge_transaction_id' => 'EB-MAP-FAIL-001',
            'transaction_id' => 'POS-MAP-FAIL-001',
            'merchant_code' => 'MRC-MAP-FAIL',
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => ['transaction_id' => 'POS-MAP-FAIL-001'],
            'processing_status' => 'queued',
        ]);

        $mapper = Mockery::mock(PosToBirMapper::class);
        $mapper->shouldReceive('map')
            ->once()
            ->andThrow(new \RuntimeException('Unexpected mapping error'));

        $this->app->instance(PosToBirMapper::class, $mapper);

        (new MapInvoiceJob($invoice->id))->handle(
            $mapper,
            $this->app->make(\App\Services\Mapping\BirSchemaValidator::class)
        );

        $invoice->refresh();

        $this->assertSame('failed', $invoice->processing_status);
    }

    public function test_sign_invoice_job_does_not_rethrow_after_signing_failure(): void
    {
        $vendor = Vendor::create([
            'name' => 'Sign Fail Vendor',
            'api_key' => hash('sha256', 'sign-fail-key'),
            'status' => 'active',
        ]);

        Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'MRC-SIGN-FAIL',
            'name' => 'Sign Fail Merchant',
            'tin' => '123-456-789-000',
        ]);

        $invoice = Invoice::create([
            'bridge_transaction_id' => 'EB-SIGN-FAIL-001',
            'transaction_id' => 'POS-SIGN-FAIL-001',
            'merchant_code' => 'MRC-SIGN-FAIL',
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => ['transaction_id' => 'POS-SIGN-FAIL-001'],
            'bir_json' => ['transaction_id' => 'POS-SIGN-FAIL-001'],
            'processing_status' => 'mapped',
        ]);

        config(['eis.sandbox_mode' => false]);

        (new SignInvoiceJob($invoice->id))->handle(
            $this->app->make(\App\Services\Signing\CertificateLoader::class),
            $this->app->make(\App\Services\Signing\JsonSigner::class)
        );

        $invoice->refresh();

        $this->assertSame('failed', $invoice->processing_status);
    }
}
