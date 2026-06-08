<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\TransmissionLog;
use App\Models\User;
use App\Models\Vendor;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminInvoiceDetailTest extends TestCase
{
    private function seedInvoice(string $merchantCode = 'MRC-INV-001'): Invoice
    {
        return Invoice::create([
            'bridge_transaction_id' => 'BRG-'.uniqid(),
            'transaction_id' => 'POS-001',
            'merchant_code' => $merchantCode,
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => ['transaction_id' => 'POS-001'],
            'bir_json' => ['invoice' => 'bir'],
            'signed_json' => [
                'signature' => base64_encode('secret-signature-blob'),
                'signature_hash' => hash('sha256', 'secret-signature-blob'),
            ],
            'processing_status' => 'failed',
            'eis_status' => 'rejected',
        ]);
    }

    private function seedVendorMerchant(string $merchantCode = 'MRC-INV-001', ?string $apiKeySeed = null): array
    {
        $vendor = Vendor::create([
            'name' => 'Invoice Vendor '.$merchantCode,
            'api_key' => hash('sha256', $apiKeySeed ?? $merchantCode),
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => $merchantCode,
            'name' => 'Invoice Merchant',
            'tin' => '111-222-333-000',
        ]);

        return [$vendor, $merchant];
    }

    public function test_admin_invoice_show_includes_transmission_logs(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        $invoice = $this->seedInvoice();

        TransmissionLog::create([
            'invoice_id' => $invoice->id,
            'event' => 'queued',
            'timestamp' => now()->subMinutes(5),
            'metadata' => ['source' => 'test'],
        ]);

        TransmissionLog::create([
            'invoice_id' => $invoice->id,
            'event' => 'mapped',
            'timestamp' => now()->subMinutes(3),
        ]);

        $response = $this->getJson("/api/admin/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $invoice->id)
            ->assertJsonPath('data.bridge_transaction_id', $invoice->bridge_transaction_id)
            ->assertJsonPath('data.raw_pos_json.transaction_id', 'POS-001')
            ->assertJsonPath('data.bir_json.invoice', 'bir')
            ->assertJsonPath('data.signed_json.signature_hash', $invoice->signed_json['signature_hash']);

        $logs = $response->json('data.transmission_logs');
        $this->assertCount(2, $logs);
        $this->assertSame('queued', $logs[0]['event']);
        $this->assertSame('mapped', $logs[1]['event']);

        $aliasLogs = $response->json('data.logs');
        $this->assertCount(2, $aliasLogs);
    }

    public function test_vendor_admin_can_view_scoped_invoice(): void
    {
        [$vendor] = $this->seedVendorMerchant('MRC-SCOPED');

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_VENDOR_ADMIN,
            'vendor_id' => $vendor->id,
        ]));

        $invoice = $this->seedInvoice('MRC-SCOPED');

        $this->getJson("/api/admin/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('data.merchant_code', 'MRC-SCOPED');
    }

    public function test_vendor_admin_cannot_view_other_vendor_invoice(): void
    {
        [$vendor] = $this->seedVendorMerchant('MRC-OWN', 'vendor-own-key');
        $this->seedVendorMerchant('MRC-OTHER', 'vendor-other-key');

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_VENDOR_ADMIN,
            'vendor_id' => $vendor->id,
        ]));

        $otherInvoice = $this->seedInvoice('MRC-OTHER');

        $this->getJson("/api/admin/invoices/{$otherInvoice->id}")
            ->assertForbidden();
    }
}
