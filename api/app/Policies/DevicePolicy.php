<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;
use App\Policies\Concerns\HandlesAdminRoles;

class DevicePolicy
{
    use HandlesAdminRoles;

    public function viewAny(User $user): bool
    {
        return $this->canRead($user);
    }

    public function view(User $user, Device $device): bool
    {
        return $this->canRead($user)
            && $this->canManageVendor($user, $device->branch?->merchant?->vendor_id);
    }

    public function create(User $user): bool
    {
        return $this->canWrite($user);
    }

    public function update(User $user, Device $device): bool
    {
        return $this->canWrite($user)
            && $this->canManageVendor($user, $device->branch?->merchant?->vendor_id);
    }

    public function delete(User $user, Device $device): bool
    {
        return $user->isSuperAdmin();
    }
}
