<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        // Check if user can invite with the specified role
        $role = $this->input('role', 'operator');

        // Super admin can invite anyone including other super admins
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Admin can invite anyone except super admin
        if ($user->isAdmin()) {
            return true;
        }

        // Manager can only invite operators
        if ($user->isManager() && $role === 'operator') {
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
        $allowedRoles = $this->getAllowedRoles($user);

        // Determine tenant for validation based on user type and role being invited
        $role = $this->input('role', 'operator');
        if ($user && $user->isSuperAdmin() && $role === 'super-admin') {
            $tenantId = null; // Super admin invitations are tenant-agnostic
        } else {
            $tenantId = $user?->tenant_id;
        }

        $rules = [
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('invitations')
                    ->where(function ($query) use ($tenantId) {
                        if ($tenantId === null) {
                            return $query->whereNull('tenant_id')
                                ->whereNull('accepted_at')
                                ->where('expires_at', '>', now());
                        } else {
                            return $query->where('tenant_id', $tenantId)
                                ->whereNull('accepted_at')
                                ->where('expires_at', '>', now());
                        }
                    })
                    ->withoutTrashed(),
                Rule::unique('users')
                    ->where(function ($query) use ($tenantId) {
                        if ($tenantId === null) {
                            return $query->whereNull('tenant_id');
                        } else {
                            return $query->where('tenant_id', $tenantId);
                        }
                    })
                    ->withoutTrashed(),
            ],
            'role' => [
                'required',
                'string',
                Rule::in($allowedRoles),
            ],
        ];

        // For super admin, allow tenant_id selection for non-super-admin roles
        if ($user && $user->isSuperAdmin() && $role !== 'super-admin') {
            $rules['tenant_id'] = [
                'required',
                'exists:tenants,id',
            ];
        }

        return $rules;
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'A pending invitation or existing user already exists for this email address.',
            'role.in' => 'You are not authorized to invite users with this role.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'email' => 'email address',
            'role' => 'user role',
        ];
    }

    /**
     * Get allowed roles for the current user.
     */
    private function getAllowedRoles($user): array
    {
        if (! $user) {
            return [];
        }

        // Super admin can invite all roles including super admin
        if ($user->isSuperAdmin()) {
            return ['super-admin', 'admin', 'manager', 'operator'];
        }

        // Admin can invite all roles except super admin
        if ($user->isAdmin()) {
            return ['admin', 'manager', 'operator'];
        }

        // Manager can only invite operators
        if ($user->isManager()) {
            return ['operator'];
        }

        return [];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower($this->email),
        ]);
    }
}
