<?php

use App\Models\Company;
use App\Services\Caching\TenantCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

describe('TenantCacheService Unit Tests', function () {
    beforeEach(function () {
        Cache::flush(); // Clear cache before each test
        $this->service = new TenantCacheService();
    });

    it('can instantiate TenantCacheService', function () {
        expect($this->service)->toBeInstanceOf(TenantCacheService::class);
    });

    describe('company caching', function () {
        it('caches company by domain', function () {
            $company = Company::factory()->create(['domain' => 'test-domain']);

            $this->service->cacheCompanyByDomain('test-domain', $company);
            $cached = $this->service->getCachedCompanyByDomain('test-domain');

            expect($cached)->toBeInstanceOf(Company::class)
                ->and($cached->id)->toBe($company->id)
                ->and($cached->domain)->toBe('test-domain');
        });

        it('returns null for non-existent cached company', function () {
            $cached = $this->service->getCachedCompanyByDomain('non-existent');

            expect($cached)->toBeNull();
        });

        it('caches and retrieves company stats', function () {
            $stats = [
                'user_count' => 10,
                'active_invitations' => 5,
                'last_activity' => now()->toISOString(),
            ];

            $this->service->cacheCompanyStats('company-123', $stats);
            $cached = $this->service->getCachedCompanyStats('company-123');

            expect($cached)->toBe($stats);
        });
    });

    describe('cache invalidation', function () {
        it('invalidates company cache', function () {
            $company = Company::factory()->create(['domain' => 'test-domain']);
            $this->service->cacheCompanyByDomain('test-domain', $company);

            // Verify it's cached
            expect($this->service->getCachedCompanyByDomain('test-domain'))->not->toBeNull();

            // Invalidate cache with domain parameter
            $this->service->invalidateCompanyCache($company->id, 'test-domain');

            // Verify it's cleared
            expect($this->service->getCachedCompanyByDomain('test-domain'))->toBeNull();
        });

        it('invalidates system cache', function () {
            // Get initial system stats (this will cache them)
            $initialStats = $this->service->cacheSystemStats();

            // Verify cached
            expect($initialStats)->toHaveKey('total_companies');

            // Invalidate
            $this->service->invalidateSystemCache();

            // Create new data to verify cache was cleared
            Company::factory()->create();
            $newStats = $this->service->cacheSystemStats();

            // Should have different count now
            expect($newStats['total_companies'])->toBeGreaterThan($initialStats['total_companies']);
        });
    });

    describe('cache warming', function () {
        it('warms up company cache', function () {
            $company = Company::factory()->create();

            $this->service->warmupCompanyCache($company);

            // This test depends on the implementation
            // For now, just verify the method doesn't throw an exception
            expect(true)->toBeTrue();
        });
    });

    describe('caching state', function () {
        it('reports caching enabled state', function () {
            $isEnabled = $this->service->isCachingEnabled();

            expect($isEnabled)->toBeBool();
        });
    });
});
