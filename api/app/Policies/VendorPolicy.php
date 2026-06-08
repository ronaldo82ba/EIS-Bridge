<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;
use App\Policies\Concerns\HandlesAdminRoles;

class VendorPolicy
{
    use HandlesAdminRoles;

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isSupport();
    }

    public function view(User $user, Vendor $vendor): bool
    {
        return $this->canRead($user) && $this->canManageVendor($user, $vendor->id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return $user->isSuperAdmin()
            || ($user->isVendorAdmin() && $user->vendor_id === $vendor->id);
    }

    public function rotateApiKey(User $user, Vendor $vendor): bool
    {
        return $this->update($user, $vendor);
    }

    public function delete(User $user, Vendor $vendor): bool
    {
        return $user->isSuperAdmin();
    }

    public function manageIpWhitelist(User $user, Vendor $vendor): bool
    {
        return $user->isSuperAdmin()
            || ($user->isVendorAdmin() && $user->vendor_id === $vendor->id);
    }
}
