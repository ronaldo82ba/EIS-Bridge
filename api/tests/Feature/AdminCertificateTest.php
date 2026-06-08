<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\MerchantCertificate;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Certificate\TestCertificateGenerator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCertificateTest extends TestCase
{
    private function actingAsSuperAdmin(): User
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    /** @return array{0: Vendor, 1: Merchant, 2: MerchantCertificate} */
    private function seedCertificate(): array
    {
        $vendor = Vendor::create([
            'name' => 'Certificate Vendor',
            'api_key' => hash('sha256', 'certificate-vendor-key'),
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'CERT-001',
            'name' => 'Certificate Merchant',
            'tin' => '111-222-333-000',
        ]);

        $disk = (string) config('security.certificate_disk', 'local');
        $filename = 'test-cert-'.$merchant->id.'.pfx';
        $relativePath = "certificates/test/{$filename}";
        $absolutePath = Storage::disk($disk)->path($relativePath);

        app(TestCertificateGenerator::class)->generate(
            $absolutePath,
            TestCertificateGenerator::DEFAULT_PASSWORD,
            'Certificate Test Merchant'
        );

        $certificate = MerchantCertificate::create([
            'merchant_id' => $merchant->id,
            'filename' => $filename,
            'file_path' => Crypt::encryptString($relativePath),
            'password_encrypted' => Crypt::encryptString(TestCertificateGenerator::DEFAULT_PASSWORD),
            'expires_at' => now()->addYear(),
            'parsed_at' => now(),
        ]);

        return [$vendor, $merchant, $certificate];
    }

    public function test_certificate_show_does_not_include_password(): void
    {
        $this->actingAsSuperAdmin();
        [, , $certificate] = $this->seedCertificate();

        $response = $this->getJson("/api/admin/certificates/{$certificate->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $certificate->id)
            ->assertJsonPath('data.password_status', 'encrypted')
            ->assertJsonPath('data.filename', $certificate->filename)
            ->assertJsonPath('data.merchant.merchant_code', 'CERT-001');

        $payload = $response->json();
        $encoded = json_encode($payload);

        $this->assertArrayNotHasKey('password_encrypted', $payload['data'] ?? []);
        $this->assertStringNotContainsString('password_encrypted', $encoded);
        $this->assertSame($certificate->filename, $payload['data']['file_path']);
        $this->assertStringContainsString('***', $payload['data']['storage_path_display']);
    }

    public function test_certificate_test_signing_returns_signature_hash(): void
    {
        $this->actingAsSuperAdmin();
        [, , $certificate] = $this->seedCertificate();

        $response = $this->postJson("/api/admin/certificates/{$certificate->id}/test-signing")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('algorithm', 'RS256');

        $signatureHash = $response->json('signature_hash');
        $this->assertIsString($signatureHash);
        $this->assertSame(64, strlen($signatureHash));
    }
}
