<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\TransmissionLog;
use App\Models\User;
use App\Models\Vendor;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminMerchantActivityTest extends TestCase
{
    private function seedMerchantWithActivity(): array
    {
        $vendor = Vendor::create([
            'name' => 'Activity API Vendor',
            'api_key' => hash('sha256', 'activity-api-vendor'),
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'MRC-ACT-API',
            'name' => 'Activity API Merchant',
            'tin' => '111-222-333-000',
            'address' => '1 API St',
            'status' => 'active',
        ]);

        $invoice = Invoice::create([
            'bridge_transaction_id' => 'BRG-API-1',
            'transaction_id' => 'POS-API-1',
            'merchant_code' => $merchant->merchant_code,
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => ['transaction_id' => 'POS-API-1'],
            'processing_status' => 'mapped',
        ]);

        TransmissionLog::create([
            'invoice_id' => $invoice->id,
            'event' => 'mapped',
            'timestamp' => now(),
        ]);

        TransmissionLog::create([
            'invoice_id' => $invoice->id,
            'event' => 'signed',
            'timestamp' => now()->addMinute(),
        ]);

        return [$vendor, $merchant, $invoice];
    }

    public function test_admin_can_fetch_merchant_activity(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        [, $merchant] = $this->seedMerchantWithActivity();

        $response = $this->getJson("/api/admin/merchants/{$merchant->id}/activity")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['type', 'created_at', 'details'],
                ],
                'current_page',
                'last_page',
                'total',
            ]);

        $types = collect($response->json('data'))->pluck('type')->all();

        $this->assertContains('transaction_received', $types);
        $this->assertContains('mapping_completed', $types);
        $this->assertContains('signing_completed', $types);
        $this->assertGreaterThanOrEqual(3, $response->json('total'));
    }

    public function test_activity_can_filter_by_type(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        [, $merchant] = $this->seedMerchantWithActivity();

        $this->getJson("/api/admin/merchants/{$merchant->id}/activity?type=signing")
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.type', 'signing_completed');
    }

    public function test_vendor_admin_cannot_view_other_vendor_activity(): void
    {
        [$vendor, $merchant] = $this->seedMerchantWithActivity();

        $otherVendor = Vendor::create([
            'name' => 'Other Vendor',
            'api_key' => hash('sha256', 'other-vendor-activity'),
            'status' => 'active',
        ]);

        $otherMerchant = Merchant::create([
            'vendor_id' => $otherVendor->id,
            'merchant_code' => 'MRC-OTHER-ACT',
            'name' => 'Other Merchant',
            'tin' => '999-888-777-000',
            'address' => '2 Other St',
            'status' => 'active',
        ]);

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_VENDOR_ADMIN,
            'vendor_id' => $vendor->id,
        ]));

        $this->getJson("/api/admin/merchants/{$merchant->id}/activity")
            ->assertOk();

        $this->getJson("/api/admin/merchants/{$otherMerchant->id}/activity")
            ->assertForbidden();
    }
}
