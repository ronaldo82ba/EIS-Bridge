<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\MerchantCertificate;
use App\Services\Certificate\TestCertificateGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class CertificateTestSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::orderBy('id')->first();

        if (! $merchant) {
            $this->command?->warn('CertificateTestSeeder skipped: no merchants found.');

            return;
        }

        if (MerchantCertificate::where('merchant_id', $merchant->id)->exists()) {
            $this->command?->info("CertificateTestSeeder skipped: merchant [{$merchant->id}] already has a certificate.");

            return;
        }

        $generator = app(TestCertificateGenerator::class);
        $disk = (string) config('security.certificate_disk', 'local');
        $filename = 'test-merchant-'.$merchant->id.'.pfx';
        $relativePath = "certificates/test/{$filename}";
        $absolutePath = Storage::disk($disk)->path($relativePath);

        $generator->generate(
            $absolutePath,
            TestCertificateGenerator::DEFAULT_PASSWORD,
            $merchant->name.' Test'
        );

        MerchantCertificate::create([
            'merchant_id' => $merchant->id,
            'filename' => $filename,
            'file_path' => Crypt::encryptString($relativePath),
            'password_encrypted' => Crypt::encryptString(TestCertificateGenerator::DEFAULT_PASSWORD),
            'expires_at' => now()->addYear(),
        ]);

        $this->command?->info("Test certificate seeded for merchant [{$merchant->id}] at storage/app/{$relativePath}");
        $this->command?->info('Password: '.TestCertificateGenerator::DEFAULT_PASSWORD.' (dev only)');
    }
}
