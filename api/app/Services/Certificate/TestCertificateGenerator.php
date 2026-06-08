<?php

namespace App\Services\Certificate;

use RuntimeException;

class TestCertificateGenerator
{
    public const DEFAULT_PASSWORD = 'test-cert-password';

    public function generate(string $outputPath, string $password = self::DEFAULT_PASSWORD, string $commonName = 'EIS Bridge Test'): void
    {
        $directory = dirname($outputPath);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create certificate directory [{$directory}].");
        }

        $config = $this->opensslConfig();

        $dn = [
            'CN' => $commonName,
            'O' => 'EIS Bridge Development',
            'C' => 'PH',
        ];

        $privateKey = openssl_pkey_new($config);

        if ($privateKey === false) {
            throw new RuntimeException('Failed to generate test private key.');
        }

        $csrOptions = array_merge($config, ['digest_alg' => 'sha256']);
        $csr = openssl_csr_new($dn, $privateKey, $csrOptions);

        if ($csr === false) {
            throw new RuntimeException('Failed to generate test certificate signing request.');
        }

        $certificate = openssl_csr_sign($csr, null, $privateKey, 365, $csrOptions);

        if ($certificate === false) {
            throw new RuntimeException('Failed to sign test certificate.');
        }

        $exported = openssl_pkcs12_export_to_file($certificate, $outputPath, $privateKey, $password);

        if (! $exported) {
            throw new RuntimeException('Failed to export test PKCS#12 certificate.');
        }
    }

    private function opensslConfig(): array
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        foreach ($this->opensslConfigCandidates() as $path) {
            if (is_file($path)) {
                $config['config'] = $path;

                return $config;
            }
        }

        return $config;
    }

    private function opensslConfigCandidates(): array
    {
        $phpMinor = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;

        return array_filter([
            storage_path('app/openssl/openssl.cnf'),
            base_path('storage/app/openssl/openssl.cnf'),
            "C:/laragon/bin/php/php-{$phpMinor}-Win32-vs16-x64/extras/ssl/openssl.cnf",
            getenv('OPENSSL_CONF') ?: null,
        ]);
    }
}
