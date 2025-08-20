<?php

namespace App\Http\Requests;

use App\Models\DeviceRegistration;
use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'device_id' => [
                'required',
                'string',
                'min:10',
                'max:255',
                'regex:/^[a-zA-Z0-9_-]+$/', // Alphanumeric, underscore, and dash only
            ],
            'device_info' => 'sometimes|array',
            'device_info.platform' => 'sometimes|string|in:ios,android',
            'device_info.model' => 'sometimes|string|max:100',
            'device_info.version' => 'sometimes|string|max:50',
            'device_info.app_version' => 'sometimes|string|max:50',
            'device_info.screen_resolution' => 'sometimes|string|max:20',
            'device_info.timezone' => 'sometimes|string|max:50',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'device_id.required' => 'Device ID is required for registration',
            'device_id.min' => 'Device ID must be at least 10 characters long',
            'device_id.max' => 'Device ID cannot exceed 255 characters',
            'device_id.regex' => 'Device ID can only contain letters, numbers, underscores, and dashes',
            'device_info.platform.in' => 'Platform must be either ios or android',
            'device_info.model.max' => 'Device model cannot exceed 100 characters',
            'device_info.version.max' => 'Device version cannot exceed 50 characters',
            'device_info.app_version.max' => 'App version cannot exceed 50 characters',
            'device_info.screen_resolution.max' => 'Screen resolution cannot exceed 20 characters',
            'device_info.timezone.max' => 'Timezone cannot exceed 50 characters',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check if device is already registered for this user
            $deviceId = $this->input('device_id');
            $userId = auth()->id();

            if ($deviceId && $userId) {
                $exists = DeviceRegistration::where('user_id', $userId)
                    ->where('device_id', $deviceId)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('device_id', 'This device is already registered for your account');
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize device_id by removing any potentially harmful characters
        if ($this->has('device_id')) {
            $this->merge([
                'device_id' => preg_replace('/[^a-zA-Z0-9_-]/', '', $this->input('device_id')),
            ]);
        }

        // Ensure device_info is an array
        if ($this->has('device_info') && ! is_array($this->input('device_info'))) {
            $this->merge([
                'device_info' => [],
            ]);
        }
    }
}
