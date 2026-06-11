<?php

namespace Tests\Feature;

use App\Enums\LicenseStatus;
use App\Jobs\MapInvoiceJob;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\LicensePlan;
use App\Models\Merchant;
use App\Models\MerchantLicense;
use App\Models\Vendor;
use App\Models\VendorLicense;
use App\Services\Security\VendorApiKeyService;
use App\Services\TransactionProcessor;
use Database\Seeders\LicensePlanSeeder;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VendorApiTenantIsolationTest extends TestCase
{
    private function createVendorWithKey(string $name, string $plainKey, string $status = 'active'): Vendor
    {
        $service = app(VendorApiKeyService::class);

        return Vendor::create([
            'name' => $name,
            'api_key' => $service->hashKey($plainKey),
            'status' => $status,
        ]);
    }

    private function authHeaders(string $plainKey): array
    {
        return ['Authorization' => 'Bearer '.$plainKey];
    }

    private function seedMerchantForVendor(Vendor $vendor, string $merchantCode): Merchant
    {
        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => $merchantCode,
            'name' => "Merchant {$merchantCode}",
            'tin' => '111-222-333-000',
            'status' => 'active',
        ]);

        Branch::create([
            'merchant_id' => $merchant->id,
            'branch_code' => 'BR001',
            'name' => 'Main Branch',
        ]);

        return $merchant;
    }

    public function test_show_returns_404_for_cross_vendor_transaction(): void
    {
        $vendorAKey = 'vb_vendor_a_isolation_key_abcdefghijklmnop';
        $vendorBKey = 'vb_vendor_b_isolation_key_abcdefghijklmnop';

        $vendorA = $this->createVendorWithKey('Vendor A', $vendorAKey);
        $vendorB = $this->createVendorWithKey('Vendor B', $vendorBKey);

        $this->seedMerchantForVendor($vendorA, 'MRC-A');
        $this->seedMerchantForVendor($vendorB, 'MRC-B');

        $invoice = Invoice::create([
            'bridge_transaction_id' => 'EB-CROSS-VENDOR-001',
            'transaction_id' => 'TX-B-001',
            'merchant_code' => 'MRC-B',
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => [],
            'processing_status' => 'queued',
        ]);

        $this->getJson(
            '/v1/transactions/'.$invoice->bridge_transaction_id,
            $this->authHeaders($vendorAKey)
        )
            ->assertNotFound()
            ->assertJson([
                'error' => 'not_found',
            ]);
    }

    public function test_show_returns_transaction_for_own_vendor(): void
    {
        $plainKey = 'vb_vendor_own_show_key_abcdefghijklmnop';
        $vendor = $this->createVendorWithKey('Own Vendor', $plainKey);

        $this->seedMerchantForVendor($vendor, 'MRC-OWN');

        $invoice = Invoice::create([
            'bridge_transaction_id' => 'EB-OWN-VENDOR-001',
            'transaction_id' => 'TX-OWN-001',
            'merchant_code' => 'MRC-OWN',
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => [],
            'processing_status' => 'queued',
        ]);

        $this->getJson(
            '/v1/transactions/'.$invoice->bridge_transaction_id,
            $this->authHeaders($plainKey)
        )
            ->assertOk()
            ->assertJsonPath('bridge_transaction_id', $invoice->bridge_transaction_id)
            ->assertJsonPath('merchant_code', 'MRC-OWN');
    }

    public function test_index_only_lists_own_vendor_transactions(): void
    {
        $vendorAKey = 'vb_vendor_a_index_key_abcdefghijklmnop';
        $vendorBKey = 'vb_vendor_b_index_key_abcdefghijklmnop';

        $vendorA = $this->createVendorWithKey('Vendor A Index', $vendorAKey);
        $vendorB = $this->createVendorWithKey('Vendor B Index', $vendorBKey);

        $this->seedMerchantForVendor($vendorA, 'MRC-A-IDX');
        $this->seedMerchantForVendor($vendorB, 'MRC-B-IDX');

        Invoice::create([
            'bridge_transaction_id' => 'EB-A-IDX-001',
            'transaction_id' => 'TX-A-IDX-001',
            'merchant_code' => 'MRC-A-IDX',
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => [],
            'processing_status' => 'queued',
        ]);

        Invoice::create([
            'bridge_transaction_id' => 'EB-B-IDX-001',
            'transaction_id' => 'TX-B-IDX-001',
            'merchant_code' => 'MRC-B-IDX',
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => [],
            'processing_status' => 'queued',
        ]);

        $response = $this->getJson('/v1/transactions', $this->authHeaders($vendorAKey));

        $response->assertOk();

        $merchantCodes = collect($response->json('data'))->pluck('merchant_code')->all();

        $this->assertSame(['MRC-A-IDX'], $merchantCodes);
        $this->assertNotContains('MRC-B-IDX', $merchantCodes);
    }

    public function test_post_rejects_merchant_not_owned_by_vendor(): void
    {
        $plainKey = 'vb_post_not_owned_key_abcdefghijklmnop';
        $vendor = $this->createVendorWithKey('Posting Vendor', $plainKey);
        $otherVendor = $this->createVendorWithKey('Other Vendor', 'vb_other_vendor_key_abcdefghijklmnop');

        $this->seedMerchantForVendor($otherVendor, 'MRC-OTHER');

        $this->postJson('/v1/transactions', [
            'transaction' => [
                'transaction_id' => 'TX-NOT-OWNED-001',
                'merchant_code' => 'MRC-OTHER',
                'branch_code' => 'BR001',
                'pos_device_id' => 'POS01',
                'totals' => ['net' => 100],
            ],
        ], $this->authHeaders($plainKey))
            ->assertForbidden()
            ->assertJson([
                'error' => 'merchant_not_owned',
            ]);
    }

    public function test_post_returns_structured_validation_error_for_invalid_payload(): void
    {
        $plainKey = 'vb_post_validation_key_abcdefghijklmnop';
        $this->createVendorWithKey('Validation Vendor', $plainKey);
        Queue::fake();

        $this->postJson('/v1/transactions', [
            'transaction' => [
                'merchant_code' => 'MRC-UNKNOWN',
                'branch_code' => 'BR001',
                'pos_device_id' => 'POS01',
                'totals' => [],
            ],
        ], $this->authHeaders($plainKey))
            ->assertStatus(422)
            ->assertJson([
                'error' => 'validation_error',
            ])
            ->assertJsonStructure([
                'message',
                'fields' => [
                    'transaction.transaction_id',
                    'transaction.totals.net',
                ],
            ]);

        Queue::assertNotPushed(MapInvoiceJob::class);
    }

    public function test_device_lock_only_applies_to_owned_merchants(): void
    {
        $vendorAKey = 'vb_device_lock_a_key_abcdefghijklmnop';
        $vendorBKey = 'vb_device_lock_b_key_abcdefghijklmnop';

        $vendorA = $this->createVendorWithKey('Device Lock A', $vendorAKey);
        $vendorB = $this->createVendorWithKey('Device Lock B', $vendorBKey);

        $merchantA = $this->seedMerchantForVendor($vendorA, 'MRC-DL-A');
        $merchantB = Merchant::create([
            'vendor_id' => $vendorB->id,
            'merchant_code' => 'MRC-DL-B',
            'name' => 'Locked Merchant B',
            'tin' => '444-555-666-000',
            'status' => 'active',
        ]);

        $branchB = Branch::create([
            'merchant_id' => $merchantB->id,
            'branch_code' => 'BR001',
            'name' => 'Branch B',
        ]);

        Device::create([
            'branch_id' => $branchB->id,
            'pos_device_id' => 'POS-LOCKED',
            'name' => 'Locked POS',
            'status' => 'locked',
        ]);

        $branchA = Branch::where('merchant_id', $merchantA->id)->first();

        Device::create([
            'branch_id' => $branchA->id,
            'pos_device_id' => 'POS-A',
            'name' => 'Active POS',
            'status' => 'active',
        ]);

        $processor = app(TransactionProcessor::class);

        $result = $processor->processSingle([
            'transaction_id' => 'TX-DL-A-001',
            'merchant_code' => 'MRC-DL-A',
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS-A',
            'totals' => ['net' => 100],
        ], $vendorA);

        $this->assertSame('accepted', $result['status']);

        $crossVendorResult = $processor->processSingle([
            'transaction_id' => 'TX-DL-B-001',
            'merchant_code' => 'MRC-DL-B',
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS-LOCKED',
            'totals' => ['net' => 100],
        ], $vendorA);

        $this->assertSame(403, $crossVendorResult['http_status']);
        $this->assertSame('merchant_not_owned', $crossVendorResult['error']);
    }

    public function test_suspended_vendor_is_rejected_at_api_middleware(): void
    {
        $plainKey = 'vb_suspended_vendor_key_abcdefghijklmnop';
        $this->createVendorWithKey('Suspended Vendor', $plainKey, 'suspended');

        $this->getJson('/v1/transactions', $this->authHeaders($plainKey))
            ->assertForbidden()
            ->assertJson([
                'error' => 'vendor_suspended',
                'message' => 'Vendor account is suspended.',
            ]);
    }

    public function test_vendor_with_suspended_blocking_license_is_rejected(): void
    {
        $this->seed(LicensePlanSeeder::class);

        $plainKey = 'vb_license_blocked_key_abcdefghijklmnop';
        $vendor = $this->createVendorWithKey('License Blocked Vendor', $plainKey);

        $plan = LicensePlan::where('slug', 'vendor_one_time')->first();

        VendorLicense::create([
            'vendor_id' => $vendor->id,
            'license_plan_id' => $plan->id,
            'status' => LicenseStatus::Active->value,
            'purchased_at' => now(),
            'starts_at' => now(),
        ]);

        VendorLicense::create([
            'vendor_id' => $vendor->id,
            'license_plan_id' => $plan->id,
            'status' => LicenseStatus::Suspended->value,
            'purchased_at' => now(),
            'starts_at' => now(),
        ]);

        $this->getJson('/v1/transactions', $this->authHeaders($plainKey))
            ->assertForbidden()
            ->assertJson([
                'error' => 'license_suspended',
                'message' => 'Vendor license is not active.',
            ]);
    }

    public function test_merchant_with_suspended_license_rejects_transaction(): void
    {
        $this->seed(LicensePlanSeeder::class);

        $plainKey = 'vb_merch_license_key_abcdefghijklmnop';
        $vendor = $this->createVendorWithKey('Merchant License Vendor', $plainKey);
        $merchant = $this->seedMerchantForVendor($vendor, 'MRC-ML');

        $plan = LicensePlan::where('slug', 'merchant_one_time')->first();

        MerchantLicense::create([
            'merchant_id' => $merchant->id,
            'license_plan_id' => $plan->id,
            'status' => LicenseStatus::Active->value,
            'purchased_at' => now(),
            'starts_at' => now(),
        ]);

        MerchantLicense::create([
            'merchant_id' => $merchant->id,
            'license_plan_id' => $plan->id,
            'status' => LicenseStatus::Suspended->value,
            'purchased_at' => now(),
            'starts_at' => now(),
        ]);

        $this->postJson('/v1/transactions', [
            'transaction' => [
                'transaction_id' => 'TX-ML-001',
                'merchant_code' => 'MRC-ML',
                'branch_code' => 'BR001',
                'pos_device_id' => 'POS01',
                'totals' => ['net' => 100],
            ],
        ], $this->authHeaders($plainKey))
            ->assertForbidden()
            ->assertJson([
                'error' => 'license_suspended',
                'message' => 'Merchant license is not active.',
            ]);
    }
}
