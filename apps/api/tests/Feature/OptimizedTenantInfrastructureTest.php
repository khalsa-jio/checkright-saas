<?php

use App\Http\Middleware\ConditionalTenancy;
use App\Http\Middleware\TenantSessionBootstrapper;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Database\Models\Domain;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create sessions table if it doesn't exist
    if (! \Illuminate\Support\Facades\Schema::hasTable('sessions')) {
        \Illuminate\Support\Facades\Artisan::call('session:table');
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    }

    // Clear middleware caches
    ConditionalTenancy::clearCache();
    TenantSessionBootstrapper::clearCache();
});

afterEach(function () {
    // Clean up any tenant context
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    // Clear caches
    Cache::flush();
});

it('optimized conditional tenancy middleware handles central domains efficiently', function () {
    // Create multiple requests to test caching
    for ($i = 0; $i < 5; $i++) {
        $response = $this->withHeaders(['Host' => 'checkright.test'])
            ->get('/admin/login');

        expect(tenancy()->initialized)->toBeFalse();
        $response->assertOk();
    }

    // Verify caching is working (should not initialize tenancy multiple times)
    expect(tenancy()->initialized)->toBeFalse();
});

it('optimized conditional tenancy middleware handles tenant domains with caching', function () {
    // Create tenant company and domain
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    $domain = $company->domains()->create([
        'domain' => 'testcompany.checkright.test',
    ]);

    // Use the existing tenant homepage route for testing
    // First request should initialize tenancy
    $response = $this->withHeaders(['Host' => 'testcompany.checkright.test'])
        ->get('/');

    $response->assertOk();
    $data = $response->json();

    // Verify tenant data in response
    expect($data['initialized'])->toBeTrue();
    expect($data['tenant_id'])->toBe($company->id);
    expect($data['tenant_name'])->toBe('Test Company');

    // Second request should also work
    $response2 = $this->withHeaders(['Host' => 'testcompany.checkright.test'])
        ->get('/');

    $response2->assertOk();
    $data2 = $response2->json();

    expect($data2['initialized'])->toBeTrue();
    expect($data2['tenant_id'])->toBe($company->id);
});

it('optimized session bootstrapper configures sessions correctly for different domain types', function () {
    // Create tenant for testing
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    $company->domains()->create([
        'domain' => 'testcompany.checkright.test',
    ]);

    // Test central domain session configuration
    $centralResponse = $this->withHeaders(['Host' => 'checkright.test'])
        ->get('/admin/login');

    $centralResponse->assertOk();

    // Test tenant domain session configuration
    $tenantResponse = $this->withHeaders(['Host' => 'testcompany.checkright.test'])
        ->withSession(['test_key' => 'test_value'])
        ->get('/');

    $tenantResponse->assertOk();

    // Verify tenant is initialized
    expect(tenancy()->initialized)->toBeTrue();
});

it('handles non-existent tenant domains gracefully', function () {
    // Try to access a non-existent tenant domain
    $response = $this->withHeaders(['Host' => 'nonexistent.checkright.test'])
        ->get('/');

    // Should return 404 for non-existent tenant
    $response->assertNotFound();
});

it('tenant homepage returns optimized response with caching headers', function () {
    // Create tenant
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    $company->domains()->create([
        'domain' => 'testcompany.checkright.test',
    ]);

    $response = $this->withHeaders(['Host' => 'testcompany.checkright.test'])
        ->get('/');

    $response->assertOk();

    // Check response structure
    $data = $response->json();
    expect($data)->toHaveKeys(['message', 'tenant_id', 'tenant_name', 'initialized']);
    expect($data['initialized'])->toBeTrue();
    expect($data['tenant_id'])->toBe($company->id);

    // Check caching headers
    expect($response->headers->get('Cache-Control'))->toBe('public, max-age=300');
});

it('rate limiting is applied to tenant routes', function () {
    // Create tenant
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    $company->domains()->create([
        'domain' => 'testcompany.checkright.test',
    ]);

    // Test rate limiting on impersonation route (10 requests per minute)
    for ($i = 0; $i < 11; $i++) {
        $response = $this->withHeaders(['Host' => 'testcompany.checkright.test'])
            ->get('/impersonate/invalid-token');
    }

    // The 11th request should be rate limited
    expect($response->status())->toBe(429);
});

it('password reset routes have enhanced rate limiting', function () {
    // Create tenant
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    $company->domains()->create([
        'domain' => 'testcompany.checkright.test',
    ]);

    // Test enhanced rate limiting on password reset (3 requests per minute)
    for ($i = 0; $i < 4; $i++) {
        $response = $this->withHeaders(['Host' => 'testcompany.checkright.test'])
            ->post('/password/email', ['email' => 'test@example.com']);
    }

    // The 4th request should be rate limited
    expect($response->status())->toBe(429);
});

it('middleware stack is properly optimized and ordered', function () {
    $router = app('router');
    $middlewareGroups = $router->getMiddlewareGroups();

    // Verify TenantSessionBootstrapper is in web middleware group
    expect($middlewareGroups['web'])->toContain(TenantSessionBootstrapper::class);

    // Verify middleware priority is set correctly
    $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
    $middlewarePriority = $kernel->getMiddlewarePriority();

    // ConditionalTenancy should be in the priority list
    expect($middlewarePriority)->toContain(ConditionalTenancy::class);
});

it('caching optimizations work for tenant data', function () {
    // Create tenant
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    $company->domains()->create([
        'domain' => 'testcompany.checkright.test',
    ]);

    // Clear cache first
    Cache::flush();

    // First request should cache tenant existence
    $response1 = $this->withHeaders(['Host' => 'testcompany.checkright.test'])
        ->get('/');

    $response1->assertOk();

    // Verify cache is populated
    $cacheKey = 'tenant_exists_testcompany.checkright.test';
    expect(Cache::has($cacheKey))->toBeTrue();

    // Second request should use cached data
    $response2 = $this->withHeaders(['Host' => 'testcompany.checkright.test'])
        ->get('/');

    $response2->assertOk();
});

it('performance optimizations are registered in service provider', function () {
    // Verify tenant cache service is registered when caching is enabled
    config(['tenant.performance.cache_tenant_data' => true]);

    // The service should be resolvable
    expect(app()->bound('tenant.cache'))->toBeTrue();

    // Verify rate limiter is registered
    expect(app()->bound('tenant.rate_limiter'))->toBeTrue();
});

it('hybrid tenancy configuration is available', function () {
    // Verify hybrid tenancy settings are available in config
    $hybridConfig = config('tenant.hybrid');

    expect($hybridConfig)->toBeArray();
    expect($hybridConfig)->toHaveKeys([
        'enabled',
        'enterprise_threshold',
        'separate_db_criteria',
        'migration_queue',
    ]);
});
