<?php

namespace App\Services\Billing;

use App\Enums\LicenseStatus;
use App\Models\Merchant;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Suspended/expired license gates for vendors and merchants.
 * Commercial prepaid wallet + postpaid daily caps live in CommercialBillingEnforcer.
 */
class LicenseEnforcement
{
    public function canVendorOperate(Vendor $vendor): bool
    {
        if ($vendor->status === 'suspended') {
            return false;
        }

        $hasBlockingLicense = $vendor->licenses()
            ->whereIn('status', [
                LicenseStatus::Suspended->value,
                LicenseStatus::Expired->value,
            ])
            ->whereHas('licensePlan', fn ($query) => $query->whereIn('slug', [
                'vendor_one_time',
                'vendor_monthly_hosting',
            ]))
            ->exists();

        if ($hasBlockingLicense) {
            return false;
        }

        if (! $vendor->licenses()->exists()) {
            return (bool) config('eis.sandbox_mode');
        }

        return $this->hasCurrentActiveLicense($vendor->licenses());
    }

    public function canMerchantOperate(Merchant $merchant): bool
    {
        if (($merchant->status ?? 'active') === 'inactive') {
            return false;
        }

        if ($merchant->licenses()
            ->whereIn('status', [
                LicenseStatus::Suspended->value,
                LicenseStatus::Expired->value,
            ])
            ->exists()
        ) {
            return false;
        }

        if (! $merchant->licenses()->exists()) {
            return (bool) config('eis.sandbox_mode');
        }

        return $this->hasCurrentActiveLicense($merchant->licenses());
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

    private function hasCurrentActiveLicense(HasMany $licenses): bool
    {
        return $licenses
            ->active()
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->exists();
    }
}
