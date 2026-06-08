<?php

namespace App\Policies;

use App\Models\MerchantCertificate;
use App\Models\User;
use App\Policies\Concerns\HandlesAdminRoles;

class CertificatePolicy
{
    use HandlesAdminRoles;

    public function viewAny(User $user): bool
    {
        return $this->canRead($user);
    }

    public function view(User $user, MerchantCertificate $certificate): bool
    {
        return $this->canRead($user)
            && $this->canManageVendor($user, $certificate->merchant?->vendor_id);
    }

    public function create(User $user): bool
    {
        return $this->canWrite($user);
    }

    public function update(User $user, MerchantCertificate $certificate): bool
    {
        return $this->canWrite($user)
            && $this->canManageVendor($user, $certificate->merchant?->vendor_id);
    }

    public function delete(User $user, MerchantCertificate $certificate): bool
    {
        return $this->canWrite($user)
            && $this->canManageVendor($user, $certificate->merchant?->vendor_id);
    }
}
