<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Device;
use App\Models\Merchant;
use App\Models\Vendor;
use App\Services\Security\VendorApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QaSuiteComplianceTest extends TestCase
{
    use RefreshDatabase;

    private function createVendorWithMerchantGraph(): array
    {
        $plainKey = 'vb_qa_suite_key_abcdefghijklmnopqrstuvwx';
        $vendor = Vendor::create([
            'name' => 'QA Suite Vendor',
            'api_key' => app(VendorApiKeyService::class)->hashKey($plainKey),
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'MRC-QA',
            'name' => 'QA Merchant',
            'tin' => '111-222-333-000',
            'status' => 'active',
        ]);

        $branch = Branch::create([
            'merchant_id' => $merchant->id,
            'branch_code' => 'BR001',
            'name' => 'QA Branch',
            'status' => 'active',
        ]);

        Device::create([
            'branch_id' => $branch->id,
            'pos_device_id' => 'POS01',
            'name' => 'QA POS',
            'status' => 'active',
        ]);

        return [$vendor, $plainKey];
    }

    private function validTransaction(string $id = 'TX-QA-001'): array
    {
        return [
            'transaction_id' => $id,
            'transaction_datetime' => '2026-06-12T00:00:00+08:00',
            'merchant_code' => 'MRC-QA',
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
            'totals' => ['net' => 100, 'gross' => 100],
            'payment' => ['method' => 'CASH', 'amount' => 100],
        ];
    }

    public function test_invalid_iso_datetime_returns_422_validation_error(): void
    {
        [, $key] = $this->createVendorWithMerchantGraph();

        $tx = $this->validTransaction();
        $tx['transaction_datetime'] = '2026-13-40T25:61:61+08:00';

        $this->postJson('/v1/transactions', ['transaction' => $tx], [
            'Authorization' => 'Bearer '.$key,
        ])->assertStatus(422)
            ->assertJsonPath('error', 'validation_error')
            ->assertJsonStructure(['details' => ['transaction.transaction_datetime']]);
    }

    public function test_negative_quantity_returns_422(): void
    {
        [, $key] = $this->createVendorWithMerchantGraph();

        $tx = $this->validTransaction();
        $tx['items'][0]['qty'] = -1;

        $this->postJson('/v1/transactions', ['transaction' => $tx], [
            'Authorization' => 'Bearer '.$key,
        ])->assertStatus(422)
            ->assertJsonPath('error', 'validation_error')
            ->assertJsonStructure(['details' => ['transaction.items.0.qty']]);
    }

    public function test_non_positive_unit_price_returns_422(): void
    {
        [, $key] = $this->createVendorWithMerchantGraph();

        $tx = $this->validTransaction();
        $tx['items'][0]['unit_price'] = 0;

        $this->postJson('/v1/transactions', ['transaction' => $tx], [
            'Authorization' => 'Bearer '.$key,
        ])->assertStatus(422)
            ->assertJsonPath('error', 'validation_error')
            ->assertJsonStructure(['details' => ['transaction.items.0.unit_price']]);
    }

    public function test_injection_like_payload_returns_422(): void
    {
        [, $key] = $this->createVendorWithMerchantGraph();

        $tx = $this->validTransaction();
        $tx['items'][0]['description'] = "<script>alert('x')</script>";

        $this->postJson('/v1/transactions', ['transaction' => $tx], [
            'Authorization' => 'Bearer '.$key,
        ])->assertStatus(422)
            ->assertJsonPath('error', 'validation_error')
            ->assertJsonStructure(['details' => ['transaction.items.0.description']]);
    }

    public function test_same_transaction_id_with_different_payload_returns_409(): void
    {
        [, $key] = $this->createVendorWithMerchantGraph();
        Queue::fake();

        $first = $this->validTransaction('TX-QA-CONFLICT');
        $second = $this->validTransaction('TX-QA-CONFLICT');
        $second['items'][0]['unit_price'] = 200;
        $second['totals']['net'] = 200;
        $second['payment']['amount'] = 200;

        $this->postJson('/v1/transactions', ['transaction' => $first], [
            'Authorization' => 'Bearer '.$key,
        ])->assertStatus(201);

        $this->postJson('/v1/transactions', ['transaction' => $second], [
            'Authorization' => 'Bearer '.$key,
        ])->assertStatus(409)
            ->assertJsonPath('error', 'transaction_conflict');
    }

    public function test_batch_accepts_valid_entries_and_rejects_invalid_entries_without_request_failure(): void
    {
        [, $key] = $this->createVendorWithMerchantGraph();

        $valid = $this->validTransaction('TX-QA-BATCH-OK');
        $conflictSeed = $this->validTransaction('TX-QA-BATCH-CONFLICT');
        $conflictDifferent = $this->validTransaction('TX-QA-BATCH-CONFLICT');
        $conflictDifferent['items'][0]['qty'] = 3;
        $conflictDifferent['totals']['net'] = 300;
        $conflictDifferent['payment']['amount'] = 300;

        $this->postJson('/v1/transactions', ['transaction' => $conflictSeed], [
            'Authorization' => 'Bearer '.$key,
        ])->assertStatus(201);

        $response = $this->postJson('/v1/transactions/batch', [
            'batch_id' => 'BATCH-QA-001',
            'transactions' => [$valid, $conflictDifferent],
        ], [
            'Authorization' => 'Bearer '.$key,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'accepted')
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.accepted', 1)
            ->assertJsonPath('summary.rejected', 1);
    }

    public function test_vendor_webhook_invalid_url_returns_422_validation_error_contract(): void
    {
        [, $key] = $this->createVendorWithMerchantGraph();

        $this->postJson('/v1/vendors/webhook', [
            'webhook_url' => 'http://127.0.0.1/webhook',
            'secret' => 'secret12345',
        ], [
            'Authorization' => 'Bearer '.$key,
        ])->assertStatus(422)
            ->assertJsonPath('error', 'validation_error')
            ->assertJsonStructure(['details' => ['webhook_url']]);
    }
}
