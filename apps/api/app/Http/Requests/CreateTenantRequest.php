<?php

namespace App\Http\Requests;

use App\Exceptions\TenantValidationException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class CreateTenantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole('super-admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|min:2',
            'domain' => [
                'required',
                'string',
                'alpha_dash',
                'max:50',
                'min:3',
                'unique:tenants,domain',
                'regex:/^[a-z0-9-]+$/', // Only lowercase letters, numbers, and hyphens
            ],
            'admin_email' => 'required|email:rfc,dns|max:255',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Company name is required.',
            'name.min' => 'Company name must be at least 2 characters.',
            'domain.required' => 'Domain is required.',
            'domain.alpha_dash' => 'Domain may only contain letters, numbers, and hyphens.',
            'domain.unique' => 'This domain is already taken.',
            'domain.regex' => 'Domain must be lowercase and contain only letters, numbers, and hyphens.',
            'domain.min' => 'Domain must be at least 3 characters.',
            'admin_email.required' => 'Admin email is required.',
            'admin_email.email' => 'Please provide a valid email address.',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'admin_email' => 'admin email address',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @throws TenantValidationException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new TenantValidationException(
            'Tenant creation request validation failed',
            $validator->errors(),
            $this->all()
        );
    }
}
