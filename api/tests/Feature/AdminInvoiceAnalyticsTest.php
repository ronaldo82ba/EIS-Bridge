<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\User;
use App\Models\Vendor;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminInvoiceAnalyticsTest extends TestCase
{
    private function seedVendorMerchant(string $merchantCode, string $name = 'Analytics Merchant'): array
    {
        $vendor = Vendor::create([
            'name' => 'Analytics Vendor '.$merchantCode,
            'api_key' => hash('sha256', 'analytics-'.$merchantCode),
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => $merchantCode,
            'name' => $name,
            'tin' => '111-222-333-000',
        ]);

        return [$vendor, $merchant];
    }

    private function seedInvoice(string $merchantCode, string $bridgeId, array $overrides = []): Invoice
    {
        $createdAt = $overrides['created_at'] ?? now();
        unset($overrides['created_at']);

        $invoice = Invoice::create(array_merge([
            'bridge_transaction_id' => $bridgeId,
            'transaction_id' => 'POS-'.$bridgeId,
            'merchant_code' => $merchantCode,
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => ['transaction_id' => 'POS-'.$bridgeId],
            'processing_status' => 'mapped',
            'eis_status' => null,
        ], $overrides));

        $invoice->forceFill(['created_at' => $createdAt])->save();

        return $invoice->fresh();
    }

    public function test_analytics_endpoint_returns_expected_shape(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        $this->seedVendorMerchant('MRC-API-001');
        $this->seedInvoice('MRC-API-001', 'BRG-API-001', [
            'eis_status' => 'acknowledged',
            'created_at' => now()->subDay(),
        ]);

        $this->getJson('/api/admin/invoices/analytics?range=7d')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'kpi' => ['total', 'ack', 'rejected', 'error_rate'],
                    'daily' => ['labels', 'values'],
                    'status_breakdown' => ['acknowledged', 'pending', 'rejected', 'failed'],
                    'top_merchants',
                    'errors',
                    'eis_ack_rate',
                    'retry_pressure' => ['retry_failed', 'transmission_failed'],
                ],
            ])
            ->assertJsonPath('data.kpi.total', 1)
            ->assertJsonPath('data.kpi.ack', 1);
    }

    public function test_vendor_admin_analytics_is_scoped_to_vendor(): void
    {
        [$vendor] = $this->seedVendorMerchant('MRC-V-OWN', 'Own Merchant');
        $this->seedVendorMerchant('MRC-V-OTHER', 'Other Merchant');

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_VENDOR_ADMIN,
            'vendor_id' => $vendor->id,
        ]));

        $this->seedInvoice('MRC-V-OWN', 'BRG-V-OWN', [
            'eis_status' => 'acknowledged',
            'created_at' => now()->subDay(),
        ]);

        $this->seedInvoice('MRC-V-OTHER', 'BRG-V-OTHER', [
            'eis_status' => 'acknowledged',
            'created_at' => now()->subDay(),
        ]);

        $this->getJson('/api/admin/invoices/analytics?range=7d')
            ->assertOk()
            ->assertJsonPath('data.kpi.total', 1)
            ->assertJsonPath('data.top_merchants.0.merchant_code', 'MRC-V-OWN');
    }
}
