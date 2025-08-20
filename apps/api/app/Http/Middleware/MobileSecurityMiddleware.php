<?php

namespace App\Http\Middleware;

use App\Exceptions\InvalidApiKeyException;
use App\Exceptions\InvalidDeviceException;
use App\Exceptions\InvalidSignatureException;
use App\Exceptions\RateLimitExceededException;
use App\Services\Security\DeviceFingerprintService;
use App\Services\Security\RequestSignatureValidator;
use App\Services\Security\SecurityLogger;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class MobileSecurityMiddleware
{
    protected DeviceFingerprintService $deviceService;

    protected RequestSignatureValidator $signatureValidator;

    protected SecurityLogger $securityLogger;

    public function __construct(
        DeviceFingerprintService $deviceService,
        RequestSignatureValidator $signatureValidator,
        SecurityLogger $securityLogger
    ) {
        $this->deviceService = $deviceService;
        $this->signatureValidator = $signatureValidator;
        $this->securityLogger = $securityLogger;
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Step 1: Validate API key
            $this->validateApiKey($request);

            // Step 2: Validate bearer token (handled by Sanctum)
            if (! auth()->check()) {
                $this->securityLogger->logSecurityEvent('auth_failure', [
                    'reason' => 'missing_or_invalid_token',
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Step 3: Verify device fingerprint and binding
            $this->validateDeviceBinding($request);

            // Step 4: Validate request signature (for sensitive operations)
            if ($this->requiresSignature($request)) {
                $this->signatureValidator->validateSignature($request);
            }

            // Step 5: Apply rate limiting
            $this->applyRateLimit($request);

            // Step 6: Log successful security validation
            $this->securityLogger->logSecurityEvent('security_validation_success', [
                'user_id' => auth()->id(),
                'device_id' => $request->header('X-Device-Id'),
                'endpoint' => $request->path(),
                'method' => $request->method(),
            ]);

            return $next($request);
        } catch (InvalidApiKeyException $e) {
            $this->securityLogger->logSecurityEvent('api_key_validation_failed', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'attempted_key' => substr($request->header('X-API-Key'), 0, 8) . '...',
            ]);

            return response()->json(['error' => 'Invalid API key'], 401);
        } catch (InvalidDeviceException $e) {
            $this->securityLogger->logSecurityEvent('device_validation_failed', [
                'user_id' => auth()->id(),
                'device_id' => $request->header('X-Device-Id'),
                'reason' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Device validation failed'], 403);
        } catch (InvalidSignatureException $e) {
            $this->securityLogger->logSecurityEvent('signature_validation_failed', [
                'user_id' => auth()->id(),
                'device_id' => $request->header('X-Device-Id'),
                'reason' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Request signature invalid'], 403);
        } catch (RateLimitExceededException $e) {
            $this->securityLogger->logSecurityEvent('rate_limit_exceeded', [
                'user_id' => auth()->id() ?? 'anonymous',
                'ip' => $request->ip(),
                'endpoint' => $request->path(),
                'limit_type' => $e->getLimitType(),
            ]);

            return response()->json([
                'error' => 'Rate limit exceeded',
                'retry_after' => $e->getRetryAfter(),
            ], 429);
        } catch (Exception $e) {
            // Log unexpected errors
            Log::error('Mobile security middleware error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'request_path' => $request->path(),
            ]);

            return response()->json(['error' => 'Security validation failed'], 500);
        }
    }

    /**
     * Validate the API key from the request header.
     */
    protected function validateApiKey(Request $request): void
    {
        if (! config('sanctum-mobile.api_key.required')) {
            return;
        }

        $apiKeyHeader = config('sanctum-mobile.api_key.header_name', 'X-API-Key');
        $providedKey = $request->header($apiKeyHeader);
        $expectedKey = config('sanctum-mobile.api_key.key');

        if (! $providedKey || ! $expectedKey) {
            throw new InvalidApiKeyException('API key is required');
        }

        if (! hash_equals($expectedKey, $providedKey)) {
            throw new InvalidApiKeyException('Invalid API key provided');
        }
    }

    /**
     * Validate device fingerprint and binding.
     */
    protected function validateDeviceBinding(Request $request): void
    {
        if (! config('sanctum-mobile.device_binding.enabled') || app()->environment('testing')) {
            return;
        }

        $deviceId = $request->header('X-Device-Id');
        if (! $deviceId) {
            throw new InvalidDeviceException('Device ID is required');
        }

        $user = auth()->user();
        if (! $this->deviceService->isDeviceTrusted($user->id, $deviceId)) {
            // Check if device is registered but not yet trusted
            if (! $this->deviceService->isDeviceRegistered($user->id, $deviceId)) {
                throw new InvalidDeviceException('Device not registered');
            }

            // Device is registered but not trusted - allow but log
            $this->securityLogger->logSecurityEvent('untrusted_device_access', [
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'requires_verification' => true,
            ]);
        }
    }

    /**
     * Determine if the request requires signature validation.
     */
    protected function requiresSignature(Request $request): bool
    {
        if (! config('sanctum-mobile.request_signing.enabled') || app()->environment('testing')) {
            return false;
        }

        // Define sensitive operations that require signature
        $sensitiveOperations = [
            'POST /api/mobile/users',
            'PUT /api/mobile/users/\*',
            'DELETE /api/mobile/users/\*',
            'POST /api/mobile/invitations',
            'POST /api/mobile/*/force-password-reset',
            'POST /api/mobile/*/bulk-*',
        ];

        $currentOperation = $request->method() . ' ' . $request->path();

        foreach ($sensitiveOperations as $pattern) {
            if (fnmatch($pattern, $currentOperation)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apply rate limiting based on user and endpoint.
     */
    protected function applyRateLimit(Request $request): void
    {
        $user = auth()->user();
        $endpoint = $request->path();
        $method = $request->method();

        // Different rate limits for different operations
        $limits = [
            'auth' => ['requests' => 10, 'window' => 900], // 10 requests per 15 minutes
            'api_general' => ['requests' => 1000, 'window' => 3600], // 1000 requests per hour
            'sensitive' => ['requests' => 50, 'window' => 3600], // 50 requests per hour
        ];

        $limitType = $this->determineLimitType($endpoint, $method);
        $limit = $limits[$limitType];

        $identifier = $user ? "user:{$user->id}" : "ip:{$request->ip()}";
        $key = "mobile_rate_limit:{$limitType}:{$identifier}";

        // Skip rate limiting in test environment
        if (app()->environment('testing')) {
            return;
        }

        $current = Redis::get($key) ?? 0;

        if ($current >= $limit['requests']) {
            throw new RateLimitExceededException($limitType, $limit['window']);
        }

        Redis::incr($key);
        Redis::expire($key, $limit['window']);
    }

    /**
     * Determine the rate limit type for the current request.
     */
    protected function determineLimitType(string $endpoint, string $method): string
    {
        // Authentication endpoints
        if (str_contains($endpoint, 'auth/')) {
            return 'auth';
        }

        // Sensitive operations
        $sensitivePatterns = [
            'users/',
            'invitations/',
            'bulk-',
            'force-password-reset',
        ];

        foreach ($sensitivePatterns as $pattern) {
            if (str_contains($endpoint, $pattern)) {
                return 'sensitive';
            }
        }

        return 'api_general';
    }
}
