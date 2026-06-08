<?php

namespace Tests\Feature\Invoices;

use App\Models\Branch;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Vendor;
use App\Services\TransactionProcessor;
use Tests\TestCase;

class AtomicIdGenerationTest extends TestCase
{
    private function seedMerchantGraph(): Vendor
    {
        $vendor = Vendor::create([
            'name' => 'Atomic Vendor',
            'api_key' => hash('sha256', 'atomic-key'),
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'MRC-ATOMIC',
            'name' => 'Atomic Merchant',
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

        return $vendor;
    }

    private function samplePayload(string $transactionId): array
    {
        return [
            'transaction_id' => $transactionId,
            'transaction_datetime' => '2026-06-09T14:23:55+08:00',
            'merchant_code' => 'MRC-ATOMIC',
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

    public function test_generate_bridge_transaction_id_returns_unique_ulid_based_ids(): void
    {
        $first = Invoice::generateBridgeTransactionId();
        $second = Invoice::generateBridgeTransactionId();

        $this->assertNotSame($first, $second);
        $this->assertStringStartsWith('EB-', $first);
        $this->assertMatchesRegularExpression('/^EB-[0-9A-HJKMNP-TV-Z]{26}$/', $first);
    }

    public function test_transaction_processor_assigns_unique_bridge_ids(): void
    {
        $vendor = $this->seedMerchantGraph();
        $processor = app(TransactionProcessor::class);

        $first = $processor->processSingle($this->samplePayload('POS-ATOMIC-001'), $vendor);
        $second = $processor->processSingle($this->samplePayload('POS-ATOMIC-002'), $vendor);

        $this->assertSame('accepted', $first['status']);
        $this->assertSame('accepted', $second['status']);
        $this->assertNotSame($first['bridge_transaction_id'], $second['bridge_transaction_id']);
        $this->assertStringStartsWith('EB-', $first['bridge_transaction_id']);
    }

    public function test_bridge_transaction_id_is_unique_at_database_level(): void
    {
        $id = Invoice::generateBridgeTransactionId();

        Invoice::create([
            'bridge_transaction_id' => $id,
            'transaction_id' => 'POS-UNIQUE-001',
            'merchant_code' => 'MRC-ATOMIC',
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => ['transaction_id' => 'POS-UNIQUE-001'],
            'processing_status' => 'queued',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Invoice::create([
            'bridge_transaction_id' => $id,
            'transaction_id' => 'POS-UNIQUE-002',
            'merchant_code' => 'MRC-ATOMIC',
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => ['transaction_id' => 'POS-UNIQUE-002'],
            'processing_status' => 'queued',
        ]);
    }
}
