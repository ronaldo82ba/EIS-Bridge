<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WebhookDelivery;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminInvoiceSearchTest extends TestCase
{
    private function seedVendorMerchant(string $merchantCode, string $name = 'Search Merchant'): array
    {
        $vendor = Vendor::create([
            'name' => 'Search Vendor '.$merchantCode,
            'api_key' => hash('sha256', 'search-'.$merchantCode),
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

    private function seedInvoice(
        string $merchantCode,
        string $bridgeId,
        array $overrides = [],
    ): Invoice {
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

    public function test_search_by_bridge_transaction_id(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        [, $merchant] = $this->seedVendorMerchant('MRC-SEARCH-001', 'Alpha Store');
        $invoice = $this->seedInvoice('MRC-SEARCH-001', 'BRG-UNIQUE-12345');
        $this->seedInvoice('MRC-SEARCH-001', 'BRG-OTHER-99999');

        $response = $this->getJson('/api/admin/invoices/search?q=UNIQUE-12345')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $invoice->id)
            ->assertJsonPath('data.0.bridge_transaction_id', 'BRG-UNIQUE-12345')
            ->assertJsonPath('data.0.merchant.name', 'Alpha Store');
    }

    public function test_search_filters_by_date_range(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        $this->seedVendorMerchant('MRC-DATE-001');

        $inRange = $this->seedInvoice('MRC-DATE-001', 'BRG-DATE-IN', [
            'created_at' => '2026-03-10 12:00:00',
        ]);

        $this->seedInvoice('MRC-DATE-001', 'BRG-DATE-OUT', [
            'created_at' => '2026-01-05 12:00:00',
        ]);

        $this->getJson('/api/admin/invoices/search?date_from=2026-03-01&date_to=2026-03-31')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $inRange->id);
    }

    public function test_vendor_admin_search_is_scoped_to_vendor_merchants(): void
    {
        [$vendor] = $this->seedVendorMerchant('MRC-VENDOR-OWN', 'Own Merchant');
        $this->seedVendorMerchant('MRC-VENDOR-OTHER', 'Other Merchant');

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_VENDOR_ADMIN,
            'vendor_id' => $vendor->id,
        ]));

        $ownInvoice = $this->seedInvoice('MRC-VENDOR-OWN', 'BRG-VENDOR-OWN');
        $this->seedInvoice('MRC-VENDOR-OTHER', 'BRG-VENDOR-OTHER');

        $this->getJson('/api/admin/invoices/search')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $ownInvoice->id);

        $this->getJson('/api/admin/invoices/search?q=VENDOR-OTHER')
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_search_status_filter_does_not_break_other_filters(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        $this->seedVendorMerchant('MRC-STATUS-001');

        $matched = $this->seedInvoice('MRC-STATUS-001', 'BRG-STATUS-MATCH', [
            'processing_status' => 'sent',
            'eis_status' => 'acknowledged',
            'created_at' => '2026-04-01 10:00:00',
        ]);

        $this->seedInvoice('MRC-STATUS-001', 'BRG-STATUS-WRONG-DATE', [
            'processing_status' => 'sent',
            'eis_status' => 'acknowledged',
            'created_at' => '2026-02-01 10:00:00',
        ]);

        $this->seedInvoice('MRC-STATUS-001', 'BRG-STATUS-WRONG-STATUS', [
            'processing_status' => 'mapped',
            'eis_status' => null,
            'created_at' => '2026-04-02 10:00:00',
        ]);

        $this->getJson('/api/admin/invoices/search?status=acknowledged&date_from=2026-04-01&date_to=2026-04-30')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $matched->id);
    }

    public function test_search_has_errors_and_webhook_failed_filters(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        [$vendor] = $this->seedVendorMerchant('MRC-ERR-001');

        $failedInvoice = $this->seedInvoice('MRC-ERR-001', 'BRG-ERR-001', [
            'processing_status' => 'transmission_failed',
            'eis_status' => 'rejected',
        ]);

        $webhookFailed = $this->seedInvoice('MRC-ERR-001', 'BRG-WH-001', [
            'processing_status' => 'sent',
            'eis_status' => 'acknowledged',
        ]);

        WebhookDelivery::create([
            'vendor_id' => $vendor->id,
            'invoice_id' => $webhookFailed->id,
            'event' => 'transaction.eis_acknowledged',
            'request_url' => 'https://example.test/webhook',
            'attempt' => 1,
            'status_code' => 500,
            'success' => false,
        ]);

        $this->getJson('/api/admin/invoices/search?has_errors=1')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $failedInvoice->id);

        $this->getJson('/api/admin/invoices/search?webhook_failed=1')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $webhookFailed->id);
    }
}
