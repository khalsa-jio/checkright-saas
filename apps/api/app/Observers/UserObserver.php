<?php

namespace App\Observers;

use App\Models\User;
use App\Services\Caching\TenantCacheService;

class UserObserver
{
    protected TenantCacheService $cacheService;

    public function __construct(TenantCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        if ($this->cacheService->isCachingEnabled() && $user->tenant_id) {
            // Invalidate company-related caches
            $this->cacheService->invalidateCompanyCache($user->tenant_id);

            // Invalidate system stats
            $this->cacheService->invalidateSystemCache();
        }
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        if ($this->cacheService->isCachingEnabled()) {
            // If tenant_id changed, invalidate both old and new tenant caches
            if ($user->isDirty('tenant_id')) {
                $originalTenantId = $user->getOriginal('tenant_id');
                if ($originalTenantId) {
                    $this->cacheService->invalidateCompanyCache($originalTenantId);
                }
            }

            // Invalidate current tenant cache
            if ($user->tenant_id) {
                $this->cacheService->invalidateCompanyCache($user->tenant_id);
            }

            // Invalidate system stats
            $this->cacheService->invalidateSystemCache();
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        if ($this->cacheService->isCachingEnabled() && $user->tenant_id) {
            // Invalidate company-related caches
            $this->cacheService->invalidateCompanyCache($user->tenant_id);

            // Invalidate system stats
            $this->cacheService->invalidateSystemCache();
        }
    }
}
