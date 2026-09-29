<?php

namespace Tests\Unit;

use App\Jobs\MapInvoiceJob;
use App\Jobs\SignInvoiceJob;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\TransmissionLog;
use App\Models\Vendor;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MapInvoiceJobTest extends TestCase
{
    private function seedMerchantGraph(): void
    {
        $vendor = Vendor::create([
            'name' => 'Map Job Vendor',
            'api_key' => hash('sha256', 'map-job-key'),
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'MRC-MAP',
            'name' => 'Map Job Merchant',
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
    }

    private function validPosPayload(): array
    {
        return [
            'transaction_id' => 'POS-MAP-001',
            'transaction_datetime' => '2026-06-09T14:23:55+08:00',
            'merchant_code' => 'MRC-MAP',
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'invoice_type' => 'OR',
            'currency' => 'PHP',
            'items' => [
                [
                    'line_no' => 1,
                    'sku' => 'SKU001',
                    'description' => 'Product A',
                    'qty' => 1,
                    'unit_price' => 100.0,
                    'discount' => 0.0,
                    'vat_rate' => 12.0,
                ],
            ],
            'totals' => [
                'gross' => 100.0,
                'discount' => 0.0,
                'vatable_sales' => 89.29,
                'vat_amount' => 10.71,
                'vat_exempt_sales' => 0.0,
                'zero_rated_sales' => 0.0,
                'service_charge' => 0.0,
                'net' => 100.0,
            ],
            'payment' => [
                'method' => 'CASH',
                'amount' => 100.0,
            ],
        ];
    }

    public function test_maps_valid_payload_and_dispatches_sign_job(): void
    {
        $this->seedMerchantGraph();

        $invoice = Invoice::create([
            'bridge_transaction_id' => 'EB-MAP-001',
            'transaction_id' => 'POS-MAP-001',
            'merchant_code' => 'MRC-MAP',
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => $this->validPosPayload(),
            'processing_status' => 'queued',
        ]);

        Queue::fake([SignInvoiceJob::class]);

        (new MapInvoiceJob($invoice->id))->handle(
            $this->app->make(\App\Services\Mapping\PosToBirMapper::class),
            $this->app->make(\App\Services\Mapping\BirSchemaValidator::class)
        );

        $invoice->refresh();

        $this->assertSame('mapped', $invoice->processing_status);
        $this->assertNotEmpty($invoice->bir_json);
        $this->assertSame('POS-MAP-001', $invoice->bir_json['transaction_id']);
        $this->assertSame('MRC-MAP', $invoice->bir_json['merchant']['code']);

        $this->assertDatabaseHas('transmission_logs', [
            'invoice_id' => $invoice->id,
            'event' => 'mapped',
        ]);

        Queue::assertPushed(SignInvoiceJob::class, function (SignInvoiceJob $job) use ($invoice) {
            return $job->invoiceId === $invoice->id;
        });
    }

    public function test_maps_qa_minimum_payload_with_net_only_totals(): void
    {
        $this->seedMerchantGraph();

        $invoice = Invoice::create([
            'bridge_transaction_id' => 'EB-MAP-NET-ONLY',
            'transaction_id' => 'TX-QA-NET-ONLY',
            'merchant_code' => 'MRC-MAP',
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => [
                'transaction_id' => 'TX-QA-NET-ONLY',
                'transaction_datetime' => '2026-06-12T00:00:00+08:00',
                'merchant_code' => 'MRC-MAP',
                'branch_code' => 'BR001',
                'pos_device_id' => 'POS01',
                'invoice_type' => 'OR',
                'items' => [
                    [
                        'sku' => 'SKU-1',
                        'description' => 'Item 1',
                        'qty' => 1,
                        'unit_price' => 100,
                    ],
                ],
                'totals' => ['net' => 100],
                'payment' => ['method' => 'CASH', 'amount' => 100],
            ],
            'processing_status' => 'queued',
        ]);

        Queue::fake([SignInvoiceJob::class]);

        (new MapInvoiceJob($invoice->id))->handle(
            $this->app->make(\App\Services\Mapping\PosToBirMapper::class),
            $this->app->make(\App\Services\Mapping\BirSchemaValidator::class)
        );

        $invoice->refresh();

        $this->assertSame('mapped', $invoice->processing_status);
        $this->assertEquals(100.0, $invoice->bir_json['totals']['gross_amount']);
        $this->assertEquals(100.0, $invoice->bir_json['totals']['net_amount']);

        Queue::assertPushed(SignInvoiceJob::class);
    }

    public function test_fails_gracefully_with_invalid_payload(): void
    {
        $this->seedMerchantGraph();

        $invoice = Invoice::create([
            'bridge_transaction_id' => 'EB-MAP-INVALID',
            'transaction_id' => 'POS-MAP-INVALID',
            'merchant_code' => 'MRC-MAP',
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => [
                'transaction_id' => 'POS-MAP-INVALID',
            ],
            'processing_status' => 'queued',
        ]);

        Queue::fake([SignInvoiceJob::class]);

        (new MapInvoiceJob($invoice->id))->handle(
            $this->app->make(\App\Services\Mapping\PosToBirMapper::class),
            $this->app->make(\App\Services\Mapping\BirSchemaValidator::class)
        );

        $invoice->refresh();

        $this->assertSame('failed', $invoice->processing_status);
        $this->assertNull($invoice->bir_json);

        $this->assertTrue(
            TransmissionLog::where('invoice_id', $invoice->id)
                ->where('event', 'mapping_validation_failed')
                ->exists()
        );

        Queue::assertNotPushed(SignInvoiceJob::class);
    }
}
