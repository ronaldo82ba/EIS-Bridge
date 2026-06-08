<?php

namespace Tests\Feature;

use App\Jobs\ProcessInvoiceJob;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Vendor;
use App\Services\TransactionProcessor;
use Tests\TestCase;

class Phase2EngineTest extends TestCase
{
    private function seedMerchantGraph(): Merchant
    {
        $vendor = Vendor::create([
            'name' => 'Test Vendor',
            'api_key' => hash('sha256', 'test-key'),
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'MRC123',
            'name' => 'Sandbox Merchant',
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

        return $merchant;
    }

    private function samplePosPayload(): array
    {
        return [
            'transaction_id' => 'POS-PHASE2-001',
            'transaction_datetime' => '2026-06-07T14:23:55+08:00',
            'merchant_code' => 'MRC123',
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

    public function test_full_phase2_pipeline_via_process_invoice_job(): void
    {
        config(['eis.sandbox_mode' => true]);

        $this->seedMerchantGraph();

        $invoice = Invoice::create([
            'bridge_transaction_id' => 'EB-PHASE2-000001',
            'transaction_id' => 'POS-PHASE2-001',
            'merchant_code' => 'MRC123',
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => $this->samplePosPayload(),
            'processing_status' => 'queued',
        ]);

        ProcessInvoiceJob::dispatch($invoice->id);

        $invoice->refresh();

        $this->assertSame('sent', $invoice->processing_status);
        $this->assertNotEmpty($invoice->bir_json);
        $this->assertNotEmpty($invoice->signed_json);
        $this->assertSame('POS-PHASE2-001', $invoice->bir_json['transaction_id']);
        $this->assertSame('MRC123', $invoice->bir_json['merchant']['code']);
        $this->assertArrayHasKey('signature', $invoice->signed_json);
        $this->assertArrayHasKey('signature_hash', $invoice->signed_json);
        $this->assertSame('acknowledged', $invoice->eis_status);
        $this->assertNotEmpty($invoice->eis_reference_no);
    }

    public function test_transaction_processor_dispatches_pipeline(): void
    {
        config(['eis.sandbox_mode' => true]);

        $vendor = $this->seedMerchantGraph()->vendor;

        $result = app(TransactionProcessor::class)->processSingle($this->samplePosPayload(), $vendor);

        $this->assertSame('accepted', $result['status']);

        $invoice = Invoice::where('transaction_id', 'POS-PHASE2-001')->first();
        $this->assertNotNull($invoice);
        $this->assertSame('sent', $invoice->processing_status);
        $this->assertNotEmpty($invoice->bir_json);
        $this->assertNotEmpty($invoice->signed_json);
    }
}
