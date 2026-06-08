<?php

namespace App\Services\Billing;

use App\Enums\LicenseStatus;
use App\Models\Merchant;
use App\Models\Vendor;

class LicenseEnforcement
{
    public function canVendorOperate(Vendor $vendor): bool
    {
        if ($vendor->status === 'suspended') {
            return false;
        }

        $hasActiveLicense = $vendor->licenses()
            ->active()
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->exists();

        if (! $hasActiveLicense) {
            return true;
        }

        $hasBlockingLicense = $vendor->licenses()
            ->where('status', LicenseStatus::Suspended->value)
            ->whereHas('licensePlan', fn ($query) => $query->whereIn('slug', [
                'vendor_one_time',
                'vendor_monthly_hosting',
            ]))
            ->exists();

        return ! $hasBlockingLicense;
    }

    public function canMerchantOperate(Merchant $merchant): bool
    {
        if (($merchant->status ?? 'active') === 'inactive') {
            return false;
        }

        $activeLicenses = $merchant->licenses()
            ->active()
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->exists();

        if (! $activeLicenses) {
            return true;
        }

        return ! $merchant->licenses()
            ->where('status', LicenseStatus::Suspended->value)
            ->exists();
    }

    /**
     * Hook point for Phase 4 middleware on POST /v1/transactions.
     */
    public function assertVendorCanTransact(Vendor $vendor): void
    {
        if (! $this->canVendorOperate($vendor)) {
            throw new \RuntimeException('Vendor license is not active.');
        }
    }

    public function assertMerchantCanTransact(Merchant $merchant): void
    {
        if (! $this->canMerchantOperate($merchant)) {
            throw new \RuntimeException('Merchant license is not active.');
        }
    }
}
