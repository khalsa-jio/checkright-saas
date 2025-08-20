<?php

namespace App\Observers;

use App\Models\Company;
use App\Services\Caching\TenantCacheService;

class CompanyObserver
{
    protected TenantCacheService $cacheService;

    public function __construct(TenantCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Handle the Company "created" event.
     */
    public function created(Company $company): void
    {
        if ($this->cacheService->isCachingEnabled()) {
            // Warm up cache for new company
            $this->cacheService->warmupCompanyCache($company);

            // Invalidate system stats
            $this->cacheService->invalidateSystemCache();
        }
    }

    /**
     * Handle the Company "updated" event.
     */
    public function updated(Company $company): void
    {
        if ($this->cacheService->isCachingEnabled()) {
            // If domain changed, we need to invalidate both old and new domain caches
            if ($company->isDirty('domain')) {
                $originalDomain = $company->getOriginal('domain');
                if ($originalDomain) {
                    $this->cacheService->invalidateCompanyCache($company->id, $originalDomain);
                }
            }

            // Invalidate current company cache
            $this->cacheService->invalidateCompanyCache($company->id, $company->domain);

            // Warm up cache with fresh data
            $this->cacheService->warmupCompanyCache($company);

            // Invalidate system stats
            $this->cacheService->invalidateSystemCache();
        }
    }

    /**
     * Handle the Company "deleted" event.
     */
    public function deleted(Company $company): void
    {
        if ($this->cacheService->isCachingEnabled()) {
            // Invalidate all caches related to this company
            $this->cacheService->invalidateCompanyCache($company->id, $company->domain);

            // Invalidate system stats
            $this->cacheService->invalidateSystemCache();
        }
    }
}
