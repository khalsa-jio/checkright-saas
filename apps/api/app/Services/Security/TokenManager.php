<?php

namespace App\Services\Security;

use App\Models\MobileTokenRegistry;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Laravel\Sanctum\PersonalAccessToken;

class TokenManager
{
    /**
     * Generate mobile token pair for a user and device.
     */
    public function generateMobileTokens(User $user, string $deviceId): array
    {
        // Get mobile token configuration
        $accessLifetime = config('sanctum-mobile.mobile_tokens.access.lifetime', 900); // 15 minutes
        $refreshLifetime = config('sanctum-mobile.mobile_tokens.refresh.lifetime', 86400); // 24 hours

        // Create access token with specific abilities
        $accessToken = $user->createToken(
            name: "mobile_access_{$deviceId}_" . time(),
            abilities: config('sanctum-mobile.mobile_tokens.access.abilities', ['*']),
            expiresAt: now()->addSeconds($accessLifetime)
        );

        // Create refresh token with limited abilities
        $refreshToken = $user->createToken(
            name: "mobile_refresh_{$deviceId}_" . time(),
            abilities: config('sanctum-mobile.mobile_tokens.refresh.abilities', ['refresh']),
            expiresAt: now()->addSeconds($refreshLifetime)
        );

        // Track tokens in registry
        $this->trackTokenPair($user->id, $deviceId, $accessToken->accessToken, $refreshToken->accessToken);

        return [
            'access_token' => $accessToken->plainTextToken,
            'refresh_token' => $refreshToken->plainTextToken,
            'access_expires_in' => $accessLifetime,
            'refresh_expires_in' => $refreshLifetime,
            'token_type' => 'Bearer',
            'expires_at' => now()->addSeconds($accessLifetime)->toISOString(),
            'refresh_expires_at' => now()->addSeconds($refreshLifetime)->toISOString(),
        ];
    }

    /**
     * Rotate tokens using a refresh token.
     */
    public function rotateTokens(string $refreshToken): array
    {
        // Find the refresh token
        $tokenModel = PersonalAccessToken::findToken($refreshToken);

        if (! $tokenModel || ! $tokenModel->can('refresh')) {
            throw new InvalidArgumentException('Invalid refresh token');
        }

        // Check if token is expired
        if ($tokenModel->expires_at && $tokenModel->expires_at->isPast()) {
            throw new InvalidArgumentException('Refresh token expired');
        }

        $user = $tokenModel->tokenable;

        // Extract device ID from token name
        $deviceId = $this->extractDeviceIdFromTokenName($tokenModel->name);

        if (! $deviceId) {
            throw new InvalidArgumentException('Could not determine device ID from token');
        }

        // Get the token registry entry
        $registry = MobileTokenRegistry::where('refresh_token_id', $tokenModel->id)->first();

        if (! $registry) {
            throw new InvalidArgumentException('Token registry entry not found');
        }

        // Revoke old tokens
        $this->revokeTokenPair($registry);

        // Generate new token pair
        $newTokens = $this->generateMobileTokens($user, $deviceId);

        Log::info('Mobile tokens rotated', [
            'user_id' => $user->id,
            'device_id' => $deviceId,
            'old_access_token_id' => $registry->access_token_id,
            'old_refresh_token_id' => $registry->refresh_token_id,
        ]);

        return $newTokens;
    }

    /**
     * Check if a token needs rotation based on threshold.
     */
    public function shouldRotateToken(PersonalAccessToken $token): bool
    {
        if (! $token->expires_at) {
            return false;
        }

        $rotationThreshold = config('sanctum-mobile.token_rotation.threshold', 0.8);
        $totalLifetime = $token->created_at->diffInSeconds($token->expires_at);
        $timeUsed = $token->created_at->diffInSeconds(now());

        $usageRatio = $totalLifetime > 0 ? $timeUsed / $totalLifetime : 1;

        return $usageRatio >= $rotationThreshold;
    }

