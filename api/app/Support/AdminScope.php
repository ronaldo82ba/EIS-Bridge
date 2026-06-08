<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AdminScope
{
    public static function vendorId(User $user): ?int
    {
        return $user->isVendorAdmin() ? $user->vendor_id : null;
    }

    public static function scopeVendors(Builder $query, User $user): Builder
    {
        if ($vendorId = self::vendorId($user)) {
            $query->where('id', $vendorId);
        }

        return $query;
    }

    public static function scopeMerchants(Builder $query, User $user): Builder
    {
        if ($vendorId = self::vendorId($user)) {
            $query->where('vendor_id', $vendorId);
        }

        return $query;
    }

    public static function scopeBranches(Builder $query, User $user): Builder
    {
        if ($vendorId = self::vendorId($user)) {
            $query->whereHas('merchant', fn (Builder $q) => $q->where('vendor_id', $vendorId));
        }

        return $query;
    }

    public static function scopeDevices(Builder $query, User $user): Builder
    {
        if ($vendorId = self::vendorId($user)) {
            $query->whereHas('branch.merchant', fn (Builder $q) => $q->where('vendor_id', $vendorId));
        }

        return $query;
    }

    public static function scopeInvoices(Builder $query, User $user): Builder
    {
        if ($vendorId = self::vendorId($user)) {
            $query->whereIn('merchant_code', function ($sub) use ($vendorId) {
                $sub->select('merchant_code')
                    ->from('merchants')
                    ->where('vendor_id', $vendorId);
            });
        }

        return $query;
    }

    public static function scopeMerchantCertificates(Builder $query, User $user): Builder
    {
        if ($vendorId = self::vendorId($user)) {
            $query->whereHas('merchant', fn (Builder $q) => $q->where('vendor_id', $vendorId));
        }

        return $query;
    }

    /** @deprecated Use scopeMerchantCertificates() */
    public static function scopeCertificates(Builder $query, User $user): Builder
    {
        return self::scopeMerchantCertificates($query, $user);
    }

    public static function belongsToVendor(User $user, ?int $vendorId): bool
    {
        if ($user->isSuperAdmin() || $user->isSupport()) {
            return true;
        }

        return $user->isVendorAdmin() && $user->vendor_id === $vendorId;
    }

    public static function merchantBelongsToUser(User $user, int $merchantVendorId): bool
    {
        return self::belongsToVendor($user, $merchantVendorId);
    }
}
