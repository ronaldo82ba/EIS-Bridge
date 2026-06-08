<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\User;
use App\Models\Vendor;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAlertsTest extends TestCase
{
    private function seedAlert(string $category = Alert::CATEGORY_PROCESSING): array
    {
        $vendor = Vendor::create([
            'name' => 'Alerts Vendor',
            'api_key' => hash('sha256', 'alerts-vendor-key'),
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'ALRT-001',
            'name' => 'Alerts Merchant',
            'tin' => '999-888-777-000',
        ]);

        $invoice = Invoice::create([
            'bridge_transaction_id' => 'BRG-ALRT-001',
            'transaction_id' => 'TX-ALRT',
            'merchant_code' => $merchant->merchant_code,
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'processing_status' => 'failed',
            'raw_pos_json' => ['test' => true],
        ]);

        $alert = Alert::create([
            'category' => $category,
            'type' => 'processing_failure',
            'severity' => Alert::SEVERITY_WARNING,
            'title' => 'Processing failed for invoice BRG-ALRT-001',
            'message' => 'Processing failed',
            'details' => ['message' => 'test'],
            'merchant_id' => $merchant->id,
            'invoice_id' => $invoice->id,
            'vendor_id' => $vendor->id,
        ]);

        return [$vendor, $merchant, $invoice, $alert];
    }

    public function test_alerts_index_returns_filtered_open_alerts(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPPORT,
            'vendor_id' => null,
        ]));

        [, $merchant, , $alert] = $this->seedAlert(Alert::CATEGORY_PROCESSING);

        Alert::create([
            'category' => Alert::CATEGORY_EIS,
            'type' => 'eis_rejection',
            'severity' => Alert::SEVERITY_CRITICAL,
            'title' => 'EIS rejected',
            'message' => 'Rejected',
            'resolved_at' => now(),
        ]);

        $response = $this->getJson('/api/admin/alerts?type=processing&status=open&merchant_id='.$merchant->id)
            ->assertOk();

        $response->assertJsonPath('data.0.id', $alert->id)
            ->assertJsonPath('data.0.type', 'processing')
            ->assertJsonPath('data.0.status', 'open')
            ->assertJsonPath('data.0.merchant.id', $merchant->id)
            ->assertJsonPath('data.0.invoice.bridge_transaction_id', 'BRG-ALRT-001');

        $this->assertCount(1, $response->json('data'));
    }

    public function test_alerts_index_filters_by_vendor(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPPORT,
            'vendor_id' => null,
        ]));

        [$vendor, , , $alert] = $this->seedAlert(Alert::CATEGORY_WEBHOOK);

        $response = $this->getJson('/api/admin/alerts?vendor_id='.$vendor->id.'&status=open')
            ->assertOk();

        $response->assertJsonPath('data.0.id', $alert->id)
            ->assertJsonPath('data.0.vendor.id', $vendor->id);
    }

    public function test_alerts_resolve_marks_alert_resolved(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        [, , , $alert] = $this->seedAlert();

        $this->postJson("/api/admin/alerts/{$alert->id}/resolve")
            ->assertOk()
            ->assertJsonPath('alert.status', 'resolved');

        $this->assertNotNull($alert->fresh()->resolved_at);
    }
}
