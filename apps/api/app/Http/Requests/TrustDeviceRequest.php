<?php

namespace App\Http\Requests;

use App\Models\DeviceRegistration;
use Illuminate\Foundation\Http\FormRequest;

class TrustDeviceRequest extends FormRequest
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
            'verification_method' => 'sometimes|string|in:biometric,password,otp',
            'verification_data' => 'sometimes|string|max:500',
            'trust_duration' => 'sometimes|integer|min:3600|max:7776000', // 1 hour to 90 days
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'verification_method.in' => 'Verification method must be biometric, password, or otp',
            'verification_data.max' => 'Verification data cannot exceed 500 characters',
            'trust_duration.min' => 'Trust duration must be at least 1 hour',
            'trust_duration.max' => 'Trust duration cannot exceed 90 days',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Verify the device exists and belongs to the user
            $deviceId = $this->route('deviceId');
            $userId = auth()->id();

            if ($deviceId && $userId) {
                $device = DeviceRegistration::where('user_id', $userId)
                    ->where('device_id', $deviceId)
                    ->first();

                if (! $device) {
                    $validator->errors()->add('device', 'Device not found or does not belong to you');

                    return;
                }

                if ($device->is_trusted && ! $device->isTrustExpired()) {
                    $validator->errors()->add('device', 'Device is already trusted and trust has not expired');
                }
            }
        });
    }

    /**
     * Get validated data with defaults.
     */
    public function validated($key = null, $default = null): array|string|int|null
    {
        $validated = parent::validated();

        // Set default trust duration if not provided
        if (! isset($validated['trust_duration'])) {
            $validated['trust_duration'] = config('sanctum-mobile.device_binding.device_trust_duration', 2592000); // 30 days
        }

        return $key ? ($validated[$key] ?? $default) : $validated;
    }
}
