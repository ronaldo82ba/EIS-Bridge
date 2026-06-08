<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\MerchantCertificate;
use App\Models\Vendor;
use App\Models\WebhookDelivery;
use App\Services\Analytics\MerchantHealthScoreService;
use Carbon\Carbon;
use Tests\TestCase;

class MerchantHealthScoreServiceTest extends TestCase
{
    private MerchantHealthScoreService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MerchantHealthScoreService;
        Carbon::setTestNow('2026-06-08 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedMerchant(string $code): Merchant
    {
        $vendor = Vendor::create([
            'name' => 'Health Vendor '.$code,
            'api_key' => hash('sha256', 'health-'.$code),
            'status' => 'active',
        ]);

        return Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => $code,
            'name' => 'Health Merchant '.$code,
            'tin' => '111-222-333-000',
        ]);
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

    public function test_perfect_merchant_scores_near_one_hundred(): void
    {
        $merchant = $this->seedMerchant('MRC-HLT-001');

        MerchantCertificate::create([
            'merchant_id' => $merchant->id,
            'filename' => 'valid.pem',
            'file_path' => 'certs/valid.pem',
            'password_encrypted' => 'encrypted',
            'expires_at' => '2027-06-01',
        ]);

        foreach (range(1, 10) as $index) {
            $invoice = $this->seedInvoice($merchant, 'BRG-HLT-PERF-'.$index, [
                'created_at' => '2026-06-07 10:00:00',
            ]);

            WebhookDelivery::create([
                'vendor_id' => $merchant->vendor_id,
                'invoice_id' => $invoice->id,
                'event' => 'invoice.acknowledged',
                'request_url' => 'https://example.test/webhook',
                'attempt' => 1,
                'status_code' => 200,
                'success' => true,
                'created_at' => '2026-06-07 10:05:00',
            ]);
        }

        $result = $this->service->getHealthScore($merchant, '30d');

        $this->assertGreaterThanOrEqual(95, $result['score']);
        $this->assertSame('healthy', $result['grade']);
        $this->assertSame(100, $result['pillars']['eis_success_rate']);
        $this->assertSame(0, $result['pillars']['error_rate']);
        $this->assertSame(0, $result['pillars']['retry_pressure']);
        $this->assertSame(100, $result['pillars']['certificate']);
        $this->assertSame(100, $result['pillars']['webhook_success']);
    }

    public function test_expired_certificate_lowers_score(): void
    {
        $merchant = $this->seedMerchant('MRC-HLT-002');

        MerchantCertificate::create([
            'merchant_id' => $merchant->id,
            'filename' => 'expired.pem',
            'file_path' => 'certs/expired.pem',
            'password_encrypted' => 'encrypted',
            'expires_at' => '2026-06-01',
        ]);

        $this->seedInvoice($merchant, 'BRG-HLT-EXP', [
            'created_at' => '2026-06-07 10:00:00',
        ]);

        $healthy = $this->seedMerchant('MRC-HLT-003');
        MerchantCertificate::create([
            'merchant_id' => $healthy->id,
            'filename' => 'valid.pem',
            'file_path' => 'certs/valid.pem',
            'password_encrypted' => 'encrypted',
            'expires_at' => '2027-06-01',
        ]);
        $this->seedInvoice($healthy, 'BRG-HLT-OK', [
            'created_at' => '2026-06-07 10:00:00',
        ]);

        $expiredResult = $this->service->getHealthScore($merchant, '30d');
        $healthyResult = $this->service->getHealthScore($healthy, '30d');

        $this->assertSame(0, $expiredResult['pillars']['certificate']);
        $this->assertSame(100, $healthyResult['pillars']['certificate']);
        $this->assertLessThan($healthyResult['score'], $expiredResult['score']);
    }

    public function test_errors_and_retries_reduce_score(): void
    {
        $merchant = $this->seedMerchant('MRC-HLT-004');

        MerchantCertificate::create([
            'merchant_id' => $merchant->id,
            'filename' => 'valid.pem',
            'file_path' => 'certs/valid.pem',
            'password_encrypted' => 'encrypted',
            'expires_at' => '2027-06-01',
        ]);

        $this->seedInvoice($merchant, 'BRG-HLT-ACK', [
            'created_at' => '2026-06-07 10:00:00',
        ]);

        $this->seedInvoice($merchant, 'BRG-HLT-RETRY', [
            'processing_status' => 'retry_failed',
            'eis_status' => null,
            'created_at' => '2026-06-06 10:00:00',
        ]);

        $this->seedInvoice($merchant, 'BRG-HLT-TXF', [
            'processing_status' => 'transmission_failed',
            'eis_status' => null,
            'created_at' => '2026-06-05 10:00:00',
        ]);

        $this->seedInvoice($merchant, 'BRG-HLT-REJ', [
            'processing_status' => 'sent',
            'eis_status' => 'rejected',
            'created_at' => '2026-06-04 10:00:00',
        ]);

        $result = $this->service->getHealthScore($merchant, '30d');

        $this->assertSame(25, $result['pillars']['eis_success_rate']);
        $this->assertSame(50, $result['pillars']['error_rate']);
        $this->assertSame(25, $result['pillars']['retry_pressure']);
        $this->assertLessThan(80, $result['score']);
    }

    public function test_trend_reflects_prior_period_change(): void
    {
        $merchant = $this->seedMerchant('MRC-HLT-005');

        MerchantCertificate::create([
            'merchant_id' => $merchant->id,
            'filename' => 'valid.pem',
            'file_path' => 'certs/valid.pem',
            'password_encrypted' => 'encrypted',
            'expires_at' => '2027-06-01',
        ]);

        $this->seedInvoice($merchant, 'BRG-HLT-CUR', [
            'created_at' => '2026-06-07 10:00:00',
        ]);

        $this->seedInvoice($merchant, 'BRG-HLT-PRIOR-BAD', [
            'processing_status' => 'retry_failed',
            'eis_status' => null,
            'created_at' => '2026-05-01 10:00:00',
        ]);

        $result = $this->service->getHealthScore($merchant, '30d');

        $this->assertSame('up', $result['trend']);
    }
}
