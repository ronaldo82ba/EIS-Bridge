<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\Device;
use App\Models\Merchant;
use App\Models\MerchantCertificate;
use App\Models\MerchantPtt;
use App\Models\Vendor;
use App\Services\Certificate\TestCertificateGenerator;
use App\Services\Onboarding\MerchantReadinessService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MerchantReadinessServiceTest extends TestCase
{
    private function seedCompleteMerchant(bool $withCertificate = true): Merchant
    {
        $vendor = Vendor::create([
            'name' => 'Readiness Vendor',
            'api_key' => hash('sha256', 'readiness-key'),
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'READY001',
            'name' => 'ABC Store',
            'tin' => '123-456-789-000',
            'address' => '123 Test Street',
            'status' => 'active',
        ]);

        $branch = Branch::create([
            'merchant_id' => $merchant->id,
            'branch_code' => 'BR001',
            'name' => 'Main Branch',
            'status' => 'active',
        ]);

        Device::create([
            'branch_id' => $branch->id,
            'pos_device_id' => 'POS01',
            'name' => 'POS Terminal 01',
            'status' => 'active',
        ]);

        MerchantPtt::create([
            'merchant_id' => $merchant->id,
            'ptt_number' => 'PTT-2026-001',
            'valid_from' => Carbon::today()->subDay(),
            'valid_to' => Carbon::today()->addYear(),
            'status' => 'active',
        ]);

        if ($withCertificate) {
            $this->storeTestCertificate($merchant);
        }

        return $merchant->fresh(['branches.devices', 'ptt', 'certificates']);
    }

    private function storeTestCertificate(Merchant $merchant): void
    {
        $disk = (string) config('security.certificate_disk', 'local');
        $relativePath = "certificates/{$merchant->id}/test-cert.pfx";
        $absolutePath = Storage::disk($disk)->path($relativePath);

        app(TestCertificateGenerator::class)->generate(
            $absolutePath,
            TestCertificateGenerator::DEFAULT_PASSWORD,
            'Readiness Test Merchant',
        );

        MerchantCertificate::create([
            'merchant_id' => $merchant->id,
            'filename' => 'test-cert.pfx',
            'file_path' => Crypt::encryptString($relativePath),
            'password_encrypted' => Crypt::encryptString(TestCertificateGenerator::DEFAULT_PASSWORD),
            'expires_at' => now()->addYear(),
        ]);
    }

    public function test_merchant_is_ready_when_all_checks_pass(): void
    {
        $merchant = $this->seedCompleteMerchant();

        $report = app(MerchantReadinessService::class)->assess($merchant);

        $this->assertSame('ABC Store', $report['merchant']);
        $this->assertTrue($report['ready']);
        $this->assertTrue($report['checks']['merchant_info']);
        $this->assertTrue($report['checks']['branches']);
        $this->assertTrue($report['checks']['devices']);
        $this->assertTrue($report['checks']['certificate']);
        $this->assertTrue($report['checks']['ptt']);
        $this->assertTrue($report['checks']['signing_test']);
        $this->assertTrue($report['checks']['mapping_test']);
    }

    public function test_merchant_is_not_ready_when_certificate_missing(): void
    {
        $merchant = $this->seedCompleteMerchant(withCertificate: false);

        $report = app(MerchantReadinessService::class)->assess($merchant);

        $this->assertFalse($report['ready']);
        $this->assertFalse($report['checks']['certificate']);
        $this->assertFalse($report['checks']['signing_test']);
    }
}
