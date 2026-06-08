<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\MerchantCertificate;
use App\Models\Vendor;
use App\Models\WebhookDelivery;
use App\Services\Analytics\MerchantHealthScoreService;
use App\Services\Analytics\VendorHealthScoreService;
use Carbon\Carbon;
use Tests\TestCase;

class VendorHealthScoreServiceTest extends TestCase
{
    private VendorHealthScoreService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VendorHealthScoreService(new MerchantHealthScoreService);
        Carbon::setTestNow('2026-06-08 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedVendorWithMerchant(string $suffix): array
    {
        $vendor = Vendor::create([
            'name' => 'Health Vendor '.$suffix,
            'api_key' => hash('sha256', 'vendor-health-'.$suffix),
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'MRC-VND-'.$suffix,
            'name' => 'Health Merchant '.$suffix,
            'tin' => '111-222-333-000',
        ]);

        return [$vendor, $merchant];
    }

    private function seedInvoice(Merchant $merchant, string $bridgeId, array $overrides = []): Invoice
    {
        $createdAt = $overrides['created_at'] ?? now();
        unset($overrides['created_at']);

        $invoice = Invoice::create(array_merge([
            'bridge_transaction_id' => $bridgeId,
            'transaction_id' => 'POS-'.$bridgeId,
            'merchant_code' => $merchant->merchant_code,
            'branch_code' => 'BR001',
            'pos_device_id' => 'POS01',
            'raw_pos_json' => ['transaction_id' => 'POS-'.$bridgeId],
            'processing_status' => 'sent',
            'eis_status' => 'acknowledged',
        ], $overrides));

        $invoice->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $invoice->fresh();
    }

    public function test_perfect_vendor_scores_near_one_hundred(): void
    {
        [$vendor, $merchant] = $this->seedVendorWithMerchant('PERF');

        MerchantCertificate::create([
            'merchant_id' => $merchant->id,
            'filename' => 'valid.pem',
            'file_path' => 'certs/valid.pem',
            'password_encrypted' => 'encrypted',
            'expires_at' => '2027-06-01',
        ]);

        foreach (range(1, 10) as $index) {
            $invoice = $this->seedInvoice($merchant, 'BRG-VND-PERF-'.$index, [
                'created_at' => '2026-06-07 10:00:00',
            ]);

            WebhookDelivery::create([
                'vendor_id' => $vendor->id,
                'invoice_id' => $invoice->id,
                'event' => 'invoice.acknowledged',
                'request_url' => 'https://example.test/webhook',
                'attempt' => 1,
                'status_code' => 200,
                'success' => true,
                'created_at' => '2026-06-07 10:05:00',
            ]);
        }

        $result = $this->service->getHealthScore($vendor, '30d');

        $this->assertGreaterThanOrEqual(95, $result['score']);
        $this->assertSame('healthy', $result['grade']);
        $this->assertSame(100, $result['pillars']['eis_success_rate']);
        $this->assertSame(0, $result['pillars']['error_rate']);
        $this->assertSame(0, $result['pillars']['retry_pressure']);
        $this->assertSame(100, $result['pillars']['certificate_health']);
        $this->assertSame(100, $result['pillars']['webhook_success']);
        $this->assertSame(100, $result['pillars']['merchant_coverage_health']);
        $this->assertSame(1, $result['merchant_count']);
        $this->assertSame(0, $result['at_risk_merchants']);
    }

    public function test_expired_certificate_lowers_score(): void
    {
        [$vendor, $merchant] = $this->seedVendorWithMerchant('EXP');

        MerchantCertificate::create([
            'merchant_id' => $merchant->id,
            'filename' => 'expired.pem',
            'file_path' => 'certs/expired.pem',
            'password_encrypted' => 'encrypted',
            'expires_at' => '2026-06-01',
        ]);

        $this->seedInvoice($merchant, 'BRG-VND-EXP', [
            'created_at' => '2026-06-07 10:00:00',
        ]);

        $this->seedInvoice($merchant, 'BRG-VND-EXP-FAIL', [
            'processing_status' => 'transmission_failed',
            'eis_status' => null,
            'created_at' => '2026-06-06 10:00:00',
        ]);

        [$healthyVendor, $healthyMerchant] = $this->seedVendorWithMerchant('OK');

        MerchantCertificate::create([
            'merchant_id' => $healthyMerchant->id,
            'filename' => 'valid.pem',
            'file_path' => 'certs/valid.pem',
            'password_encrypted' => 'encrypted',
            'expires_at' => '2027-06-01',
        ]);

        $this->seedInvoice($healthyMerchant, 'BRG-VND-OK', [
            'created_at' => '2026-06-07 10:00:00',
        ]);

        $expiredResult = $this->service->getHealthScore($vendor, '30d');
        $healthyResult = $this->service->getHealthScore($healthyVendor, '30d');

        $this->assertSame(0, $expiredResult['pillars']['certificate_health']);
        $this->assertSame(100, $healthyResult['pillars']['certificate_health']);
        $this->assertLessThan($healthyResult['score'], $expiredResult['score']);
        $this->assertGreaterThanOrEqual(1, $expiredResult['at_risk_merchants']);
    }

    public function test_errors_and_retries_reduce_score(): void
    {
        [$vendor, $merchant] = $this->seedVendorWithMerchant('ERR');

        MerchantCertificate::create([
            'merchant_id' => $merchant->id,
            'filename' => 'valid.pem',
            'file_path' => 'certs/valid.pem',
            'password_encrypted' => 'encrypted',
            'expires_at' => '2027-06-01',
        ]);

        $this->seedInvoice($merchant, 'BRG-VND-ACK', [
            'created_at' => '2026-06-07 10:00:00',
        ]);

        $this->seedInvoice($merchant, 'BRG-VND-RETRY', [
            'processing_status' => 'retry_failed',
            'eis_status' => null,
            'created_at' => '2026-06-06 10:00:00',
        ]);

        $this->seedInvoice($merchant, 'BRG-VND-TXF', [
            'processing_status' => 'transmission_failed',
            'eis_status' => null,
            'created_at' => '2026-06-05 10:00:00',
        ]);

        $this->seedInvoice($merchant, 'BRG-VND-REJ', [
            'processing_status' => 'sent',
            'eis_status' => 'rejected',
            'created_at' => '2026-06-04 10:00:00',
        ]);

        $result = $this->service->getHealthScore($vendor, '30d');

        $this->assertSame(25, $result['pillars']['eis_success_rate']);
        $this->assertSame(50, $result['pillars']['error_rate']);
        $this->assertSame(25, $result['pillars']['retry_pressure']);
        $this->assertLessThan(80, $result['score']);
    }

    public function test_trend_reflects_prior_period_change(): void
    {
        [$vendor, $merchant] = $this->seedVendorWithMerchant('TRD');

        MerchantCertificate::create([
            'merchant_id' => $merchant->id,
            'filename' => 'valid.pem',
            'file_path' => 'certs/valid.pem',
            'password_encrypted' => 'encrypted',
            'expires_at' => '2027-06-01',
        ]);

        $this->seedInvoice($merchant, 'BRG-VND-CUR', [
            'created_at' => '2026-06-07 10:00:00',
        ]);

        $this->seedInvoice($merchant, 'BRG-VND-PRIOR-BAD', [
            'processing_status' => 'retry_failed',
            'eis_status' => null,
            'created_at' => '2026-05-01 10:00:00',
        ]);

        $result = $this->service->getHealthScore($vendor, '30d');

        $this->assertSame('up', $result['trend']);
    }
}
