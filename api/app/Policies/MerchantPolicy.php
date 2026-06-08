<?php

namespace App\Policies;

use App\Models\Merchant;
use App\Models\User;
use App\Policies\Concerns\HandlesAdminRoles;

class MerchantPolicy
{
    use HandlesAdminRoles;

    public function viewAny(User $user): bool
    {
        return $this->canRead($user);
    }

    public function view(User $user, Merchant $merchant): bool
    {
        return $this->canRead($user) && $this->canManageVendor($user, $merchant->vendor_id);
    }

    public function create(User $user): bool
    {
        return $this->canWrite($user);
    }

    public function update(User $user, Merchant $merchant): bool
    {
        return $this->canWrite($user) && $this->canManageVendor($user, $merchant->vendor_id);
    }

    public function delete(User $user, Merchant $merchant): bool
    {
        return $user->isSuperAdmin();
    }
}
