<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\MerchantCertificate;
use App\Models\TransmissionLog;
use App\Models\Vendor;
use App\Models\WebhookDelivery;
use App\Services\Analytics\VendorAnalyticsService;
use Carbon\Carbon;
use Tests\TestCase;

class VendorAnalyticsServiceTest extends TestCase
{
    private VendorAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VendorAnalyticsService;
        Carbon::setTestNow('2026-06-08 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedVendorWithMerchants(): array
    {
        $vendor = Vendor::create([
            'name' => 'Vendor Analytics Co',
            'api_key' => hash('sha256', 'vendor-analytics'),
            'status' => 'active',
        ]);

        $alpha = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'MRC-VA-001',
            'name' => 'Alpha Merchant',
            'tin' => '111-222-333-000',
        ]);

        $beta = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'MRC-VA-002',
            'name' => 'Beta Merchant',
            'tin' => '111-222-333-001',
        ]);

        return [$vendor, $alpha, $beta];
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

    public function test_returns_vendor_scoped_kpi_and_daily_series(): void
    {
        [$vendor, $alpha, $beta] = $this->seedVendorWithMerchants();

        $this->seedInvoice('MRC-VA-001', 'BRG-VA-ACK', [
            'eis_status' => 'acknowledged',
            'created_at' => '2026-06-07 10:00:00',
        ]);

        $this->seedInvoice('MRC-VA-001', 'BRG-VA-REJ', [
            'eis_status' => 'rejected',
            'created_at' => '2026-06-06 10:00:00',
        ]);

        $this->seedInvoice('MRC-VA-002', 'BRG-VA-BETA', [
            'eis_status' => 'acknowledged',
            'created_at' => '2026-06-05 10:00:00',
        ]);

        $this->seedInvoice('MRC-VA-001', 'BRG-VA-OLD', [
            'eis_status' => 'acknowledged',
            'created_at' => '2026-04-01 10:00:00',
        ]);

        WebhookDelivery::create([
            'vendor_id' => $vendor->id,
            'event' => 'invoice.acknowledged',
            'request_url' => 'https://example.test/hook',
            'attempt' => 1,
            'status_code' => 200,
            'success' => true,
            'created_at' => '2026-06-07 11:00:00',
        ]);

        WebhookDelivery::create([
            'vendor_id' => $vendor->id,
            'event' => 'invoice.failed',
            'request_url' => 'https://example.test/hook',
            'attempt' => 2,
            'status_code' => 500,
            'success' => false,
            'created_at' => '2026-06-06 11:00:00',
        ]);

        $result = $this->service->getAnalytics($vendor, '30d');

        $this->assertSame(3, $result['kpi']['total']);
        $this->assertSame(2, $result['kpi']['ack']);
        $this->assertSame(1, $result['kpi']['rejected']);
        $this->assertSame(1, $result['kpi']['webhook_failures']);
        $this->assertSame(33.33, $result['kpi']['error_rate']);
        $this->assertSame(66.7, $result['kpi']['eis_ack_rate']);
        $this->assertCount(31, $result['daily']['labels']);
        $this->assertSame(3, array_sum($result['daily']['values']));
        $this->assertCount(2, $result['top_merchants']);
        $this->assertSame('Alpha Merchant', $result['top_merchants'][0]['name']);
        $this->assertSame(2, $result['top_merchants'][0]['count']);
        $this->assertSame(1, $result['webhooks']['success']);
        $this->assertSame(1, $result['webhooks']['failed']);
        $this->assertSame(50.0, $result['webhooks']['success_rate']);
    }

    public function test_certificate_health_counts_merchants_by_expiry_status(): void
    {
        [$vendor, $alpha, $beta] = $this->seedVendorWithMerchants();

        MerchantCertificate::create([
            'merchant_id' => $alpha->id,
            'filename' => 'valid.pem',
            'file_path' => 'certs/valid.pem',
            'password_encrypted' => 'encrypted',
            'expires_at' => '2026-08-01',
        ]);

        MerchantCertificate::create([
            'merchant_id' => $beta->id,
            'filename' => 'expired.pem',
            'file_path' => 'certs/expired.pem',
            'password_encrypted' => 'encrypted',
            'expires_at' => '2026-06-01',
        ]);

        $result = $this->service->getAnalytics($vendor, '30d');

        $this->assertSame(1, $result['certificate_health']['valid']);
        $this->assertSame(0, $result['certificate_health']['expiring_30']);
        $this->assertSame(0, $result['certificate_health']['expiring_7']);
        $this->assertSame(1, $result['certificate_health']['expired']);
        $this->assertSame(0, $result['certificate_health']['missing']);
    }

    public function test_error_breakdown_includes_transmission_log_events(): void
    {
        [$vendor] = $this->seedVendorWithMerchants();

        $invoice = $this->seedInvoice('MRC-VA-001', 'BRG-VA-ERR', [
            'processing_status' => 'failed',
            'created_at' => '2026-06-07 10:00:00',
        ]);

        TransmissionLog::create([
            'invoice_id' => $invoice->id,
            'event' => 'mapping_failed',
            'timestamp' => Carbon::parse('2026-06-07 10:05:00'),
        ]);

        $result = $this->service->getAnalytics($vendor, '30d');

        $errors = collect($result['errors'])->pluck('count', 'error');
        $this->assertTrue($errors->has('mapping_failed'));
        $this->assertGreaterThanOrEqual(1, $errors->get('mapping_failed'));
    }
}
