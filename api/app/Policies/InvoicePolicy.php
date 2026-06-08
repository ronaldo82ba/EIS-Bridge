<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Policies\Concerns\HandlesAdminRoles;
use App\Support\AdminScope;

class InvoicePolicy
{
    use HandlesAdminRoles;

    public function viewAny(User $user): bool
    {
        return $this->canRead($user);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if (! $this->canRead($user)) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isSupport()) {
            return true;
        }

        return AdminScope::scopeInvoices(Invoice::query()->where('id', $invoice->id), $user)->exists();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        if (! $this->canRead($user)) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isSupport()) {
            return true;
        }

        return AdminScope::scopeInvoices(Invoice::query()->where('id', $invoice->id), $user)->exists();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return false;
    }
}
