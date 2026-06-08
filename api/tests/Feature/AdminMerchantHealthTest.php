<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\MerchantCertificate;
use App\Models\User;
use App\Models\Vendor;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminMerchantHealthTest extends TestCase
{
    private function seedVendorMerchant(string $suffix): array
    {
        $vendor = Vendor::create([
            'name' => 'Vendor '.$suffix,
            'api_key' => hash('sha256', 'health-'.$suffix),
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

    public function test_health_endpoint_returns_expected_shape(): void
    {
        [$vendor, $merchant] = $this->seedVendorMerchant('HLT-API');

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        MerchantCertificate::create([
            'merchant_id' => $merchant->id,
            'filename' => 'valid.pem',
            'file_path' => 'certs/valid.pem',
            'password_encrypted' => 'encrypted',
            'expires_at' => now()->addYear(),
        ]);

        Invoice::create([
            'bridge_transaction_id' => 'BRG-HLT-API',
            'transaction_id' => 'POS-HLT-API',
            'merchant_code' => $merchant->merchant_code,
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => ['transaction_id' => 'POS-HLT-API'],
            'processing_status' => 'sent',
            'eis_status' => 'acknowledged',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $this->getJson("/api/admin/merchants/{$merchant->id}/health?range=30d")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'score',
                    'grade',
                    'pillars' => [
                        'eis_success_rate',
                        'error_rate',
                        'retry_pressure',
                        'certificate',
                        'webhook_success',
                    ],
                    'trend',
                ],
            ])
            ->assertJsonPath('data.pillars.eis_success_rate', 100)
            ->assertJsonPath('data.pillars.certificate', 100)
            ->assertJsonPath('data.grade', 'healthy');
    }

    public function test_vendor_admin_can_view_own_merchant_health(): void
    {
        [$vendor, $merchant] = $this->seedVendorMerchant('HLT-OWN');

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_VENDOR_ADMIN,
            'vendor_id' => $vendor->id,
        ]));

        $this->getJson("/api/admin/merchants/{$merchant->id}/health?range=30d")
            ->assertOk()
            ->assertJsonStructure(['data' => ['score', 'grade', 'pillars', 'trend']]);
    }

    public function test_vendor_admin_cannot_view_other_vendor_merchant_health(): void
    {
        [$ownVendor] = $this->seedVendorMerchant('HLT-OWN-SCOPE');
        [, $otherMerchant] = $this->seedVendorMerchant('HLT-OTHER-SCOPE');

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_VENDOR_ADMIN,
            'vendor_id' => $ownVendor->id,
        ]));

        $this->getJson("/api/admin/merchants/{$otherMerchant->id}/health?range=30d")
            ->assertForbidden();
    }

    public function test_support_user_can_view_any_merchant_health(): void
    {
        [, $merchant] = $this->seedVendorMerchant('HLT-SUPPORT');

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPPORT,
            'vendor_id' => null,
        ]));

        $this->getJson("/api/admin/merchants/{$merchant->id}/health?range=30d")
            ->assertOk()
            ->assertJsonPath('data.score', fn ($score) => is_int($score));
    }
}
