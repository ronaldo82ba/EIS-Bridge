<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WebhookDelivery;
use App\Services\Security\VendorApiKeyService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminVendorDetailTest extends TestCase
{
    private function seedVendorWithMerchant(string $suffix = '001'): array
    {
        $apiKeyService = app(VendorApiKeyService::class);
        $plainKey = 'vb_test_key_'.$suffix;

        $vendor = Vendor::create([
            'name' => 'Vendor '.$suffix,
            'api_key' => $apiKeyService->hashKey($plainKey),
            'webhook_url' => 'https://example.test/webhook',
            'webhook_secret' => 'secret-12345678',
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'MRC-'.$suffix,
            'name' => 'Merchant '.$suffix,
            'tin' => '123-456-789-000',
        ]);

        return [$vendor, $merchant, $plainKey];
    }

    public function test_admin_vendor_show_includes_merchants_webhook_deliveries_and_stats(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        [$vendor, $merchant] = $this->seedVendorWithMerchant('SHOW');

        Invoice::create([
            'bridge_transaction_id' => 'BRG-SHOW-1',
            'transaction_id' => 'POS-SHOW-1',
            'merchant_code' => $merchant->merchant_code,
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => ['transaction_id' => 'POS-SHOW-1'],
            'processing_status' => 'completed',
            'eis_status' => 'acknowledged',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Invoice::create([
            'bridge_transaction_id' => 'BRG-SHOW-2',
            'transaction_id' => 'POS-SHOW-2',
            'merchant_code' => $merchant->merchant_code,
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => ['transaction_id' => 'POS-SHOW-2'],
            'processing_status' => 'failed',
            'eis_status' => 'rejected',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        WebhookDelivery::create([
            'vendor_id' => $vendor->id,
            'event' => 'invoice.acknowledged',
            'request_url' => $vendor->webhook_url,
            'attempt' => 1,
            'status_code' => 200,
            'success' => true,
        ]);

        WebhookDelivery::create([
            'vendor_id' => $vendor->id,
            'event' => 'invoice.failed',
            'request_url' => $vendor->webhook_url,
            'attempt' => 2,
            'status_code' => 500,
            'success' => false,
        ]);

        $response = $this->getJson("/api/admin/vendors/{$vendor->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $vendor->id)
            ->assertJsonPath('data.name', $vendor->name)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'api_key_masked',
                    'merchants',
                    'webhook_deliveries',
                    'stats' => [
                        'today_total',
                        'today_ack',
                        'today_rejected',
                        'webhook_failures',
                    ],
                ],
            ]);

        $payload = $response->json('data');

        $this->assertCount(1, $payload['merchants']);
        $this->assertSame($merchant->id, $payload['merchants'][0]['id']);
        $this->assertCount(2, $payload['webhook_deliveries']);
        $this->assertSame(2, $payload['stats']['today_total']);
        $this->assertSame(1, $payload['stats']['today_ack']);
        $this->assertSame(1, $payload['stats']['today_rejected']);
        $this->assertSame(1, $payload['stats']['webhook_failures']);
        $this->assertStringStartsWith('vb_****', $payload['api_key_masked']);
        $this->assertArrayNotHasKey('api_key', $payload);
        $this->assertArrayNotHasKey('webhook_secret', $payload);
    }

    public function test_vendor_admin_can_view_scoped_vendor(): void
    {
        [$vendor] = $this->seedVendorWithMerchant('OWN');

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_VENDOR_ADMIN,
            'vendor_id' => $vendor->id,
        ]));

        $this->getJson("/api/admin/vendors/{$vendor->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $vendor->id);
    }

    public function test_vendor_admin_cannot_view_other_vendor(): void
    {
        [$ownVendor] = $this->seedVendorWithMerchant('OWN-SCOPE');
        [$otherVendor] = $this->seedVendorWithMerchant('OTHER-SCOPE');

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_VENDOR_ADMIN,
            'vendor_id' => $ownVendor->id,
        ]));

        $this->getJson("/api/admin/vendors/{$otherVendor->id}")
            ->assertForbidden();
    }

    public function test_support_user_can_view_any_vendor(): void
    {
        [$vendor] = $this->seedVendorWithMerchant('SUPPORT');

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPPORT,
            'vendor_id' => null,
        ]));

        $this->getJson("/api/admin/vendors/{$vendor->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $vendor->id);
    }

    public function test_admin_vendor_store_returns_vendor_identifier_for_redirects(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        $response = $this->postJson('/api/admin/vendors', [
            'name' => 'Redirect Vendor',
            'webhook_url' => 'https://example.test/redirect-webhook',
        ])
            ->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                ],
                'api_key',
            ]);

        $this->assertIsInt($response->json('data.id'));
        $this->assertSame('Redirect Vendor', $response->json('data.name'));
    }

    public function test_admin_vendor_store_rejects_private_webhook_url(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        $this->postJson('/api/admin/vendors', [
            'name' => 'Private Webhook Vendor',
            'webhook_url' => 'https://127.0.0.1/webhook',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'validation_error')
            ->assertJsonStructure(['details' => ['webhook_url']]);
    }

    public function test_admin_vendor_update_rejects_private_webhook_url(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        [$vendor] = $this->seedVendorWithMerchant('PRIVATE-UPDATE');

        $this->patchJson("/api/admin/vendors/{$vendor->id}", [
            'webhook_url' => 'http://localhost/webhook',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'validation_error')
            ->assertJsonStructure(['details' => ['webhook_url']]);
    }

    public function test_admin_webhook_update_rejects_private_webhook_url(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        [$vendor] = $this->seedVendorWithMerchant('PRIVATE-WEBHOOK');

        $this->patchJson("/api/admin/webhooks/{$vendor->id}", [
            'webhook_url' => 'https://10.0.0.5/webhook',
            'webhook_secret' => 'secret-12345678',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'validation_error')
            ->assertJsonStructure(['details' => ['webhook_url']]);
    }
}
