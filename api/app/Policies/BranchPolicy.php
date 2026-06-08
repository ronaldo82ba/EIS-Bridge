<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use App\Policies\Concerns\HandlesAdminRoles;

class BranchPolicy
{
    use HandlesAdminRoles;

    public function viewAny(User $user): bool
    {
        return $this->canRead($user);
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->canRead($user)
            && $this->canManageVendor($user, $branch->merchant?->vendor_id);
    }

    public function create(User $user): bool
    {
        return $this->canWrite($user);
    }

    public function update(User $user, Branch $branch): bool
    {
        return $this->canWrite($user)
            && $this->canManageVendor($user, $branch->merchant?->vendor_id);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->isSuperAdmin();
    }
}
