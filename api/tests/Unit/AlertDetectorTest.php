<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\Merchant;
use App\Models\MerchantCertificate;
use App\Models\Vendor;
use App\Services\Observability\AlertDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertDetectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_does_not_create_certificate_expiring_alerts(): void
    {
        $vendor = Vendor::create([
            'name' => 'Test Vendor',
            'api_key' => hash('sha256', 'test-key'),
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'M001',
            'name' => 'Test Merchant',
        ]);

        MerchantCertificate::create([
            'merchant_id' => $merchant->id,
            'filename' => 'cert.p12',
            'file_path' => 'certs/test.p12',
            'password_encrypted' => 'encrypted',
            'expires_at' => now()->addDays(5),
        ]);

        $created = app(AlertDetector::class)->run();

        $this->assertSame(0, $created);
        $this->assertDatabaseMissing('alerts', [
            'type' => Alert::TYPE_CERTIFICATE_EXPIRING,
        ]);
    }
}
