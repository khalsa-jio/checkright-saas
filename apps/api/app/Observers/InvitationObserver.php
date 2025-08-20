<?php

namespace App\Observers;

use App\Models\Invitation;
use App\Services\Caching\TenantCacheService;

class InvitationObserver
{
    protected TenantCacheService $cacheService;

    public function __construct(TenantCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Handle the Invitation "created" event.
     */
    public function created(Invitation $invitation): void
    {
        if ($this->cacheService->isCachingEnabled() && $invitation->tenant_id) {
            // Invalidate company invitation-related caches
            $this->cacheService->invalidateCompanyCache($invitation->tenant_id);

            // Invalidate system stats
            $this->cacheService->invalidateSystemCache();
        }
    }

    /**
     * Handle the Invitation "updated" event.
     */
    public function updated(Invitation $invitation): void
    {
        if ($this->cacheService->isCachingEnabled()) {
            // If tenant_id changed, invalidate both old and new tenant caches
            if ($invitation->isDirty('tenant_id')) {
                $originalTenantId = $invitation->getOriginal('tenant_id');
                if ($originalTenantId) {
                    $this->cacheService->invalidateCompanyCache($originalTenantId);
                }
            }

            // Invalidate current tenant cache
            if ($invitation->tenant_id) {
                $this->cacheService->invalidateCompanyCache($invitation->tenant_id);
            }

            // Invalidate system stats
            $this->cacheService->invalidateSystemCache();
        }
    }

    /**
     * Handle the Invitation "deleted" event.
     */
    public function deleted(Invitation $invitation): void
    {
        if ($this->cacheService->isCachingEnabled() && $invitation->tenant_id) {
            // Invalidate company invitation-related caches
            $this->cacheService->invalidateCompanyCache($invitation->tenant_id);

            // Invalidate system stats
            $this->cacheService->invalidateSystemCache();
        }
    }
}
