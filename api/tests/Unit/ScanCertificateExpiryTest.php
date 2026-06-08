<?php

namespace Tests\Unit;

use App\Jobs\SendCertificateAlertJob;
use App\Models\CertificateAlert;
use App\Models\Merchant;
use App\Models\MerchantCertificate;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ScanCertificateExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_certificate_expiring_in_five_days_creates_expiring_7_alert(): void
    {
        Bus::fake([SendCertificateAlertJob::class]);

        $vendor = Vendor::create([
            'name' => 'Vendor',
            'api_key' => hash('sha256', 'scan-key'),
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'SCAN-001',
            'name' => 'Scan Merchant',
            'tin' => '111-222-333-000',
        ]);

        MerchantCertificate::create([
            'merchant_id' => $merchant->id,
            'filename' => 'cert.pfx',
            'file_path' => 'certificates/test/cert.pfx',
            'password_encrypted' => 'encrypted',
            'expires_at' => now()->addDays(5),
        ]);

        $this->artisan('certificates:scan-expiry')->assertSuccessful();

        $this->assertDatabaseHas('certificate_alerts', [
            'level' => CertificateAlert::LEVEL_EXPIRING_7,
        ]);

        Bus::assertDispatched(SendCertificateAlertJob::class);
    }

    public function test_scan_does_not_create_duplicate_alerts(): void
    {
        Bus::fake([SendCertificateAlertJob::class]);

        $vendor = Vendor::create([
            'name' => 'Vendor',
            'api_key' => hash('sha256', 'scan-key-2'),
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'SCAN-002',
            'name' => 'Scan Merchant 2',
            'tin' => '111-222-333-001',
        ]);

        $certificate = MerchantCertificate::create([
            'merchant_id' => $merchant->id,
            'filename' => 'cert.pfx',
            'file_path' => 'certificates/test/cert2.pfx',
            'password_encrypted' => 'encrypted',
            'expires_at' => now()->addDays(5),
        ]);

        CertificateAlert::create([
            'certificate_id' => $certificate->id,
            'level' => CertificateAlert::LEVEL_EXPIRING_7,
        ]);

        $this->artisan('certificates:scan-expiry')->assertSuccessful();

        $this->assertDatabaseCount('certificate_alerts', 1);
        Bus::assertNotDispatched(SendCertificateAlertJob::class);
    }

    public function test_expired_certificate_creates_expired_alert(): void
    {
        Bus::fake([SendCertificateAlertJob::class]);

        $vendor = Vendor::create([
            'name' => 'Vendor',
            'api_key' => hash('sha256', 'scan-key-3'),
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'SCAN-003',
            'name' => 'Scan Merchant 3',
            'tin' => '111-222-333-002',
        ]);

        MerchantCertificate::create([
            'merchant_id' => $merchant->id,
            'filename' => 'cert.pfx',
            'file_path' => 'certificates/test/cert3.pfx',
            'password_encrypted' => 'encrypted',
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('certificates:scan-expiry')->assertSuccessful();

        $this->assertDatabaseHas('certificate_alerts', [
            'level' => CertificateAlert::LEVEL_EXPIRED,
        ]);
    }
}
