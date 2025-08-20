<?php

namespace App\Http\Controllers;

use App\Http\Requests\RefreshTokenRequest;
use App\Services\Security\SecurityLogger;
use App\Services\Security\TokenManager;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TokenManagementController extends Controller
{
    protected TokenManager $tokenManager;

    protected SecurityLogger $securityLogger;

    public function __construct(TokenManager $tokenManager, SecurityLogger $securityLogger)
    {
        $this->tokenManager = $tokenManager;
        $this->securityLogger = $securityLogger;
    }

    /**
     * Generate new token pair for authenticated user.
     */
    public function generateTokens(Request $request): JsonResponse
    {
        $request->validate([
            'device_id' => 'required|string|min:10|max:255',
        ]);

        $user = auth()->user();
        $deviceId = $request->input('device_id');

        try {
            // Revoke existing tokens for this device first
            $this->tokenManager->revokeDeviceTokens($user->id, $deviceId);

            // Generate new token pair
            $tokens = $this->tokenManager->generateMobileTokens($user, $deviceId);

            $this->securityLogger->logSecurityEvent('mobile_tokens_generated', [
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'access_expires_in' => $tokens['access_expires_in'],
                'refresh_expires_in' => $tokens['refresh_expires_in'],
            ]);

            return response()->json([
                'message' => 'Tokens generated successfully',
                'tokens' => $tokens,
            ]);
        } catch (Exception $e) {
            $this->securityLogger->logSecurityEvent('token_generation_failed', [
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Token generation failed',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Refresh tokens using refresh token.
     */
    public function refreshTokens(RefreshTokenRequest $request): JsonResponse
    {
        $refreshToken = $request->input('refresh_token');

        try {
            $newTokens = $this->tokenManager->rotateTokens($refreshToken);

            $this->securityLogger->logSecurityEvent('tokens_refreshed', [
                'user_id' => auth()->id(),
                'refresh_token_used' => substr($refreshToken, 0, 10) . '...',
            ]);

            return response()->json([
                'message' => 'Tokens refreshed successfully',
                'tokens' => $newTokens,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->securityLogger->logSecurityEvent('token_refresh_failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'refresh_token_preview' => substr($refreshToken, 0, 10) . '...',
            ]);

            return response()->json([
                'error' => 'Token refresh failed',
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            $this->securityLogger->logSecurityEvent('token_refresh_error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Internal error during token refresh',
            ], 500);
        }
    }

    /**
     * Get token information for the current user's device.
     */
    public function getTokenInfo(Request $request): JsonResponse
    {
        $request->validate([
            'device_id' => 'required|string',
        ]);

        $user = auth()->user();
        $deviceId = $request->input('device_id');

        $tokenInfo = $this->tokenManager->getTokenInfo($user->id, $deviceId);

        if (! $tokenInfo) {
            return response()->json([
                'error' => 'No tokens found for this device',
            ], 404);
        }

        // Remove sensitive IDs for security
        unset($tokenInfo['access_token_id'], $tokenInfo['refresh_token_id']);

        return response()->json([
            'device_id' => $deviceId,
            'token_info' => $tokenInfo,
        ]);
    }

    /**
     * Revoke all tokens for a specific device.
     */
    public function revokeDeviceTokens(Request $request): JsonResponse
    {
        $request->validate([
            'device_id' => 'required|string',
        ]);

        $user = auth()->user();
        $deviceId = $request->input('device_id');

        try {
            $revokedCount = $this->tokenManager->revokeDeviceTokens($user->id, $deviceId);

            $this->securityLogger->logSecurityEvent('device_tokens_revoked', [
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'revoked_count' => $revokedCount,
            ]);

            return response()->json([
                'message' => "Revoked {$revokedCount} token pairs for device",
                'device_id' => $deviceId,
                'revoked_count' => $revokedCount,
            ]);
        } catch (Exception $e) {
            $this->securityLogger->logSecurityEvent('device_token_revocation_failed', [
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to revoke device tokens',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Revoke all tokens for the current user.
     */
    public function revokeAllTokens(): JsonResponse
    {
        $user = auth()->user();

        try {
            $revokedCount = $this->tokenManager->revokeAllUserTokens($user->id);

            $this->securityLogger->logSecurityEvent('all_user_tokens_revoked', [
                'user_id' => $user->id,
                'revoked_count' => $revokedCount,
            ]);

            return response()->json([
                'message' => "Revoked all {$revokedCount} token pairs for user",
                'revoked_count' => $revokedCount,
            ]);
        } catch (Exception $e) {
            $this->securityLogger->logSecurityEvent('user_token_revocation_failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to revoke user tokens',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if current token should be rotated.
     */
    public function shouldRotate(): JsonResponse
    {
        $user = auth()->user();
        $currentToken = $user->currentAccessToken();

        if (! $currentToken) {
            return response()->json([
                'should_rotate' => false,
                'message' => 'No current token found',
            ]);
        }

        $shouldRotate = $this->tokenManager->shouldRotateToken($currentToken);

        $response = [
            'should_rotate' => $shouldRotate,
            'token_expires_at' => $currentToken->expires_at,
            'token_created_at' => $currentToken->created_at,
        ];

        if ($shouldRotate) {
            $response['recommendation'] = 'Token should be rotated soon for optimal security';
        }

        return response()->json($response);
    }

    /**
     * Validate current token and return status.
     */
    public function validateToken(): JsonResponse
    {
        $user = auth()->user();
        $currentToken = $user->currentAccessToken();

        if (! $currentToken) {
            return response()->json([
                'valid' => false,
                'message' => 'No current token found',
            ], 401);
        }

        $isExpired = $currentToken->expires_at && $currentToken->expires_at->isPast();
        $shouldRotate = $this->tokenManager->shouldRotateToken($currentToken);

        return response()->json([
            'valid' => ! $isExpired,
            'expired' => $isExpired,
            'expires_at' => $currentToken->expires_at,
            'created_at' => $currentToken->created_at,
            'should_rotate' => $shouldRotate,
            'abilities' => $currentToken->abilities,
            'token_name' => $currentToken->name,
        ]);
    }
}
