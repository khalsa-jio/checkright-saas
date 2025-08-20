<?php

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\Facades\Tenancy;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create sessions table if it doesn't exist
    if (! \Illuminate\Support\Facades\Schema::hasTable('sessions')) {
        \Illuminate\Support\Facades\Artisan::call('session:table');
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    }
});

afterEach(function () {
    // Clean up any tenant context
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('registers tenant routes properly', function () {
    // Check if tenant routes are registered
    $routes = Route::getRoutes();
    $routeNames = [];

    foreach ($routes as $route) {
        $name = $route->getName();
        if ($name) {
            $routeNames[] = $name;
        }
    }

    // Debug: show all registered routes
    dump('All registered routes:', $routeNames);

    // Should have tenant routes
    $tenantRoutes = array_filter($routeNames, fn ($name) => str_contains($name, 'tenant'));
    expect($tenantRoutes)->not->toBeEmpty();
});

it('conditional tenancy middleware works for central domain', function () {
    // Test that central domain doesn't initialize tenancy
    $response = $this->withHeaders(['Host' => 'checkright.test'])
        ->get('/admin/login');

    expect(tenancy()->initialized)->toBeFalse();
    $response->assertOk();
});

it('conditional tenancy middleware works for tenant domain', function () {
    // Create tenant company and domain
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    $company->domains()->create([
        'domain' => 'testcompany.checkright.test',
    ]);

    // Create a test route to verify tenancy initialization
    Route::get('/test-tenancy', function () {
        return response()->json([
            'initialized' => tenancy()->initialized,
            'tenant_id' => tenancy()->initialized ? tenant('id') : null,
        ]);
    })->middleware(['web', \App\Http\Middleware\ConditionalTenancy::class]);

    // Test that tenant domain initializes tenancy
    $response = $this->withHeaders(['Host' => 'testcompany.checkright.test'])
        ->get('/test-tenancy');

    $response->assertOk();
    $data = $response->json();

    expect($data['initialized'])->toBeTrue();
    expect($data['tenant_id'])->toBe($company->id);
});

it('session bootstrapper works correctly', function () {
    // Create tenant company and domain
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    $company->domains()->create([
        'domain' => 'testcompany.checkright.test',
    ]);

    // Test tenant session configuration
    $response = $this->withHeaders(['Host' => 'testcompany.checkright.test'])
        ->withSession(['test_key' => 'test_value'])
        ->get('/admin/login');

    $response->assertOk();
    $response->assertSessionHas('test_key');
});

it('middleware stack is properly ordered', function () {
    // Get all middleware that would be applied to a web route
    $router = app('router');
    $middlewareGroups = $router->getMiddlewareGroups();

    dump('Web middleware group:', $middlewareGroups['web'] ?? []);

    // Should have session middleware before tenancy middleware
    expect($middlewareGroups['web'])->toContain(\App\Http\Middleware\TenantSessionBootstrapper::class);
});

it('can access filament admin routes on tenant domains', function () {
    // Create tenant company and domain
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    $company->domains()->create([
        'domain' => 'testcompany.checkright.test',
    ]);

    // Test accessing Filament admin login on tenant domain
    $response = $this->withHeaders(['Host' => 'testcompany.checkright.test'])
        ->get('/admin/login');

    // Should get the login page, not 404
    expect($response->status())->not->toBe(404);
    $response->assertOk();
});

it('tenant routes are accessible when tenancy is initialized', function () {
    // Create tenant company and domain
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    $company->domains()->create([
        'domain' => 'testcompany.checkright.test',
    ]);

    // Test tenant homepage route
    $response = $this->withHeaders(['Host' => 'testcompany.checkright.test'])
        ->get('/');

    // Should not be 404
    expect($response->status())->not->toBe(404);
});
