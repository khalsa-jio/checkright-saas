<?php

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Database\Models\Domain;

uses(RefreshDatabase::class);

afterEach(function () {
    // Clean up any tenant context
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('debugs conditional tenancy middleware behavior', function () {
    // Create tenant company and domain
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    $company->domains()->create([
        'domain' => 'testcompany.checkright.test',
    ]);

    // Create a debug route to test the middleware directly
    Route::get('/debug-tenancy', function () {
        return response()->json([
            'host' => request()->getHost(),
            'central_domains' => config('tenancy.central_domains'),
            'is_central' => in_array(request()->getHost(), config('tenancy.central_domains', [])),
            'tenancy_initialized' => tenancy()->initialized,
            'tenant_id' => tenancy()->initialized ? tenant('id') : null,
        ]);
    })->middleware(['web', \App\Http\Middleware\ConditionalTenancy::class]);

    // Test with tenant domain
    $response = $this->withHeaders(['Host' => 'testcompany.checkright.test'])
        ->get('/debug-tenancy');

    $response->assertOk();
    $data = $response->json();

    dump('Debug data:', $data);

    expect($data['host'])->toBe('testcompany.checkright.test');
    expect($data['is_central'])->toBeFalse();
    expect($data['tenancy_initialized'])->toBeTrue();
});

it('checks if domain resolution works', function () {
    // Create tenant company and domain
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    $company->domains()->create([
        'domain' => 'testcompany.checkright.test',
    ]);

    // Test domain resolution directly
    $resolver = app(\Stancl\Tenancy\Resolvers\DomainTenantResolver::class);

    $tenant = $resolver->resolve('testcompany.checkright.test');

    expect($tenant)->not->toBeNull();
    expect($tenant->id)->toBe($company->id);

    dump('Tenant resolved:', $tenant->toArray());
});
