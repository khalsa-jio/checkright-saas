<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class TenantPolicy
{
    /**
     * Determine whether the user can view any tenants.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can view the tenant.
     */
    public function view(User $user, Company $company): bool
    {
        return $user->hasRole('super-admin') ||
               $user->tenant_id === $company->id;
    }

    /**
     * Determine whether the user can create tenants.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can update the tenant.
     */
    public function update(User $user, Company $company): bool
    {
        return $user->hasRole('super-admin') ||
               ($user->tenant_id === $company->id && $user->hasRole('admin'));
    }

    /**
     * Determine whether the user can delete the tenant.
     */
    public function delete(User $user, Company $company): bool
    {
        return $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can restore the tenant.
     */
    public function restore(User $user, Company $company): bool
    {
        return $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can permanently delete the tenant.
     */
    public function forceDelete(User $user, Company $company): bool
    {
        return $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can invite admins to the tenant.
     */
    public function inviteAdmin(User $user, Company $company): bool
    {
        return $user->hasRole('super-admin') ||
               ($user->tenant_id === $company->id && $user->hasRole('admin'));
    }
}
