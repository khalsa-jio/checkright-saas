<?php

namespace App\Services\Caching;

use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TenantCacheService
{
    protected int $defaultTtl;

    protected string $keyPrefix = 'tenant:';

    public function __construct()
    {
        $this->defaultTtl = config('tenant.performance.cache_ttl', 3600);
    }

    /**
     * Cache a company/tenant by domain.
     */
    public function cacheCompanyByDomain(string $domain, Company $company): void
    {
        $key = $this->getCacheKey('company:domain', $domain);

        Cache::put($key, $company, $this->defaultTtl);

        activity('tenant_cached')
            ->performedOn($company)
            ->withProperties(['cache_key' => $key, 'domain' => $domain])
            ->log('Company cached by domain');
    }

    /**
     * Get cached company by domain.
     */
    public function getCachedCompanyByDomain(string $domain): ?Company
    {
        $key = $this->getCacheKey('company:domain', $domain);

        return Cache::get($key);
    }

    /**
     * Cache company statistics.
     */
    public function cacheCompanyStats(string $companyId, array $stats): void
    {
        $key = $this->getCacheKey('company:stats', $companyId);

        Cache::put($key, $stats, $this->defaultTtl);

        Log::info('Company statistics cached', [
            'company_id' => $companyId,
            'cache_key' => $key,
            'stats' => $stats,
        ]);
    }

    /**
     * Get cached company statistics.
     */
    public function getCachedCompanyStats(string $companyId): ?array
    {
        $key = $this->getCacheKey('company:stats', $companyId);

        return Cache::get($key);
    }

    /**
     * Cache company users count.
     */
    public function cacheCompanyUsersCount(string $companyId): int
    {
        $key = $this->getCacheKey('company:users:count', $companyId);

        return Cache::remember($key, $this->defaultTtl, function () use ($companyId) {
            return User::where('tenant_id', $companyId)->count();
        });
    }

    /**
     * Cache pending invitations count for a company.
     */
    public function cachePendingInvitationsCount(string $companyId): int
    {
        $key = $this->getCacheKey('company:invitations:pending', $companyId);

        return Cache::remember($key, $this->defaultTtl / 2, function () use ($companyId) {
            return Invitation::where('tenant_id', $companyId)
                ->pending()
                ->count();
        });
    }

    /**
     * Cache system-wide statistics.
     */
    public function cacheSystemStats(): array
    {
        $key = $this->getCacheKey('system:stats');

        return Cache::remember($key, $this->defaultTtl * 2, function () {
            return [
                'total_companies' => Company::count(),
                'active_companies' => Company::has('users')->count(),
                'total_users' => User::count(),
                'pending_invitations' => Invitation::pending()->count(),
                'expired_invitations' => Invitation::expired()->count(),
                'cached_at' => now(),
            ];
        });
    }

    /**
     * Cache tenant configuration by domain.
     */
    public function cacheTenantConfig(string $domain, array $config): void
    {
        $key = $this->getCacheKey('config:domain', $domain);

        Cache::put($key, $config, $this->defaultTtl * 4); // Longer TTL for config
    }

    /**
     * Get cached tenant configuration.
     */
    public function getCachedTenantConfig(string $domain): ?array
    {
        $key = $this->getCacheKey('config:domain', $domain);

        return Cache::get($key);
    }

    /**
     * Invalidate all caches for a specific company.
     */
    public function invalidateCompanyCache(string $companyId, ?string $domain = null): void
    {
        $keys = [
            $this->getCacheKey('company:stats', $companyId),
            $this->getCacheKey('company:users:count', $companyId),
            $this->getCacheKey('company:invitations:pending', $companyId),
        ];

        if ($domain) {
            $keys[] = $this->getCacheKey('company:domain', $domain);
            $keys[] = $this->getCacheKey('config:domain', $domain);
        }

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        // Also invalidate system stats as company data changed
        Cache::forget($this->getCacheKey('system:stats'));

        Log::info('Company cache invalidated', [
            'company_id' => $companyId,
            'domain' => $domain,
            'invalidated_keys' => $keys,
        ]);

        activity('tenant_cache_invalidated')
            ->withProperties([
                'company_id' => $companyId,
                'domain' => $domain,
                'invalidated_keys_count' => count($keys),
            ])
            ->log('Company cache invalidated');
    }

    /**
     * Invalidate system-wide caches.
     */
    public function invalidateSystemCache(): void
    {
        $keys = [
            $this->getCacheKey('system:stats'),
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        Log::info('System cache invalidated', ['invalidated_keys' => $keys]);
    }

    /**
     * Warm up caches for a company.
     */
    public function warmupCompanyCache(Company $company): void
    {
        $domain = $company->domain;

        // Cache company by domain
        $this->cacheCompanyByDomain($domain, $company);

        // Pre-cache frequently accessed data
        $this->cacheCompanyUsersCount($company->id);
        $this->cachePendingInvitationsCount($company->id);

        // Cache company stats
        $stats = [
            'users_count' => $this->cacheCompanyUsersCount($company->id),
            'pending_invitations' => $this->cachePendingInvitationsCount($company->id),
            'created_at' => $company->created_at,
            'last_activity' => $company->updated_at,
        ];
        $this->cacheCompanyStats($company->id, $stats);

        Log::info('Company cache warmed up', [
            'company_id' => $company->id,
            'domain' => $domain,
        ]);

        activity('tenant_cache_warmed')
            ->performedOn($company)
            ->log('Company cache warmed up');
    }

    /**
     * Get cache key with prefix.
     */
    protected function getCacheKey(string $type, mixed $identifier = null): string
    {
        $key = $this->keyPrefix . $type;

        if ($identifier !== null) {
            $key .= ':' . $identifier;
        }

        return $key;
    }

    /**
     * Check if caching is enabled.
     */
    public function isCachingEnabled(): bool
    {
        return config('tenant.performance.cache_tenant_data', true);
    }

    /**
     * Get cache statistics.
     */
    public function getCacheStats(): array
    {
        // This would require Redis/cache-specific implementation
        // For now, return basic info
        return [
            'caching_enabled' => $this->isCachingEnabled(),
            'default_ttl' => $this->defaultTtl,
            'key_prefix' => $this->keyPrefix,
            'cache_driver' => config('cache.default'),
        ];
    }
}
