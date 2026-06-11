<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Device;
use App\Models\Merchant;
use App\Models\User;
use App\Models\Vendor;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MerchantOnboardingTest extends TestCase
{
    private function actingAsSuperAdmin(): User
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    private function createVendor(): Vendor
    {
        return Vendor::create([
            'name' => 'Onboarding Vendor',
            'api_key' => hash('sha256', 'onboarding-vendor-key'),
            'status' => 'active',
        ]);
    }

    public function test_admin_can_create_merchant_branch_and_device(): void
    {
        $this->actingAsSuperAdmin();
        $vendor = $this->createVendor();

        $merchantResponse = $this->postJson('/api/admin/merchants', [
            'vendor_id' => $vendor->id,
            'name' => 'Onboarding Merchant',
            'tin' => '999-888-777-000',
            'address' => '456 Commerce Ave',
            'status' => 'active',
        ])->assertCreated();

        $merchantId = $merchantResponse->json('data.id');
        $this->assertNotNull($merchantId);

        $branchResponse = $this->postJson('/api/admin/branches', [
            'merchant_id' => $merchantId,
            'branch_code' => 'BR100',
            'name' => 'Flagship Branch',
            'address' => 'Branch address',
            'status' => 'active',
        ])->assertCreated();

        $branchId = $branchResponse->json('data.id');

        $this->postJson('/api/admin/devices', [
            'branch_id' => $branchId,
            'pos_device_id' => 'POS-100',
            'status' => 'active',
        ])
            ->assertCreated()
            ->assertJsonPath('data.pos_device_id', 'POS-100')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('merchants', [
            'id' => $merchantId,
            'name' => 'Onboarding Merchant',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('branches', [
            'id' => $branchId,
            'merchant_id' => $merchantId,
            'branch_code' => 'BR100',
        ]);

        $this->assertDatabaseHas('devices', [
            'branch_id' => $branchId,
            'pos_device_id' => 'POS-100',
            'status' => 'active',
        ]);
    }

    public function test_branch_code_must_be_unique_per_merchant(): void
    {
        $this->actingAsSuperAdmin();
        $vendor = $this->createVendor();

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'DUP001',
            'name' => 'Duplicate Branch Merchant',
            'tin' => '111-222-333-000',
            'address' => 'Test address',
            'status' => 'active',
        ]);

        Branch::create([
            'merchant_id' => $merchant->id,
            'branch_code' => 'BR001',
            'name' => 'Existing Branch',
        ]);

        $this->postJson('/api/admin/branches', [
            'merchant_id' => $merchant->id,
            'branch_code' => 'BR001',
            'name' => 'Duplicate Branch',
        ])->assertStatus(422);
    }

    public function test_locked_device_rejects_transactions(): void
    {
        $vendor = $this->createVendor();

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'LOCK001',
            'name' => 'Locked Device Merchant',
            'tin' => '444-555-666-000',
            'address' => 'Lock test address',
            'status' => 'active',
        ]);

        $branch = Branch::create([
            'merchant_id' => $merchant->id,
            'branch_code' => 'BR001',
            'name' => 'Main',
        ]);

        Device::create([
            'branch_id' => $branch->id,
            'pos_device_id' => 'POS-LOCKED',
            'name' => 'Locked POS',
            'status' => 'locked',
        ]);

        $processor = app(\App\Services\TransactionProcessor::class);

        $result = $processor->processSingle([
            'transaction_id' => 'TX-LOCK-001',
            'transaction_datetime' => '2026-06-12T01:00:00+08:00',
            'merchant_code' => 'LOCK001',
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS-LOCKED',
            'invoice_type' => 'OR',
            'items' => [[
                'sku' => 'SKU-LOCK',
                'description' => 'Locked item',
                'qty' => 1,
                'unit_price' => 100,
            ]],
            'totals' => ['net' => 100, 'gross' => 100],
            'payment' => ['method' => 'CASH', 'amount' => 100],
        ], $vendor);

        $this->assertSame(403, $result['http_status']);
        $this->assertSame('device_locked', $result['error']);
    }
}
