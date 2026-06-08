<?php

namespace Tests\Feature;

use App\Models\CertificateAlert;
use App\Models\Merchant;
use App\Models\MerchantCertificate;
use App\Models\User;
use App\Models\Vendor;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCertificateAlertTest extends TestCase
{
    private function seedCertificateAlert(): array
    {
        $vendor = Vendor::create([
            'name' => 'Alert Vendor',
            'api_key' => hash('sha256', 'alert-vendor-key'),
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'ALERT-001',
            'name' => 'Alert Merchant',
            'tin' => '111-222-333-000',
        ]);

        $certificate = MerchantCertificate::create([
            'merchant_id' => $merchant->id,
            'filename' => 'cert.pfx',
            'file_path' => 'certificates/test/cert.pfx',
            'password_encrypted' => 'encrypted',
            'expires_at' => now()->addDays(5),
        ]);

        $alert = CertificateAlert::create([
            'certificate_id' => $certificate->id,
            'level' => CertificateAlert::LEVEL_EXPIRING_7,
        ]);

        return [$vendor, $merchant, $certificate, $alert];
    }

    public function test_certificate_alerts_index_returns_recent_alerts_with_relations(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        [, $merchant, $certificate, $alert] = $this->seedCertificateAlert();

        $response = $this->getJson('/api/admin/certificate-alerts')
            ->assertOk();

        $response->assertJsonPath('data.0.id', $alert->id)
            ->assertJsonPath('data.0.level', CertificateAlert::LEVEL_EXPIRING_7)
            ->assertJsonPath('data.0.certificate.id', $certificate->id)
            ->assertJsonPath('data.0.certificate.merchant.id', $merchant->id)
            ->assertJsonPath('data.0.certificate.merchant.merchant_code', 'ALERT-001');
    }

    public function test_dashboard_includes_certificate_alerts_summary(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        $this->seedCertificateAlert();

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('certificate_alerts.count', 1)
            ->assertJsonPath('certificate_alerts.recent.0.level', CertificateAlert::LEVEL_EXPIRING_7);
    }

    public function test_merchant_show_includes_certificate_expiry_alert(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        [, $merchant] = $this->seedCertificateAlert();

        $this->getJson("/api/admin/merchants/{$merchant->id}")
            ->assertOk()
            ->assertJsonPath('data.certificate.expiry_alert', CertificateAlert::LEVEL_EXPIRING_7);
    }

    public function test_certificate_show_includes_alert_history(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        [, , $certificate, $alert] = $this->seedCertificateAlert();

        $this->getJson("/api/admin/certificates/{$certificate->id}")
            ->assertOk()
            ->assertJsonPath('data.alerts.0.id', $alert->id)
            ->assertJsonPath('data.alerts.0.level', CertificateAlert::LEVEL_EXPIRING_7);
    }
}
