<?php

namespace Tests\Unit;

use App\Models\CertificateAlert;
use App\Models\Merchant;
use App\Models\MerchantCertificate;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_certificate_alerts_are_prevented_by_unique_index(): void
    {
        $vendor = Vendor::create([
            'name' => 'Vendor',
            'api_key' => hash('sha256', 'vendor-key'),
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'DUP-001',
            'name' => 'Duplicate Merchant',
            'tin' => '111-222-333-000',
        ]);

        $certificate = MerchantCertificate::create([
            'merchant_id' => $merchant->id,
            'filename' => 'cert.pfx',
            'file_path' => 'certificates/test/cert.pfx',
            'password_encrypted' => 'encrypted',
            'expires_at' => now()->addDays(5),
        ]);

        CertificateAlert::create([
            'certificate_id' => $certificate->id,
            'level' => CertificateAlert::LEVEL_EXPIRING_7,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        CertificateAlert::create([
            'certificate_id' => $certificate->id,
            'level' => CertificateAlert::LEVEL_EXPIRING_7,
        ]);
    }

    public function test_different_levels_can_exist_for_same_certificate(): void
    {
        $vendor = Vendor::create([
            'name' => 'Vendor',
            'api_key' => hash('sha256', 'vendor-key-2'),
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'LVL-001',
            'name' => 'Level Merchant',
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
            'level' => CertificateAlert::LEVEL_EXPIRING_30,
        ]);

        CertificateAlert::create([
            'certificate_id' => $certificate->id,
            'level' => CertificateAlert::LEVEL_EXPIRING_7,
        ]);

        $this->assertDatabaseCount('certificate_alerts', 2);
    }
}
