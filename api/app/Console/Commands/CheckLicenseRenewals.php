<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\MerchantLicense;
use App\Models\VendorLicense;
use App\Services\Billing\BillingEventLogger;
use Illuminate\Console\Command;

class CheckLicenseRenewals extends Command
{
    protected $signature = 'licenses:check-renewals {--days=30 : Days ahead to check for expiring licenses}';

    protected $description = 'Check for expiring vendor and merchant licenses and create alerts and billing events';

    public function handle(): int
    {
        $withinDays = (int) $this->option('days');
        $alertsCreated = 0;
        $eventsLogged = 0;

        $alertsCreated += $this->checkMerchantLicenses($withinDays, $eventsLogged);
        $alertsCreated += $this->checkVendorLicenses($withinDays, $eventsLogged);

        $this->info("License renewal check complete. {$alertsCreated} alert(s) created, {$eventsLogged} billing event(s) logged.");

        return self::SUCCESS;
    }

    private function checkMerchantLicenses(int $withinDays, int &$eventsLogged): int
    {
        $created = 0;

        $licenses = MerchantLicense::query()
            ->with(['merchant:id,name,merchant_code', 'licensePlan:id,name,slug'])
            ->dueForRenewal($withinDays)
            ->get();

        foreach ($licenses as $license) {
            $daysLeft = now()->diffInDays($license->ends_at, false);

            if ($daysLeft < 0) {
                continue;
            }

            $severity = $daysLeft <= 7 ? Alert::SEVERITY_CRITICAL : Alert::SEVERITY_WARNING;

            if ($this->createAlertIfNew(
                Alert::TYPE_LICENSE_EXPIRING,
                MerchantLicense::class,
                $license->id,
                $severity,
                'Merchant license renewal due',
                sprintf(
                    'Merchant %s license (%s) expires in %d day(s) on %s.',
                    $license->merchant?->name ?? $license->merchant_id,
                    $license->licensePlan?->name ?? 'plan',
                    $daysLeft,
                    $license->ends_at->toDateString()
                ),
                [
                    'merchant_id' => $license->merchant_id,
                    'license_plan_id' => $license->license_plan_id,
                    'ends_at' => $license->ends_at->toIso8601String(),
                    'days_left' => $daysLeft,
                ]
            )) {
                $created++;
            }

            BillingEventLogger::log(
                'license_renewal_due',
                $license,
                $license->licensePlan,
                null,
                [
                    'days_left' => $daysLeft,
                    'ends_at' => $license->ends_at->toIso8601String(),
                ]
            );
            $eventsLogged++;
        }

        return $created;
    }

    private function checkVendorLicenses(int $withinDays, int &$eventsLogged): int
    {
        $created = 0;

        $licenses = VendorLicense::query()
            ->with(['vendor:id,name', 'licensePlan:id,name,slug'])
            ->dueForRenewal($withinDays)
            ->get();

        foreach ($licenses as $license) {
            $daysLeft = now()->diffInDays($license->ends_at, false);

            if ($daysLeft < 0) {
                continue;
            }

            $severity = $daysLeft <= 7 ? Alert::SEVERITY_CRITICAL : Alert::SEVERITY_WARNING;

            if ($this->createAlertIfNew(
                Alert::TYPE_LICENSE_EXPIRING,
                VendorLicense::class,
                $license->id,
                $severity,
                'Vendor license renewal due',
                sprintf(
                    'Vendor %s license (%s) expires in %d day(s) on %s.',
                    $license->vendor?->name ?? $license->vendor_id,
                    $license->licensePlan?->name ?? 'plan',
                    $daysLeft,
                    $license->ends_at->toDateString()
                ),
                [
                    'vendor_id' => $license->vendor_id,
                    'license_plan_id' => $license->license_plan_id,
                    'ends_at' => $license->ends_at->toIso8601String(),
                    'days_left' => $daysLeft,
                ]
            )) {
                $created++;
            }

            BillingEventLogger::log(
                'license_renewal_due',
                $license,
                $license->licensePlan,
                null,
                [
                    'days_left' => $daysLeft,
                    'ends_at' => $license->ends_at->toIso8601String(),
                ]
            );
            $eventsLogged++;
        }

        return $created;
    }

    private function createAlertIfNew(
        string $type,
        string $entityType,
        int $entityId,
        string $severity,
        string $title,
        string $message,
        array $metadata,
    ): bool {
        $since = now()->subHours((int) config('observability.alert_dedupe_hours', 24));

        $exists = Alert::query()
            ->where('type', $type)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->whereNull('resolved_at')
            ->where('created_at', '>=', $since)
            ->exists();

        if ($exists) {
            return false;
        }

        Alert::create([
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
        ]);

        return true;
    }
}
