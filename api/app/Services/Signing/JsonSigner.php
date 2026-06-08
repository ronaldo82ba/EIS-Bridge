<?php

namespace App\Services\Signing;

use App\Models\Invoice;
use App\Models\Merchant;
use RuntimeException;

class JsonSigner
{
    public function __construct(
        private CertificateLoader $certificateLoader,
    ) {}

    /**
     * Sign a BIR JSON payload with an on-disk PKCS#12/PEM certificate.
     *
     * @return array{payload: array, signature: string, signature_hash: string, algorithm: string, signed_at: string, certificate_subject: ?string}
     */
    public function sign(array $payload, string $pfxPath, string $password): array
    {
        $loaded = $this->loadPrivateKeyFromFile($pfxPath, $password);
        $canonical = $this->canonicalize($payload);

        $signature = '';
        $signed = openssl_sign($canonical, $signature, $loaded['privateKey'], OPENSSL_ALGO_SHA256);

        if (! $signed) {
            throw new RuntimeException('Failed to sign BIR JSON payload.');
        }

        $signatureBase64 = base64_encode($signature);
        $signatureHash = hash('sha256', $signatureBase64);

        return [
            'payload' => $payload,
            'signature' => $signatureBase64,
            'signature_hash' => $signatureHash,
            'algorithm' => 'RS256',
            'signed_at' => now()->toIso8601String(),
            'certificate_subject' => $this->extractSubject($loaded['certificate']),
        ];
    }

    /**
     * Resolve merchant certificate and sign, with sandbox fallback when configured.
     */
    public function signForInvoice(array $birJson, Invoice $invoice): array
    {
        $merchant = Merchant::where('merchant_code', $invoice->merchant_code)->first();

        if (! $merchant) {
            if (config('eis.sandbox_mode')) {
                return $this->sandboxSign($birJson);
            }

            throw new RuntimeException("Merchant not found for code [{$invoice->merchant_code}].");
        }

        try {
            $cert = $this->certificateLoader->loadForMerchant($merchant->id);

            return $this->sign($birJson, $cert['path'], $cert['password']);
        } catch (RuntimeException $e) {
            if (config('eis.sandbox_mode')) {
                return $this->sandboxSign($birJson);
            }

            throw $e;
        }
    }

    public function sandboxSign(array $birJson): array
    {
        $canonical = $this->canonicalize($birJson);
        $signatureHash = hash('sha256', $canonical);

        return [
            'payload' => $birJson,
            'signature' => base64_encode('SANDBOX_SIGNATURE_'.$signatureHash),
            'signature_hash' => $signatureHash,
            'algorithm' => 'SANDBOX',
            'signed_at' => now()->toIso8601String(),
            'certificate_subject' => 'SANDBOX',
        ];
    }

    /**
     * @return array{privateKey: mixed, certificate: string}
     */
    private function loadPrivateKeyFromFile(string $path, string $password): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Certificate file not found at [{$path}].");
        }

        $contents = file_get_contents($path);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['pfx', 'p12'], true)) {
            $certs = [];
            if (! openssl_pkcs12_read($contents, $certs, $password)) {
                throw new RuntimeException('Unable to read PKCS#12 certificate.');
            }

            return [
                'privateKey' => $certs['pkey'],
                'certificate' => $certs['cert'],
            ];
        }

        if ($extension === 'pem') {
            $privateKey = openssl_pkey_get_private($contents, $password);

            if ($privateKey === false) {
                throw new RuntimeException('Unable to load PEM private key.');
            }

            return [
                'privateKey' => $privateKey,
                'certificate' => $contents,
            ];
        }

        throw new RuntimeException("Unsupported certificate format [{$extension}].");
    }

    private function canonicalize(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function extractSubject(string $certificate): ?string
    {
        $parsed = openssl_x509_parse($certificate);

        return $parsed['subject']['CN'] ?? null;
    }
}
