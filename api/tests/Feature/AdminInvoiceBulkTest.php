<?php

namespace Tests\Feature;

use App\Jobs\Bulk\RetryMappingJob;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminInvoiceBulkTest extends TestCase
{
    private function seedVendorMerchant(string $merchantCode): array
    {
        $vendor = Vendor::create([
            'name' => 'Bulk Vendor '.$merchantCode,
            'api_key' => hash('sha256', 'bulk-'.$merchantCode),
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => $merchantCode,
            'name' => 'Bulk Merchant '.$merchantCode,
            'tin' => '111-222-333-000',
        ]);

        return [$vendor, $merchant];
    }

    private function seedInvoice(string $merchantCode, string $bridgeId, array $overrides = []): Invoice
    {
        return Invoice::create(array_merge([
            'bridge_transaction_id' => $bridgeId,
            'transaction_id' => 'POS-'.$bridgeId,
            'merchant_code' => $merchantCode,
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => ['transaction_id' => 'POS-'.$bridgeId],
            'processing_status' => 'failed',
            'eis_status' => null,
        ], $overrides));
    }

    public function test_bulk_retry_mapping_queues_jobs(): void
    {
        Queue::fake();

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        $this->seedVendorMerchant('MRC-BULK-001');
        $invoiceA = $this->seedInvoice('MRC-BULK-001', 'BRG-BULK-A');
        $invoiceB = $this->seedInvoice('MRC-BULK-001', 'BRG-BULK-B');

        $this->postJson('/api/admin/invoices/bulk', [
            'action' => 'retry_mapping',
            'ids' => [$invoiceA->id, $invoiceB->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.queued', 2);

        Queue::assertPushed(RetryMappingJob::class, fn (RetryMappingJob $job) => $job->invoiceId === $invoiceA->id);
        Queue::assertPushed(RetryMappingJob::class, fn (RetryMappingJob $job) => $job->invoiceId === $invoiceB->id);
    }

    public function test_vendor_admin_can_only_bulk_own_invoices(): void
    {
        Queue::fake();

        [$vendor] = $this->seedVendorMerchant('MRC-BULK-OWN');
        $this->seedVendorMerchant('MRC-BULK-OTHER');

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_VENDOR_ADMIN,
            'vendor_id' => $vendor->id,
        ]));

        $ownInvoice = $this->seedInvoice('MRC-BULK-OWN', 'BRG-BULK-OWN');
        $otherInvoice = $this->seedInvoice('MRC-BULK-OTHER', 'BRG-BULK-OTHER');

        $this->postJson('/api/admin/invoices/bulk', [
            'action' => 'retry_mapping',
            'ids' => [$ownInvoice->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.queued', 1);

        Queue::assertPushed(RetryMappingJob::class, 1);

        $this->postJson('/api/admin/invoices/bulk', [
            'action' => 'retry_mapping',
            'ids' => [$otherInvoice->id],
        ])
            ->assertForbidden();

        Queue::assertPushed(RetryMappingJob::class, 1);
    }

    public function test_support_can_bulk_invoices(): void
    {
        Queue::fake();

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPPORT,
            'vendor_id' => null,
        ]));

        $this->seedVendorMerchant('MRC-BULK-SUPPORT');
        $invoice = $this->seedInvoice('MRC-BULK-SUPPORT', 'BRG-BULK-SUPPORT');

        $this->postJson('/api/admin/invoices/bulk', [
            'action' => 'resolve',
            'ids' => [$invoice->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.queued', 1);

        $this->assertSame('resolved', $invoice->fresh()->processing_status);
    }
}
