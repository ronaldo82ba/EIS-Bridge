<?php

namespace App\Policies;

use App\Models\BillingInvoice;
use App\Models\LicensePlan;
use App\Models\Merchant;
use App\Models\User;
use App\Models\Vendor;

class BillingPolicy
{
    public function viewPlans(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'vendor_admin', 'support'], true);
    }

    public function managePlans(User $user): bool
    {
        return $user->role === 'super_admin';
    }

    public function viewVendorLicenses(User $user, Vendor $vendor): bool
    {
        return $this->canAccessVendor($user, $vendor);
    }

    public function manageVendorLicenses(User $user, Vendor $vendor): bool
    {
        if ($user->role === 'super_admin') {
            return true;
        }

        return $user->role === 'vendor_admin' && $user->vendor_id === $vendor->id;
    }

    public function viewMerchantLicenses(User $user, Merchant $merchant): bool
    {
        return $this->canAccessMerchant($user, $merchant);
    }

    public function manageMerchantLicenses(User $user, Merchant $merchant): bool
    {
        if ($user->role === 'super_admin') {
            return true;
        }

        if ($user->role === 'support') {
            return false;
        }

        return $user->role === 'vendor_admin' && $user->vendor_id === $merchant->vendor_id;
    }

    public function viewInvoices(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'vendor_admin', 'support'], true);
    }

    public function viewInvoice(User $user, BillingInvoice $invoice): bool
    {
        if (in_array($user->role, ['super_admin', 'support'], true)) {
            return true;
        }

        if ($user->role !== 'vendor_admin') {
            return false;
        }

        $billable = $invoice->billable;

        if ($billable instanceof Vendor) {
            return $user->vendor_id === $billable->id;
        }

        if ($billable instanceof Merchant) {
            return $user->vendor_id === $billable->vendor_id;
        }

        return false;
    }

    public function generateInvoices(User $user): bool
    {
        return $user->role === 'super_admin';
    }

    public function viewSummary(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'vendor_admin', 'support'], true);
    }

    private function canAccessVendor(User $user, Vendor $vendor): bool
    {
        if (in_array($user->role, ['super_admin', 'support'], true)) {
            return true;
        }

        return $user->role === 'vendor_admin' && $user->vendor_id === $vendor->id;
    }

    private function canAccessMerchant(User $user, Merchant $merchant): bool
    {
        if (in_array($user->role, ['super_admin', 'support'], true)) {
            return true;
        }

        return $user->role === 'vendor_admin' && $user->vendor_id === $merchant->vendor_id;
    }
}
