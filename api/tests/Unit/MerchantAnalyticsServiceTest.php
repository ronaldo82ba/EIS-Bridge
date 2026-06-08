<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\MerchantCertificate;
use App\Models\TransmissionLog;
use App\Models\Vendor;
use App\Services\Analytics\MerchantAnalyticsService;
use Carbon\Carbon;
use Tests\TestCase;

class MerchantAnalyticsServiceTest extends TestCase
{
    private MerchantAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MerchantAnalyticsService;
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
        $merchant = $this->seedMerchant('MRC-MA-001', 'Alpha Analytics');

        $this->seedInvoice('MRC-MA-001', 'BRG-MA-ACK', [
            'eis_status' => 'acknowledged',
            'processing_status' => 'sent',
            'created_at' => '2026-06-07 10:00:00',
        ]);

        $this->seedInvoice('MRC-MA-001', 'BRG-MA-REJ', [
            'eis_status' => 'rejected',
            'processing_status' => 'sent',
            'created_at' => '2026-06-06 10:00:00',
        ]);

        $this->seedInvoice('MRC-MA-001', 'BRG-MA-RETRY', [
            'eis_status' => null,
            'processing_status' => 'retry_failed',
            'created_at' => '2026-06-05 10:00:00',
        ]);

        $this->seedInvoice('MRC-MA-001', 'BRG-MA-OLD', [
            'eis_status' => 'acknowledged',
            'created_at' => '2026-05-01 10:00:00',
        ]);

        $result = $this->service->getAnalytics($merchant, '7d');

        $this->assertSame(3, $result['kpi']['total']);
        $this->assertSame(1, $result['kpi']['ack']);
        $this->assertSame(1, $result['kpi']['rejected']);
        $this->assertSame(1, $result['kpi']['retry_failed']);
        $this->assertSame(66.67, $result['kpi']['error_rate']);
        $this->assertSame(1, $result['eis_breakdown']['ack']);
        $this->assertSame(1, $result['eis_breakdown']['rejected']);
        $this->assertSame(1, $result['eis_breakdown']['pending']);
        $this->assertSame(1, $result['retry_pressure']['retry_failed']);
        $this->assertSame(0, $result['retry_pressure']['transmission_failed']);
    }

    public function test_branch_volume_joins_branch_names(): void
    {
        $merchant = $this->seedMerchant('MRC-MA-002');

        Branch::create([
            'merchant_id' => $merchant->id,
            'branch_code' => 'BR001',
            'name' => 'Main Branch',
            'address' => '123 Street',
            'status' => 'active',
        ]);

        Branch::create([
            'merchant_id' => $merchant->id,
            'branch_code' => 'BR002',
            'name' => 'Mall Branch',
            'address' => '456 Avenue',
            'status' => 'active',
        ]);

        $this->seedInvoice('MRC-MA-002', 'BRG-MA-B1A', [
            'branch_code' => 'BR001',
            'created_at' => '2026-06-07 10:00:00',
        ]);

        $this->seedInvoice('MRC-MA-002', 'BRG-MA-B1B', [
            'branch_code' => 'BR001',
            'created_at' => '2026-06-06 10:00:00',
        ]);

        $this->seedInvoice('MRC-MA-002', 'BRG-MA-B2', [
            'branch_code' => 'BR002',
            'created_at' => '2026-06-05 10:00:00',
        ]);

        $result = $this->service->getAnalytics($merchant, '7d');

        $branches = collect($result['branch_volume'])->keyBy('branch_code');
        $this->assertSame('Main Branch', $branches->get('BR001')['name']);
        $this->assertSame(2, $branches->get('BR001')['count']);
        $this->assertSame('Mall Branch', $branches->get('BR002')['name']);
        $this->assertSame(1, $branches->get('BR002')['count']);
    }

    public function test_device_volume_returns_top_ten_devices(): void
    {
        $merchant = $this->seedMerchant('MRC-MA-003');

        foreach (range(1, 12) as $index) {
            $this->seedInvoice('MRC-MA-003', 'BRG-MA-DEV-'.$index, [
                'pos_device_id' => 'POS-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'created_at' => '2026-06-07 10:00:00',
            ]);
        }

        $this->seedInvoice('MRC-MA-003', 'BRG-MA-DEV-01-EXTRA', [
            'pos_device_id' => 'POS-01',
            'created_at' => '2026-06-06 10:00:00',
        ]);

        $result = $this->service->getAnalytics($merchant, '7d');

        $this->assertCount(10, $result['device_volume']);
        $this->assertSame('POS-01', $result['device_volume'][0]['pos_device_id']);
        $this->assertSame(2, $result['device_volume'][0]['count']);
    }

    public function test_certificate_health_returns_single_merchant_status(): void
    {
        $merchant = $this->seedMerchant('MRC-MA-004');

        MerchantCertificate::create([
            'merchant_id' => $merchant->id,
            'filename' => 'expired.pem',
            'file_path' => 'certs/expired.pem',
            'password_encrypted' => 'encrypted',
            'expires_at' => '2026-06-01',
        ]);

        $result = $this->service->getAnalytics($merchant, '7d');

        $this->assertSame('expired', $result['certificate_health']['status']);
        $this->assertSame(0, $result['certificate_health']['valid']);
        $this->assertSame(1, $result['certificate_health']['expired']);
        $this->assertSame(0, $result['certificate_health']['missing']);
    }

    public function test_error_breakdown_includes_transmission_log_events(): void
    {
        $merchant = $this->seedMerchant('MRC-MA-005');

        $invoice = $this->seedInvoice('MRC-MA-005', 'BRG-MA-ERR', [
            'processing_status' => 'failed',
            'created_at' => '2026-06-07 10:00:00',
        ]);

        TransmissionLog::create([
            'invoice_id' => $invoice->id,
            'event' => 'mapping_failed',
            'timestamp' => Carbon::parse('2026-06-07 10:05:00'),
        ]);

        $result = $this->service->getAnalytics($merchant, '7d');

        $errors = collect($result['errors'])->pluck('count', 'error');
        $this->assertTrue($errors->has('mapping_failed'));
        $this->assertGreaterThanOrEqual(1, $errors->get('mapping_failed'));
    }
}
