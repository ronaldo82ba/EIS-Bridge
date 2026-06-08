<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use App\Models\MerchantCertificate;
use App\Services\Certificate\TestCertificateGenerator;
use App\Services\Signing\CertificateLoader;
use App\Services\Signing\JsonSigner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class TestEisSigning extends Command
{
    protected $signature = 'eis:test-signing {merchant_id : Merchant ID to test signing for}';

    protected $description = 'Load a merchant certificate (or generate a dev test cert) and sign sample BIR JSON';

    public function handle(
        TestCertificateGenerator $generator,
        CertificateLoader $certificateLoader,
        JsonSigner $signer,
    ): int {
        $merchant = Merchant::find($this->argument('merchant_id'));

        if (! $merchant) {
            $this->error('Merchant not found.');

            return self::FAILURE;
        }

        $certificate = $this->resolveCertificate($merchant, $generator);

        $samplePayload = [
            'document_type' => 'OR',
            'transaction_id' => 'TEST-'.now()->format('YmdHis'),
            'transaction_datetime' => now()->toIso8601String(),
            'currency' => 'PHP',
            'merchant' => [
                'code' => $merchant->merchant_code,
                'name' => $merchant->name,
                'tin' => $merchant->tin,
            ],
            'branch' => ['code' => 'TEST'],
            'device' => ['pos_device_id' => 'TEST'],
            'line_items' => [[
                'line_no' => 1,
                'sku' => 'TEST',
                'description' => 'Test item',
                'quantity' => 1,
                'unit_price' => 100,
                'gross_amount' => 100,
            ]],
            'totals' => [
                'gross_amount' => 100,
                'discount_amount' => 0,
                'vatable_sales' => 89.29,
                'vat_amount' => 10.71,
                'vat_exempt_sales' => 0,
                'zero_rated_sales' => 0,
                'service_charge' => 0,
                'net_amount' => 100,
            ],
            'payment' => ['method' => 'CASH', 'amount' => 100],
            'eis_fields' => [
                'submission_version' => '1.0',
                'source' => 'EIS_BRIDGE',
            ],
        ];

        try {
            $cert = $certificateLoader->loadForMerchant($merchant->id);
            $signed = $signer->sign($samplePayload, $cert['path'], $cert['password']);
        } catch (\Throwable $e) {
            $this->error('Signing failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $hash = $signed['signature_hash'] ?? null;

        if (empty($hash)) {
            $this->error('Signing failed: no signature hash produced.');

            return self::FAILURE;
        }

        $this->info('Signing succeeded.');
        $this->line('Certificate: '.$certificate->filename);
        $this->line('Algorithm: '.($signed['algorithm'] ?? 'unknown'));
        $this->line('Signature hash: '.$hash);
        $this->line('Certificate subject: '.($signed['certificate_subject'] ?? 'n/a'));

        return self::SUCCESS;
    }

    private function resolveCertificate(Merchant $merchant, TestCertificateGenerator $generator): MerchantCertificate
    {
        $existing = MerchantCertificate::where('merchant_id', $merchant->id)
            ->latest('id')
            ->first();

        if ($existing) {
            $this->line("Using existing certificate [{$existing->filename}].");

            return $existing;
        }

        if (! config('eis.sandbox_mode') && ! app()->environment('local', 'testing')) {
            throw new \RuntimeException(
                'No certificate found for merchant. Upload a production certificate via the admin API.'
            );
        }

        $this->warn('No certificate found — generating self-signed test certificate (dev only).');

        $disk = (string) config('security.certificate_disk', 'local');
        $filename = 'test-merchant-'.$merchant->id.'.pfx';
        $relativePath = "certificates/test/{$filename}";
        $absolutePath = Storage::disk($disk)->path($relativePath);

        $generator->generate($absolutePath, TestCertificateGenerator::DEFAULT_PASSWORD, $merchant->name.' Test');

        return MerchantCertificate::create([
            'merchant_id' => $merchant->id,
            'filename' => $filename,
            'file_path' => Crypt::encryptString($relativePath),
            'password_encrypted' => Crypt::encryptString(TestCertificateGenerator::DEFAULT_PASSWORD),
            'expires_at' => now()->addYear(),
        ]);
    }
}
