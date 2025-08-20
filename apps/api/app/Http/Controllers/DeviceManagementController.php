<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterDeviceRequest;
use App\Http\Requests\TrustDeviceRequest;
use App\Services\Security\DeviceFingerprintService;
use App\Services\Security\SecurityLogger;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceManagementController extends Controller
{
    protected DeviceFingerprintService $deviceService;

    protected SecurityLogger $securityLogger;

    public function __construct(
        DeviceFingerprintService $deviceService,
        SecurityLogger $securityLogger
    ) {
        $this->deviceService = $deviceService;
        $this->securityLogger = $securityLogger;
    }

    /**
     * Register a new device for the authenticated user.
     */
    public function register(RegisterDeviceRequest $request): JsonResponse
    {
        $user = auth()->user();
        $deviceId = $request->input('device_id');
        $deviceInfo = $request->input('device_info', []);

        try {
            // Register the device
            $device = $this->deviceService->registerDevice($user->id, $deviceId, $deviceInfo);

            // Generate device secret for HMAC signing
            $deviceSecret = $this->deviceService->generateDeviceSecret($user->id, $deviceId);

            // Log successful registration
            $this->securityLogger->logSecurityEvent('device_registered', [
                'device_id' => $deviceId,
                'device_info' => $deviceInfo,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Device registered successfully',
                'device' => [
                    'id' => $device->id,
                    'device_id' => $device->device_id,
                    'is_trusted' => $device->is_trusted,
                    'device_info_string' => $device->device_info_string,
                    'registered_at' => $device->registered_at,
                ],
                'device_secret' => $deviceSecret, // Only returned once during registration
            ], 201);
        } catch (Exception $e) {
            $this->securityLogger->logSecurityEvent('device_registration_failed', [
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'error' => 'Device registration failed',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get all registered devices for the authenticated user.
     */
    public function index(): JsonResponse
    {
        $user = auth()->user();

        $devices = $user->deviceRegistrations()
            ->select(['id', 'device_id', 'device_info', 'is_trusted', 'registered_at', 'trusted_at', 'trusted_until', 'last_used_at'])
            ->orderBy('last_used_at', 'desc')
            ->get()
            ->map(function ($device) {
                return [
                    'id' => $device->id,
                    'device_id' => $device->device_id,
                    'device_info_string' => $device->device_info_string,
                    'is_trusted' => $device->is_trusted,
                    'is_trust_expired' => $device->isTrustExpired(),
                    'registered_at' => $device->registered_at,
                    'trusted_at' => $device->trusted_at,
                    'trusted_until' => $device->trusted_until,
                    'last_used_at' => $device->last_used_at,
                ];
            });

        return response()->json([
            'devices' => $devices,
            'total' => $devices->count(),
            'max_devices' => config('sanctum-mobile.device_binding.max_devices_per_user', 5),
        ]);
    }

    /**
     * Trust a registered device.
     */
    public function trust(TrustDeviceRequest $request, string $deviceId): JsonResponse
    {
        $user = auth()->user();

        try {
            $trusted = $this->deviceService->trustDevice($user->id, $deviceId);

            if (! $trusted) {
                return response()->json([
                    'error' => 'Device not found or already trusted',
                ], 404);
            }

            $this->securityLogger->logSecurityEvent('device_trusted', [
                'device_id' => $deviceId,
                'user_id' => $user->id,
                'trusted_until' => now()->addSeconds(config('sanctum-mobile.device_binding.device_trust_duration', 2592000)),
            ]);

            return response()->json([
                'message' => 'Device trusted successfully',
                'trusted_until' => now()->addSeconds(config('sanctum-mobile.device_binding.device_trust_duration', 2592000)),
            ]);
        } catch (Exception $e) {
            $this->securityLogger->logSecurityEvent('device_trust_failed', [
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'error' => 'Failed to trust device',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Revoke trust for a device.
     */
    public function revokeTrust(string $deviceId): JsonResponse
    {
        $user = auth()->user();

        try {
            $revoked = $this->deviceService->revokeTrust($user->id, $deviceId);

            if (! $revoked) {
                return response()->json([
                    'error' => 'Device not found',
                ], 404);
            }

            $this->securityLogger->logSecurityEvent('device_trust_revoked', [
                'device_id' => $deviceId,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Device trust revoked successfully',
            ]);
        } catch (Exception $e) {
            $this->securityLogger->logSecurityEvent('device_trust_revoke_failed', [
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'error' => 'Failed to revoke device trust',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Remove a device registration.
     */
    public function destroy(string $deviceId): JsonResponse
    {
        $user = auth()->user();

        try {
            $device = $user->deviceRegistrations()
                ->where('device_id', $deviceId)
                ->first();

            if (! $device) {
                return response()->json([
                    'error' => 'Device not found',
                ], 404);
            }

            $device->delete();

            $this->securityLogger->logSecurityEvent('device_removed', [
                'device_id' => $deviceId,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Device removed successfully',
            ]);
        } catch (Exception $e) {
            $this->securityLogger->logSecurityEvent('device_removal_failed', [
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'error' => 'Failed to remove device',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get device security status and recommendations.
     */
    public function securityStatus(Request $request): JsonResponse
    {
        $user = auth()->user();
        $deviceId = $request->header('X-Device-Id');

        if (! $deviceId) {
            return response()->json([
                'error' => 'Device ID header required',
            ], 400);
        }

        $device = $user->deviceRegistrations()
            ->where('device_id', $deviceId)
            ->first();

        if (! $device) {
            return response()->json([
                'error' => 'Device not found',
            ], 404);
        }

        $status = [
            'device_id' => $device->device_id,
            'is_registered' => true,
            'is_trusted' => $device->is_trusted,
            'is_trust_expired' => $device->isTrustExpired(),
            'trust_expires_at' => $device->trusted_until,
            'last_used_at' => $device->last_used_at,
            'security_score' => $this->calculateSecurityScore($device),
            'recommendations' => $this->getSecurityRecommendations($device),
        ];

        return response()->json($status);
    }

    /**
     * Calculate device security score.
     */
    protected function calculateSecurityScore($device): float
    {
        $score = 0.0;

        // Base score for registration
        $score += 0.3;

        // Trust status
        if ($device->is_trusted && ! $device->isTrustExpired()) {
            $score += 0.4;
        }

        // Recent usage
        if ($device->last_used_at && $device->last_used_at->diffInDays(now()) < 7) {
            $score += 0.2;
        }

        // Device info completeness
        $deviceInfo = $device->device_info ?? [];
        if (count($deviceInfo) >= 3) {
            $score += 0.1;
        }

        return min($score, 1.0);
    }

    /**
     * Get security recommendations for a device.
     */
    protected function getSecurityRecommendations($device): array
    {
        $recommendations = [];

        if (! $device->is_trusted) {
            $recommendations[] = [
                'type' => 'trust',
                'message' => 'Trust this device to enable full security features',
                'priority' => 'high',
            ];
        }

        if ($device->isTrustExpired()) {
            $recommendations[] = [
                'type' => 'renew_trust',
                'message' => 'Device trust has expired. Re-verify to continue secure access',
                'priority' => 'high',
            ];
        }

        if ($device->last_used_at && $device->last_used_at->diffInDays(now()) > 30) {
            $recommendations[] = [
                'type' => 'inactive',
                'message' => 'Consider removing this inactive device',
                'priority' => 'medium',
            ];
        }

        $deviceInfo = $device->device_info ?? [];
        if (count($deviceInfo) < 3) {
            $recommendations[] = [
                'type' => 'device_info',
                'message' => 'Update device information for better security tracking',
                'priority' => 'low',
            ];
        }

        return $recommendations;
    }
}
