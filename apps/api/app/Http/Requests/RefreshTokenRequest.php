<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Laravel\Sanctum\PersonalAccessToken;

class RefreshTokenRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Allow if user is authenticated or if refresh token is provided for token refresh
        return auth()->check() || $this->has('refresh_token');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'refresh_token' => [
                'required',
                'string',
                'min:40', // Minimum token length
                function ($attribute, $value, $fail) {
                    // Validate token format and existence
                    $token = PersonalAccessToken::findToken($value);

                    if (! $token) {
                        $fail('The provided refresh token is invalid.');

                        return;
                    }

                    // Check if token has refresh ability
                    if (! $token->can('refresh')) {
                        $fail('The provided token does not have refresh capability.');

                        return;
                    }

                    // Check if token is expired
                    if ($token->expires_at && $token->expires_at->isPast()) {
                        $fail('The refresh token has expired.');

                        return;
                    }

                    // Check if token name indicates it's a refresh token
                    if (! str_contains($token->name, 'mobile_refresh_')) {
                        $fail('The provided token is not a valid refresh token.');

                        return;
                    }
                },
            ],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'refresh_token.required' => 'A refresh token is required to obtain new access tokens.',
            'refresh_token.string' => 'The refresh token must be a valid string.',
            'refresh_token.min' => 'The refresh token appears to be invalid (too short).',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $refreshToken = $this->input('refresh_token');

            if ($refreshToken) {
                // Additional validation for rate limiting refresh attempts
                $tokenModel = PersonalAccessToken::findToken($refreshToken);

                if ($tokenModel) {
                    // Check if the token has been used recently (prevent rapid refresh)
                    $lastUsed = $tokenModel->last_used_at;
                    $minRefreshInterval = 60; // 1 minute minimum between refreshes

                    if ($lastUsed && $lastUsed->diffInSeconds(now()) < $minRefreshInterval) {
                        $validator->errors()->add(
                            'refresh_token',
                            'Token refresh attempted too frequently. Please wait before trying again.'
                        );
                    }
                }
            }
        });
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(
            'You must be authenticated or provide a valid refresh token.'
        );
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Clean up the refresh token if provided
        if ($this->has('refresh_token')) {
            $token = trim($this->input('refresh_token'));

            // Remove any potential Bearer prefix
            if (str_starts_with($token, 'Bearer ')) {
                $token = substr($token, 7);
            }

            $this->merge([
                'refresh_token' => $token,
            ]);
        }
    }
}
