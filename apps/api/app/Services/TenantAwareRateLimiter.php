<?php

namespace App\Services;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Cache;

/**
 * Tenant-aware rate limiter for enhanced security and performance.
 *
 * Features:
 * - Per-tenant rate limiting
 * - Global and tenant-specific limits
 * - Dynamic threshold adjustment
 * - Memory-efficient caching
 */
class TenantAwareRateLimiter
{
    private RateLimiter $limiter;

    public function __construct(?RateLimiter $limiter = null)
    {
        $this->limiter = $limiter ?: app(RateLimiter::class);
    }

    /**
     * Attempt to execute a callback with tenant-aware rate limiting.
     */
    public function attempt(string $key, int $maxAttempts, callable $callback, int $decaySeconds = 60): mixed
    {
        $tenantKey = $this->getTenantAwareKey($key);

        return $this->limiter->attempt($tenantKey, $maxAttempts, $callback, $decaySeconds);
    }

    /**
     * Check if the key has been rate limited.
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $tenantKey = $this->getTenantAwareKey($key);

        return $this->limiter->tooManyAttempts($tenantKey, $maxAttempts);
    }

    /**
     * Increment the counter for the given key.
     */
    public function hit(string $key, int $decaySeconds = 60): int
    {
        $tenantKey = $this->getTenantAwareKey($key);

        return $this->limiter->hit($tenantKey, $decaySeconds);
    }

    /**
     * Get the number of attempts for the given key.
     */
    public function attempts(string $key): int
    {
        $tenantKey = $this->getTenantAwareKey($key);

        return $this->limiter->attempts($tenantKey);
    }

    /**
     * Reset the number of attempts for the given key.
     */
    public function resetAttempts(string $key): bool
    {
        $tenantKey = $this->getTenantAwareKey($key);

        return $this->limiter->resetAttempts($tenantKey);
    }

    /**
     * Get the number of seconds until the key is accessible again.
     */
    public function availableIn(string $key): int
    {
        $tenantKey = $this->getTenantAwareKey($key);

        return $this->limiter->availableIn($tenantKey);
    }

    /**
     * Clear all rate limiting data for a specific tenant.
     */
    public function clearTenant(string $tenantId): void
    {
        $pattern = 'rate_limiter_tenant_' . $tenantId . '_*';
        $keys = Cache::get($pattern, []);

        if (! empty($keys)) {
            Cache::deleteMultiple($keys);
        }
    }

    /**
     * Get tenant-specific rate limits based on tenant plan/tier.
     */
    public function getTenantLimits(string $action): array
    {
        $tenantId = tenancy()->initialized ? tenant('id') : 'central';

        // Get tenant-specific limits from config or database
        $limits = config('tenant.security.rate_limits', []);
        $tenantLimits = $limits[$action] ?? $this->getDefaultLimits($action);

        // Adjust limits based on tenant plan (if implemented)
        if (tenancy()->initialized) {
            $tenant = tenant();
            $plan = $tenant->plan ?? 'basic';

            $multiplier = match ($plan) {
                'enterprise' => 5.0,
                'pro' => 2.0,
                'basic' => 1.0,
                default => 1.0,
            };

            $tenantLimits['max_attempts'] = (int) ($tenantLimits['max_attempts'] * $multiplier);
        }

        return $tenantLimits;
    }

    /**
     * Generate a tenant-aware cache key.
     */
    private function getTenantAwareKey(string $key): string
    {
        $tenantId = tenancy()->initialized ? tenant('id') : 'central';

        return "rate_limiter_tenant_{$tenantId}_{$key}";
    }

    /**
     * Get default rate limits for different actions.
     */
    private function getDefaultLimits(string $action): array
    {
        return match ($action) {
            'login' => [
                'max_attempts' => config('tenant.security.max_login_attempts', 5),
                'decay_seconds' => config('tenant.security.login_throttle_minutes', 1) * 60,
            ],
            'password_reset' => [
                'max_attempts' => 3,
                'decay_seconds' => 60,
            ],
            'api_request' => [
                'max_attempts' => 1000,
                'decay_seconds' => 3600, // Per hour
            ],
            'file_upload' => [
                'max_attempts' => 100,
                'decay_seconds' => 3600,
            ],
            default => [
                'max_attempts' => 60,
                'decay_seconds' => 60,
            ],
        };
    }
}
