<?php

namespace App\Console\Commands;

use App\Jobs\SendCertificateAlertJob;
use App\Models\CertificateAlert;
use App\Models\MerchantCertificate;
use App\Services\Alerts\AlertEmitter;
use Illuminate\Console\Command;

class ScanCertificateExpiry extends Command
{
    protected $signature = 'certificates:scan-expiry';

    protected $description = 'Scan merchant certificates for expiry and create certificate alerts';

    public function handle(): int
    {
        $created = 0;

        MerchantCertificate::query()
            ->whereNotNull('expires_at')
            ->orderBy('id')
            ->each(function (MerchantCertificate $certificate) use (&$created) {
                $daysLeft = (int) now()->startOfDay()->diffInDays(
                    $certificate->expires_at->copy()->startOfDay(),
                    false
                );

                $level = $this->resolveLevel($daysLeft);

                if ($level === null) {
                    return;
                }

                if ($this->triggerAlert($certificate, $level)) {
                    $created++;
                }
            });

        $this->info("Certificate expiry scan complete. {$created} new alert(s) created.");

        return self::SUCCESS;
    }

    private function resolveLevel(int $daysLeft): ?string
    {
        if ($daysLeft <= 0) {
            return CertificateAlert::LEVEL_EXPIRED;
        }

        if ($daysLeft <= 7) {
            return CertificateAlert::LEVEL_EXPIRING_7;
        }

        if ($daysLeft <= 30) {
            return CertificateAlert::LEVEL_EXPIRING_30;
        }

        return null;
    }

    private function triggerAlert(MerchantCertificate $certificate, string $level): bool
    {
        $exists = CertificateAlert::query()
            ->where('certificate_id', $certificate->id)
            ->where('level', $level)
            ->exists();

        if ($exists) {
            return false;
        }

        $alert = CertificateAlert::create([
            'certificate_id' => $certificate->id,
            'level' => $level,
        ]);

        AlertEmitter::certificateExpiry(
            $certificate->loadMissing('merchant'),
            $level,
            ['expires_at' => $certificate->expires_at?->toIso8601String()]
        );

        SendCertificateAlertJob::dispatch($alert->id);

        return true;
    }
}
