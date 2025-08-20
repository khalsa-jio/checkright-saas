<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasTokenAbility('manage-users') || $user->isAdmin() || $user->isManager();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Users can always view their own profile
        if ($user->id === $model->id) {
            return true;
        }

        // Super admin can view anyone
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Admin can view anyone in their tenant
        if ($user->isAdmin()) {
            return true;
        }

        // Manager can view non-admin users
        if ($user->isManager() && ! $model->isAdmin()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || ($user->hasTokenAbility('manage-users') && ($user->isAdmin() || $user->isManager()));
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Users can update their own profile (limited fields)
        if ($user->id === $model->id) {
            return true;
        }

        // Super admin can update anyone
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Admin can update anyone in their tenant
        if ($user->isAdmin()) {
            return true;
        }

        // Manager can update operators only
        if ($user->isManager() && $model->isOperator()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Super admin can delete anyone (controller handles self-deletion prevention with custom message)
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Admin can delete anyone in their tenant (controller handles self-deletion prevention with custom message)
        if ($user->isAdmin()) {
            return true;
        }

        // Manager can delete operators only
        if ($user->isManager() && $model->isOperator()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $this->delete($user, $model);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        // Only super admin and admin can permanently delete users
        return ($user->isSuperAdmin() || $user->isAdmin()) && $user->id !== $model->id;
    }

    /**
     * Determine whether the user can invite other users.
     */
    public function invite(User $user, string $role): bool
    {
        // Super admin can invite anyone including other super admins
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Admin can invite anyone except super admin
        if ($user->isAdmin() && $role !== 'super-admin') {
            return true;
        }

        // Manager can only invite operators
        if ($user->isManager() && $role === 'operator') {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can manage roles.
     */
    public function manageRoles(User $user, User $model): bool
    {
        // Admin can manage any role
        if ($user->isAdmin()) {
            return true;
        }

        // Manager can only assign operator role
        if ($user->isManager() && $model->isOperator()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can force password reset.
     */
    public function forcePasswordReset(User $user, User $model): bool
    {
        // Users cannot force their own password reset
        if ($user->id === $model->id) {
            return false;
        }

        return $this->update($user, $model);
    }
}
