<?php

namespace Tests\Feature\Invoices;

use App\Models\Branch;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Vendor;
use App\Services\TransactionProcessor;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DedupIndexTest extends TestCase
{
    private function seedMerchantGraph(): Vendor
    {
        $vendor = Vendor::create([
            'name' => 'Dedup Vendor',
            'api_key' => hash('sha256', 'dedup-key'),
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'MRC-DEDUP',
            'name' => 'Dedup Merchant',
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

    private function samplePayload(): array
    {
        return [
            'transaction_id' => 'POS-DEDUP-001',
            'transaction_datetime' => '2026-06-09T14:23:55+08:00',
            'merchant_code' => 'MRC-DEDUP',
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

    public function test_dedup_composite_index_exists(): void
    {
        $indexes = Schema::getIndexes('invoices');
        $indexNames = array_column($indexes, 'name');

        $this->assertContains('invoices_dedup_index', $indexNames);
    }

    public function test_duplicate_transaction_is_detected_via_dedup_columns(): void
    {
        $vendor = $this->seedMerchantGraph();
        $processor = app(TransactionProcessor::class);
        $payload = $this->samplePayload();

        $first = $processor->processSingle($payload, $vendor);
        $second = $processor->processSingle($payload, $vendor);

        $this->assertSame('accepted', $first['status']);
        $this->assertSame('duplicate', $second['status']);
        $this->assertSame($first['bridge_transaction_id'], $second['bridge_transaction_id']);
        $this->assertSame(1, Invoice::where('transaction_id', 'POS-DEDUP-001')->count());
    }

    public function test_same_transaction_id_with_different_device_is_not_duplicate(): void
    {
        $vendor = $this->seedMerchantGraph();
        $processor = app(TransactionProcessor::class);

        $payloadA = $this->samplePayload();
        $payloadB = $this->samplePayload();
        $payloadB['pos_device_id'] = 'POS02';

        Device::create([
            'branch_id' => Branch::first()->id,
            'pos_device_id' => 'POS02',
            'name' => 'POS Terminal 02',
        ]);

        $first = $processor->processSingle($payloadA, $vendor);
        $second = $processor->processSingle($payloadB, $vendor);

        $this->assertSame('accepted', $first['status']);
        $this->assertSame('accepted', $second['status']);
        $this->assertNotSame($first['bridge_transaction_id'], $second['bridge_transaction_id']);
    }

    public function test_same_transaction_id_with_different_branch_is_not_duplicate(): void
    {
        $vendor = $this->seedMerchantGraph();
        $processor = app(TransactionProcessor::class);
        $merchant = Merchant::where('merchant_code', 'MRC-DEDUP')->first();

        Branch::create([
            'merchant_id' => $merchant->id,
            'branch_code' => 'BR002',
            'name' => 'Second Branch',
        ]);

        Device::create([
            'branch_id' => Branch::where('branch_code', 'BR002')->first()->id,
            'pos_device_id' => 'POS01',
            'name' => 'POS Terminal BR002',
        ]);

        $payloadA = $this->samplePayload();
        $payloadB = $this->samplePayload();
        $payloadB['branch_code'] = 'BR002';

        $first = $processor->processSingle($payloadA, $vendor);
        $second = $processor->processSingle($payloadB, $vendor);

        $this->assertSame('accepted', $first['status']);
        $this->assertSame('accepted', $second['status']);
        $this->assertNotSame($first['bridge_transaction_id'], $second['bridge_transaction_id']);
        $this->assertSame(2, Invoice::where('transaction_id', 'POS-DEDUP-001')->count());
    }
}
