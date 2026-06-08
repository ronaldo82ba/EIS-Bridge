<?php

namespace App\Services\Signing;

use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\MerchantCertificate;
use App\Services\Certificate\CertificateStorageService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class CertificateLoader
{
    public function __construct(
        private CertificateStorageService $certificateStorage,
    ) {}

    /**
     * @return array{path: string, password: string}
     */
    public function load(MerchantCertificate $certificate): array
    {
        if ($certificate->expires_at && $certificate->expires_at->isPast()) {
            throw new RuntimeException('Certificate has expired.');
        }

        $absolutePath = $this->certificateStorage->resolvePath($certificate);

        try {
            $password = Crypt::decryptString($certificate->password_encrypted);
        } catch (DecryptException) {
            throw new RuntimeException('Unable to decrypt certificate password.');
        }

        return [
            'path' => $absolutePath,
            'password' => $password,
        ];
    }

    /**
     * @return array{path: string, password: string}
     */
    public function loadForMerchant(int $merchantId): array
    {
        $certificate = MerchantCertificate::where('merchant_id', $merchantId)
            ->latest('id')
            ->first();

        if (! $certificate) {
            throw new RuntimeException("No certificate configured for merchant id [{$merchantId}].");
        }

        return $this->load($certificate);
    }

    public function loadForInvoice(Invoice $invoice): LoadedCertificate
    {
        $merchant = Merchant::where('merchant_code', $invoice->merchant_code)->first();

        if (! $merchant) {
            throw new RuntimeException("Merchant not found for code [{$invoice->merchant_code}].");
        }

        $cert = $this->loadForMerchant($merchant->id);
        $contents = file_get_contents($cert['path']);
        $extension = strtolower(pathinfo($cert['path'], PATHINFO_EXTENSION));

        if (in_array($extension, ['pfx', 'p12'], true)) {
            $certs = [];
            if (! openssl_pkcs12_read($contents, $certs, $cert['password'])) {
                throw new RuntimeException('Unable to read PKCS#12 certificate.');
            }

            return new LoadedCertificate(
                privateKey: $certs['pkey'],
                certificate: $certs['cert'],
                caCertificates: $certs['extracerts'] ?? [],
            );
        }

        if ($extension === 'pem') {
            $privateKey = openssl_pkey_get_private($contents, $cert['password']);

            if ($privateKey === false) {
                throw new RuntimeException('Unable to load PEM private key.');
            }

            return new LoadedCertificate(
                privateKey: $privateKey,
                certificate: $contents,
                caCertificates: [],
            );
        }

        throw new RuntimeException("Unsupported certificate format [{$extension}].");
    }
}
