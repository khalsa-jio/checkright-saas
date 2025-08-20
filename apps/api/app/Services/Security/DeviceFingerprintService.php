<?php

namespace App\Services\Security;

use App\Models\DeviceRegistration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DeviceFingerprintService
{
    /**
     * Check if a device is registered for a user.
     */
    public function isDeviceRegistered(int $userId, string $deviceId): bool
    {
        $cacheKey = "device_registered:{$userId}:{$deviceId}";

        return Cache::remember($cacheKey, 3600, function () use ($userId, $deviceId) {
            return DeviceRegistration::where('user_id', $userId)
                ->where('device_id', $deviceId)
                ->exists();
        });
    }

    /**
     * Check if a device is trusted for a user.
     */
    public function isDeviceTrusted(int $userId, string $deviceId): bool
    {
        $cacheKey = "device_trusted:{$userId}:{$deviceId}";

        return Cache::remember($cacheKey, 1800, function () use ($userId, $deviceId) {
            return DeviceRegistration::where('user_id', $userId)
                ->where('device_id', $deviceId)
                ->where('is_trusted', true)
                ->where('trusted_until', '>', now())
                ->exists();
        });
    }

    /**
     * Register a new device for a user.
     */
    public function registerDevice(int $userId, string $deviceId, array $deviceInfo): DeviceRegistration
    {
        // Check if device is already registered
        $existingDevice = DeviceRegistration::where('user_id', $userId)
            ->where('device_id', $deviceId)
            ->first();

        if ($existingDevice) {
            // Update existing device info
            $existingDevice->update([
                'device_info' => $deviceInfo,
                'last_used_at' => now(),
            ]);

            // Clear cache
            Cache::forget("device_registered:{$userId}:{$deviceId}");
            Cache::forget("device_trusted:{$userId}:{$deviceId}");

            return $existingDevice;
        }

        // Check device limit
        $deviceCount = DeviceRegistration::where('user_id', $userId)->count();
        $maxDevices = config('sanctum-mobile.device_binding.max_devices_per_user', 5);

        if ($deviceCount >= $maxDevices) {
            // Remove oldest device
            $oldestDevice = DeviceRegistration::where('user_id', $userId)
                ->orderBy('last_used_at', 'asc')
                ->first();

            if ($oldestDevice) {
                $oldestDevice->delete();
                Cache::forget("device_registered:{$userId}:{$oldestDevice->device_id}");
                Cache::forget("device_trusted:{$userId}:{$oldestDevice->device_id}");
            }
        }

        // Create new device registration
        $device = DeviceRegistration::create([
            'user_id' => $userId,
            'device_id' => $deviceId,
            'device_info' => $deviceInfo,
            'is_trusted' => false,
            'registered_at' => now(),
            'last_used_at' => now(),
        ]);

        Log::info('New device registered', [
            'user_id' => $userId,
            'device_id' => $deviceId,
            'device_info' => $deviceInfo,
        ]);

        return $device;
    }

    /**
     * Trust a device for a user.
     */
    public function trustDevice(int $userId, string $deviceId): bool
    {
        $trustDuration = config('sanctum-mobile.device_binding.device_trust_duration', 2592000); // 30 days

        $updated = DeviceRegistration::where('user_id', $userId)
            ->where('device_id', $deviceId)
            ->update([
                'is_trusted' => true,
                'trusted_at' => now(),
                'trusted_until' => now()->addSeconds($trustDuration),
            ]);

        if ($updated) {
            // Clear cache to force refresh
            Cache::forget("device_trusted:{$userId}:{$deviceId}");

            Log::info('Device trusted', [
                'user_id' => $userId,
                'device_id' => $deviceId,
                'trusted_until' => now()->addSeconds($trustDuration),
            ]);
        }

        return $updated > 0;
    }

    /**
     * Revoke trust for a device.
     */
    public function revokeTrust(int $userId, string $deviceId): bool
    {
        $updated = DeviceRegistration::where('user_id', $userId)
            ->where('device_id', $deviceId)
            ->update([
                'is_trusted' => false,
                'trusted_at' => null,
                'trusted_until' => null,
            ]);

        if ($updated) {
            Cache::forget("device_trusted:{$userId}:{$deviceId}");

            Log::info('Device trust revoked', [
                'user_id' => $userId,
                'device_id' => $deviceId,
            ]);
        }

        return $updated > 0;
    }

    /**
     * Get device secret for HMAC signing.
     */
    public function getDeviceSecret(string $deviceId): ?string
    {
        $cacheKey = "device_secret:{$deviceId}";

        return Cache::remember($cacheKey, 7200, function () use ($deviceId) {
            $device = DeviceRegistration::where('device_id', $deviceId)->first();

            return $device?->device_secret;
        });
    }

    /**
     * Generate and store a device secret.
     */
    public function generateDeviceSecret(int $userId, string $deviceId): string
    {
        $secret = bin2hex(random_bytes(32));

        DeviceRegistration::where('user_id', $userId)
            ->where('device_id', $deviceId)
            ->update(['device_secret' => $secret]);

        // Clear cache
        Cache::forget("device_secret:{$deviceId}");

        return $secret;
    }

    /**
     * Clean up expired device registrations.
     */
    public function cleanupExpiredDevices(): int
    {
        $expiredDevices = DeviceRegistration::where('trusted_until', '<', now())
            ->where('is_trusted', true)
            ->get();

        $count = 0;
        foreach ($expiredDevices as $device) {
            $device->update([
                'is_trusted' => false,
                'trusted_at' => null,
                'trusted_until' => null,
            ]);

            Cache::forget("device_trusted:{$device->user_id}:{$device->device_id}");
            $count++;
        }

        if ($count > 0) {
            Log::info("Cleaned up {$count} expired device trusts");
        }

        return $count;
    }
}
