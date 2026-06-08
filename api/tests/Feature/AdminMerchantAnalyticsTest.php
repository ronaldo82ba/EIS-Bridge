<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\User;
use App\Models\Vendor;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminMerchantAnalyticsTest extends TestCase
{
    private function seedVendorMerchant(string $suffix): array
    {
        $vendor = Vendor::create([
            'name' => 'Vendor '.$suffix,
            'api_key' => hash('sha256', 'merchant-'.$suffix),
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'MRC-'.$suffix,
            'name' => 'Merchant '.$suffix,
            'tin' => '111-222-333-000',
        ]);

        return [$vendor, $merchant];
    }

    public function test_analytics_endpoint_returns_expected_shape(): void
    {
        [$vendor, $merchant] = $this->seedVendorMerchant('API-MA');

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        Branch::create([
            'merchant_id' => $merchant->id,
            'branch_code' => 'BR001',
            'name' => 'Main Branch',
            'address' => '123 Street',
            'status' => 'active',
        ]);

        Invoice::create([
            'bridge_transaction_id' => 'BRG-MA-API',
            'transaction_id' => 'POS-MA-API',
            'merchant_code' => $merchant->merchant_code,
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => ['transaction_id' => 'POS-MA-API'],
            'processing_status' => 'sent',
            'eis_status' => 'acknowledged',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $this->getJson("/api/admin/merchants/{$merchant->id}/analytics?range=7d")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'kpi' => ['total', 'ack', 'rejected', 'retry_failed', 'error_rate'],
                    'daily' => ['labels', 'values'],
                    'eis_breakdown' => ['ack', 'rejected', 'pending'],
                    'branch_volume',
                    'device_volume',
                    'errors',
                    'certificate_health' => ['status', 'valid', 'expiring_30', 'expiring_7', 'expired', 'missing'],
                    'retry_pressure' => ['retry_failed', 'transmission_failed'],
                ],
            ])
            ->assertJsonPath('data.kpi.total', 1)
            ->assertJsonPath('data.kpi.ack', 1)
            ->assertJsonPath('data.branch_volume.0.name', 'Main Branch');
    }

    public function test_vendor_admin_can_view_own_merchant_analytics(): void
    {
        [$vendor, $merchant] = $this->seedVendorMerchant('OWN-MA');

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_VENDOR_ADMIN,
            'vendor_id' => $vendor->id,
        ]));

        Invoice::create([
            'bridge_transaction_id' => 'BRG-MA-OWN',
            'transaction_id' => 'POS-MA-OWN',
            'merchant_code' => $merchant->merchant_code,
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => ['transaction_id' => 'POS-MA-OWN'],
            'processing_status' => 'sent',
            'eis_status' => 'acknowledged',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $this->getJson("/api/admin/merchants/{$merchant->id}/analytics?range=7d")
            ->assertOk()
            ->assertJsonPath('data.kpi.total', 1);
    }

    public function test_vendor_admin_cannot_view_other_vendor_merchant_analytics(): void
    {
        [$ownVendor, $ownMerchant] = $this->seedVendorMerchant('OWN-SCOPE-MA');
        [$otherVendor, $otherMerchant] = $this->seedVendorMerchant('OTHER-SCOPE-MA');

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_VENDOR_ADMIN,
            'vendor_id' => $ownVendor->id,
        ]));

        $this->getJson("/api/admin/merchants/{$otherMerchant->id}/analytics?range=7d")
            ->assertForbidden();
    }

    public function test_support_user_can_view_any_merchant_analytics(): void
    {
        [$vendor, $merchant] = $this->seedVendorMerchant('SUPPORT-MA');

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPPORT,
            'vendor_id' => null,
        ]));

        $this->getJson("/api/admin/merchants/{$merchant->id}/analytics?range=7d")
            ->assertOk()
            ->assertJsonPath('data.kpi.total', 0);
    }
}
