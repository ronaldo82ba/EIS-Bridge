<?php

namespace App\Services\Certificate;

use App\Models\Merchant;
use App\Models\MerchantCertificate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateStorageService
{
    private const ALLOWED_EXTENSIONS = ['pfx', 'p12', 'pem'];

    public function store(UploadedFile $file, Merchant $merchant, ?User $uploadedBy = null, ?string $password = null): MerchantCertificate
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException('Certificate must be a .pfx, .p12, or .pem file.');
        }

        $this->scanForVirus($file);

        $disk = (string) config('security.certificate_disk', 'local');
        $randomName = Str::uuid()->toString().'.'.$extension;
        $relativePath = "certificates/{$merchant->id}/{$randomName}";

        Storage::disk($disk)->putFileAs(
            "certificates/{$merchant->id}",
            $file,
            $randomName,
        );

        if ($password === null || $password === '') {
            throw new InvalidArgumentException('Certificate password is required.');
        }

        $absolutePath = Storage::disk($disk)->path($relativePath);

        try {
            $expiresAt = $this->validateStructureAndExtractExpiry($absolutePath, $password, $extension);
        } catch (InvalidArgumentException $e) {
            Storage::disk($disk)->delete($relativePath);

            throw $e;
        }

        return MerchantCertificate::create([
            'merchant_id' => $merchant->id,
            'filename' => $file->getClientOriginalName(),
            'file_path' => Crypt::encryptString($relativePath),
            'password_encrypted' => Crypt::encryptString($password),
            'expires_at' => $expiresAt,
            'parsed_at' => now(),
            'uploaded_by' => $uploadedBy?->id,
        ]);
    }

    private function validateStructureAndExtractExpiry(string $absolutePath, string $password, string $extension): ?Carbon
    {
        $contents = file_get_contents($absolutePath);

        if ($contents === false) {
            throw new InvalidArgumentException('Unable to read uploaded certificate file.');
        }

        if (in_array($extension, ['pfx', 'p12'], true)) {
            $certs = [];

            if (! openssl_pkcs12_read($contents, $certs, $password)) {
                throw new InvalidArgumentException('Invalid certificate file or password.');
            }

            if (empty($certs['pkey']) || empty($certs['cert'])) {
                throw new InvalidArgumentException('Certificate file is missing a private key or public certificate.');
            }

            $parsed = openssl_x509_parse($certs['cert']);
        } elseif ($extension === 'pem') {
            $privateKey = openssl_pkey_get_private($contents, $password);

            if ($privateKey === false) {
                throw new InvalidArgumentException('Invalid PEM certificate or password.');
            }

            $parsed = openssl_x509_parse($contents) ?: [];
        } else {
            throw new InvalidArgumentException('Unsupported certificate format.');
        }

        if (! isset($parsed['validTo_time_t'])) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $parsed['validTo_time_t']);
    }

    public function resolvePath(MerchantCertificate $certificate): string
    {
        try {
            $relativePath = Crypt::decryptString($certificate->file_path);
        } catch (DecryptException) {
            $relativePath = $certificate->file_path;
        }

        $disk = (string) config('security.certificate_disk', 'local');
        $absolutePath = Storage::disk($disk)->path($relativePath);

        if (! is_file($absolutePath)) {
            throw new RuntimeException("Certificate file missing at [{$relativePath}].");
        }

        return $absolutePath;
    }

    public function retrieve(MerchantCertificate $certificate): StreamedResponse
    {
        $absolutePath = $this->resolvePath($certificate);

        return response()->streamDownload(function () use ($absolutePath): void {
            $stream = fopen($absolutePath, 'rb');

            if ($stream === false) {
                throw new RuntimeException('Unable to open certificate file.');
            }

            fpassthru($stream);
            fclose($stream);
        }, $certificate->filename, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function delete(MerchantCertificate $certificate): void
    {
        try {
            $relativePath = Crypt::decryptString($certificate->file_path);
            $disk = (string) config('security.certificate_disk', 'local');
            Storage::disk($disk)->delete($relativePath);
        } catch (DecryptException) {
            // File path metadata is unreadable; skip storage cleanup.
        }
    }

    protected function scanForVirus(UploadedFile $file): void
    {
        // Stub: integrate ClamAV or a cloud scanner in production.
    }
}
