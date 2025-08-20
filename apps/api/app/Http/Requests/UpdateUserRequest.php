<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $userId = $this->route('user');

        if (! $user || ! $userId) {
            return false;
        }

        // Get the target user - super admin can access any user
        if ($user->isSuperAdmin()) {
            $targetUser = User::withTrashed()->find($userId);
        } else {
            $tenantId = $user->tenant_id;
            $targetUser = User::withTrashed()
                ->where('tenant_id', $tenantId)
                ->find($userId);
        }

        if (! $targetUser) {
            return false;
        }

        // Users can update their own profile (limited fields)
        if ($user->id === $targetUser->id) {
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
        if ($user->isManager() && $targetUser->isOperator()) {
            return true;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $user = $this->user();
        $userId = $this->route('user');

        // Get the target user - super admin can access any user
        if ($user && $user->isSuperAdmin()) {
            $targetUser = User::withTrashed()->find($userId);
            $tenantId = $targetUser?->tenant_id;
        } else {
            $tenantId = $user?->tenant_id;
            $targetUser = $userId && $tenantId ? User::withTrashed()
                ->where('tenant_id', $tenantId)
                ->find($userId) : null;
        }
        $isOwnProfile = $user && $targetUser && $user->id === $targetUser->id;

        $rules = [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($targetUser?->id)->where('tenant_id', $tenantId),
            ],
        ];

        // Only super admin, admins and managers can update roles, and only for allowed users
        if (! $isOwnProfile && $user && ($user->isSuperAdmin() || $user->isAdmin() || $user->isManager())) {
            $allowedRoles = $this->getAllowedRoles($user, $targetUser);
            $rules['role'] = [
                'sometimes',
                'required',
                'string',
                Rule::in($allowedRoles),
            ];
        }

        // Password rules
        if ($this->has('password')) {
            $rules['password'] = ['required', 'string', Password::default()];
        }

        // Only users with proper permissions can modify these fields
        if (! $isOwnProfile && $user && ($user->isSuperAdmin() || $user->isAdmin() || $user->isManager())) {
            $rules['must_change_password'] = ['sometimes', 'boolean'];
        }

        return $rules;
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'This email address is already taken by another user.',
            'role.in' => 'You are not authorized to assign this role.',
        ];
    }

    /**
     * Get allowed roles for updating the target user.
     */
    private function getAllowedRoles($user, $targetUser): array
    {
        if (! $user || ! $targetUser) {
            return [];
        }

        // Super admin can assign all roles including super-admin
        if ($user->isSuperAdmin()) {
            return ['super-admin', 'admin', 'manager', 'operator'];
        }

        // Admin can assign all roles except super-admin
        if ($user->isAdmin()) {
            return ['admin', 'manager', 'operator'];
        }

        // Manager can only change operator roles
        if ($user->isManager() && $targetUser->isOperator()) {
            return ['operator']; // Managers can't promote operators
        }

        return [];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower($this->email),
            ]);
        }
    }
}
