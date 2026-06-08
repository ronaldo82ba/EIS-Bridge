<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Support\AdminScope;

trait HandlesAdminRoles
{
    protected function canRead(User $user): bool
    {
        return $user->hasAdminAccess();
    }

    protected function canWrite(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isVendorAdmin();
    }

    protected function canManageVendor(User $user, ?int $vendorId): bool
    {
        return AdminScope::belongsToVendor($user, $vendorId);
    }
}