    /**
     * Auto-rotate tokens if they meet the rotation criteria.
     */
    public function autoRotateIfNeeded(PersonalAccessToken $accessToken): ?array
    {
        if (! $this->shouldRotateToken($accessToken)) {
            return null;
        }

        // Find the associated refresh token
        $registry = MobileTokenRegistry::where('access_token_id', $accessToken->id)->first();

        if (! $registry || ! $registry->refreshToken) {
            return null;
        }

        try {
            $refreshTokenModel = $registry->refreshToken;
            $refreshPlainText = $this->getPlainTextToken($refreshTokenModel);

            if ($refreshPlainText) {
                return $this->rotateTokens($refreshPlainText);
            }
        } catch (Exception $e) {
            Log::warning('Auto-rotation failed', [
                'access_token_id' => $accessToken->id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Revoke all tokens for a user on a specific device.
     */
    public function revokeDeviceTokens(int $userId, string $deviceId): int
    {
        $revokedCount = 0;

        // Find all token registry entries for this user/device
        $registries = MobileTokenRegistry::where('user_id', $userId)
            ->where('device_id', $deviceId)
            ->get();

        foreach ($registries as $registry) {
            $this->revokeTokenPair($registry);
            $revokedCount++;
        }

        Log::info('Device tokens revoked', [
            'user_id' => $userId,
            'device_id' => $deviceId,
            'revoked_count' => $revokedCount,
        ]);

        return $revokedCount;
    }

    /**
     * Revoke all tokens for a user.
     */
    public function revokeAllUserTokens(int $userId): int
    {
        $revokedCount = 0;

        // Find all token registry entries for this user
        $registries = MobileTokenRegistry::where('user_id', $userId)->get();

        foreach ($registries as $registry) {
            $this->revokeTokenPair($registry);
            $revokedCount++;
        }

        Log::info('All user tokens revoked', [
            'user_id' => $userId,
            'revoked_count' => $revokedCount,
        ]);

        return $revokedCount;
    }

    /**
     * Get token information for a user's device.
     */
    public function getTokenInfo(int $userId, string $deviceId): ?array
    {
        $registry = MobileTokenRegistry::where('user_id', $userId)
            ->where('device_id', $deviceId)
            ->with(['accessToken', 'refreshToken'])
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $registry) {
            return null;
        }

        $accessToken = $registry->accessToken;
        $refreshToken = $registry->refreshToken;

        return [
            'access_token_id' => $accessToken?->id,
            'access_expires_at' => $accessToken?->expires_at,
            'access_is_expired' => $accessToken?->expires_at?->isPast() ?? true,
            'refresh_token_id' => $refreshToken?->id,
            'refresh_expires_at' => $refreshToken?->expires_at,
            'refresh_is_expired' => $refreshToken?->expires_at?->isPast() ?? true,
            'created_at' => $registry->created_at,
            'should_rotate' => $accessToken ? $this->shouldRotateToken($accessToken) : false,
        ];
    }

    /**
     * Clean up expired tokens and registry entries.
     */
    public function cleanupExpiredTokens(): int
    {
        $cleanedCount = 0;

        // Find expired token registry entries
        $expiredRegistries = MobileTokenRegistry::whereHas('accessToken', function ($query) {
            $query->where('expires_at', '<', now());
        })->orWhereHas('refreshToken', function ($query) {
            $query->where('expires_at', '<', now());
        })->get();

        foreach ($expiredRegistries as $registry) {
            // Only clean up if both tokens are expired
            $accessExpired = ! $registry->accessToken || $registry->accessToken->expires_at?->isPast() ?? true;
            $refreshExpired = ! $registry->refreshToken || $registry->refreshToken->expires_at?->isPast() ?? true;

            if ($accessExpired && $refreshExpired) {
                $this->revokeTokenPair($registry);
                $cleanedCount++;
            }
        }

        if ($cleanedCount > 0) {
            Log::info("Cleaned up {$cleanedCount} expired token pairs");
        }

        return $cleanedCount;
    }

    /**
     * Track a token pair in the registry.
     */
    protected function trackTokenPair(int $userId, string $deviceId, PersonalAccessToken $accessToken, PersonalAccessToken $refreshToken): MobileTokenRegistry
    {
        return MobileTokenRegistry::create([
            'user_id' => $userId,
            'device_id' => $deviceId,
            'access_token_id' => $accessToken->id,
            'refresh_token_id' => $refreshToken->id,
            'expires_at' => $refreshToken->expires_at, // Registry expires when refresh token expires
        ]);
    }

    /**
     * Revoke a token pair and clean up registry.
     */
    protected function revokeTokenPair(MobileTokenRegistry $registry): void
    {
        // Revoke access token if it exists
        if ($registry->accessToken) {
            $registry->accessToken->delete();
        }

        // Revoke refresh token if it exists
        if ($registry->refreshToken) {
            $registry->refreshToken->delete();
        }

        // Remove registry entry
        $registry->delete();

        // Clear any cached token data
        Cache::forget("token_info:{$registry->user_id}:{$registry->device_id}");
    }

    /**
     * Extract device ID from token name.
     */
    protected function extractDeviceIdFromTokenName(string $tokenName): ?string
    {
        // Token names are in format: mobile_access_{deviceId}_{timestamp} or mobile_refresh_{deviceId}_{timestamp}
        if (preg_match('/mobile_(access|refresh)_(.+)_\d+$/', $tokenName, $matches)) {
            return $matches[2];
        }

        return null;
    }

    /**
     * Get plain text token (this is a limitation - normally not retrievable).
     */
    protected function getPlainTextToken(PersonalAccessToken $token): ?string
    {
        // In a real implementation, we'd need to store the plain text token temporarily
        // or use a different approach. For now, we'll return null to indicate unavailability.
        // This would be handled differently in production with proper token caching.
        return null;
    }
}
