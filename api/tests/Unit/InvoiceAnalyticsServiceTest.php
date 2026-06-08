<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\TransmissionLog;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Analytics\InvoiceAnalyticsService;
use Carbon\Carbon;
use Tests\TestCase;

class InvoiceAnalyticsServiceTest extends TestCase
{
    private InvoiceAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InvoiceAnalyticsService;
        Carbon::setTestNow('2026-06-08 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedMerchant(string $code, string $name = 'Analytics Merchant'): Merchant
    {
        $vendor = Vendor::create([
            'name' => 'Analytics Vendor '.$code,
            'api_key' => hash('sha256', 'analytics-'.$code),
            'status' => 'active',
        ]);

        return Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => $code,
            'name' => $name,
            'tin' => '111-222-333-000',
        ]);
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

        $invoice->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $invoice->fresh();
    }

    public function test_returns_correct_kpi_counts_for_range(): void
    {
        $merchant = $this->seedMerchant('MRC-AN-001', 'Alpha Analytics');

        $this->seedInvoice('MRC-AN-001', 'BRG-AN-ACK', [
            'eis_status' => 'acknowledged',
            'processing_status' => 'sent',
            'created_at' => '2026-06-07 10:00:00',
        ]);

        $this->seedInvoice('MRC-AN-001', 'BRG-AN-REJ', [
            'eis_status' => 'rejected',
            'processing_status' => 'sent',
            'created_at' => '2026-06-06 10:00:00',
        ]);

        $this->seedInvoice('MRC-AN-001', 'BRG-AN-FAIL', [
            'eis_status' => null,
            'processing_status' => 'transmission_failed',
            'created_at' => '2026-06-05 10:00:00',
        ]);

        $this->seedInvoice('MRC-AN-001', 'BRG-AN-OLD', [
            'eis_status' => 'acknowledged',
            'created_at' => '2026-05-01 10:00:00',
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]);

        $result = $this->service->getAnalytics($user, '7d');

        $this->assertSame(3, $result['kpi']['total']);
        $this->assertSame(1, $result['kpi']['ack']);
        $this->assertSame(1, $result['kpi']['rejected']);
        $this->assertSame(66.67, $result['kpi']['error_rate']);
        $this->assertSame(50.0, $result['eis_ack_rate']);
        $this->assertCount(1, $result['top_merchants']);
        $this->assertSame('Alpha Analytics', $result['top_merchants'][0]['name']);
        $this->assertSame(3, $result['top_merchants'][0]['count']);
        $this->assertSame(0, $result['retry_pressure']['retry_failed']);
        $this->assertSame(1, $result['retry_pressure']['transmission_failed']);
    }

    public function test_error_breakdown_includes_transmission_log_events(): void
    {
        $merchant = $this->seedMerchant('MRC-AN-002');

        $invoice = $this->seedInvoice('MRC-AN-002', 'BRG-AN-ERR', [
            'processing_status' => 'failed',
            'created_at' => '2026-06-07 10:00:00',
        ]);

        TransmissionLog::create([
            'invoice_id' => $invoice->id,
            'event' => 'mapping_failed',
            'timestamp' => Carbon::parse('2026-06-07 10:05:00'),
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]);

        $result = $this->service->getAnalytics($user, '7d');

        $errors = collect($result['errors'])->pluck('count', 'error');
        $this->assertTrue($errors->has('mapping_failed'));
        $this->assertGreaterThanOrEqual(1, $errors->get('mapping_failed'));
    }
}
