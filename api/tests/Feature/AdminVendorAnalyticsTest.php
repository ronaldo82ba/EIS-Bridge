<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WebhookDelivery;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminVendorAnalyticsTest extends TestCase
{
    private function seedVendorMerchant(string $suffix): array
    {
        $vendor = Vendor::create([
            'name' => 'Vendor '.$suffix,
            'api_key' => hash('sha256', 'vendor-'.$suffix),
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
        [$vendor, $merchant] = $this->seedVendorMerchant('API-VA');

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        Invoice::create([
            'bridge_transaction_id' => 'BRG-VA-API',
            'transaction_id' => 'POS-VA-API',
            'merchant_code' => $merchant->merchant_code,
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => ['transaction_id' => 'POS-VA-API'],
            'processing_status' => 'sent',
            'eis_status' => 'acknowledged',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        WebhookDelivery::create([
            'vendor_id' => $vendor->id,
            'event' => 'invoice.acknowledged',
            'request_url' => 'https://example.test/hook',
            'attempt' => 1,
            'status_code' => 200,
            'success' => true,
            'created_at' => now()->subDay(),
        ]);

        $this->getJson("/api/admin/vendors/{$vendor->id}/analytics?range=30d")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'kpi' => ['total', 'ack', 'rejected', 'webhook_failures', 'error_rate', 'eis_ack_rate'],
                    'daily' => ['labels', 'values'],
                    'top_merchants',
                    'webhooks' => ['success', 'failed', 'success_rate'],
                    'certificate_health' => ['valid', 'expiring_30', 'expiring_7', 'expired', 'missing'],
                    'errors',
                ],
            ])
            ->assertJsonPath('data.kpi.total', 1)
            ->assertJsonPath('data.kpi.ack', 1);
    }

    public function test_vendor_admin_can_view_own_vendor_analytics(): void
    {
        [$vendor] = $this->seedVendorMerchant('OWN-VA');

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_VENDOR_ADMIN,
            'vendor_id' => $vendor->id,
        ]));

        $this->getJson("/api/admin/vendors/{$vendor->id}/analytics?range=7d")
            ->assertOk()
            ->assertJsonPath('data.kpi.total', 0);
    }

    public function test_vendor_admin_cannot_view_other_vendor_analytics(): void
    {
        [$ownVendor] = $this->seedVendorMerchant('OWN-SCOPE-VA');
        [$otherVendor] = $this->seedVendorMerchant('OTHER-SCOPE-VA');

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_VENDOR_ADMIN,
            'vendor_id' => $ownVendor->id,
        ]));

        $this->getJson("/api/admin/vendors/{$otherVendor->id}/analytics?range=7d")
            ->assertForbidden();
    }

    public function test_support_user_can_view_any_vendor_analytics(): void
    {
        [$vendor] = $this->seedVendorMerchant('SUPPORT-VA');

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPPORT,
            'vendor_id' => null,
        ]));

        $this->getJson("/api/admin/vendors/{$vendor->id}/analytics?range=7d")
            ->assertOk()
            ->assertJsonPath('data.kpi.total', 0);
    }
}
