<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

it('tenant domain authentication now works correctly', function () {
    // Create tenant company and domain
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    $company->domains()->create([
        'domain' => 'testcompany.checkright.test',
    ]);

    // Initialize tenancy and create user
    tenancy()->initialize($company);

    $user = User::factory()->create([
        'email' => 'user@testcompany.test',
        'password' => bcrypt('password123'),
        'tenant_id' => $company->id,
    ]);

    tenancy()->end();

    // Try to login via Filament on tenant domain
    $this->withHeaders(['Host' => 'testcompany.checkright.test']);

    $loginComponent = \Livewire\Livewire::test(\App\Filament\Pages\Login::class)
        ->fillForm([
            'email' => 'user@testcompany.test',
            'password' => 'password123',
        ])
        ->call('authenticate');

    // Should succeed now that ConditionalTenancy middleware is added
    $loginComponent->assertHasNoErrors();
});

it('verifies tenancy is now initialized for filament routes on tenant domains', function () {
    // Create tenant company and domain
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    $company->domains()->create([
        'domain' => 'testcompany.checkright.test',
    ]);

    // Access Filament login on tenant domain
    $response = $this->withHeaders(['Host' => 'testcompany.checkright.test'])
        ->get('/admin/login');

    $response->assertOk();

    // Now tenancy should be initialized for Filament routes
    expect(tenancy()->initialized)->toBeTrue();
    expect(tenant('id'))->toBe($company->id);
});

it('central domain authentication still works correctly', function () {
    // Create user in central database (no tenancy)
    $user = User::factory()->create([
        'email' => 'admin@checkright.test',
        'password' => bcrypt('password123'),
    ]);

    // Access central domain
    $this->withHeaders(['Host' => 'checkright.test']);

    $loginComponent = \Livewire\Livewire::test(\App\Filament\Pages\Login::class)
        ->fillForm([
            'email' => 'admin@checkright.test',
            'password' => 'password123',
        ])
        ->call('authenticate');

    // Central domain login should still work
    $loginComponent->assertHasNoErrors();
});

it('prevents cross-tenant authentication correctly', function () {
    // Create two tenant companies
    $company1 = Company::factory()->create([
        'name' => 'Company One',
        'domain' => 'company1',
    ]);

    $company2 = Company::factory()->create([
        'name' => 'Company Two',
        'domain' => 'company2',
    ]);

    // Create domains for both companies
    $company1->domains()->create([
        'domain' => 'company1.checkright.test',
    ]);

    $company2->domains()->create([
        'domain' => 'company2.checkright.test',
    ]);

    // Create user in company1
    tenancy()->initialize($company1);
    $user1 = User::factory()->create([
        'email' => 'user@company1.test',
        'password' => bcrypt('password123'),
        'tenant_id' => $company1->id,
    ]);
    tenancy()->end();

    // Try to authenticate company1 user on company2 domain
    $this->withHeaders(['Host' => 'company2.checkright.test']);

    $loginComponent = \Livewire\Livewire::test(\App\Filament\Pages\Login::class)
        ->fillForm([
            'email' => 'user@company1.test',
            'password' => 'password123',
        ])
        ->call('authenticate');

    // Should fail because user doesn't exist in company2's context
    $loginComponent->assertHasFormErrors(['email']);
});

it('verifies session isolation between tenants', function () {
    // Create two tenant companies
    $company1 = Company::factory()->create([
        'name' => 'Company One',
        'domain' => 'company1',
    ]);

    $company2 = Company::factory()->create([
        'name' => 'Company Two',
        'domain' => 'company2',
    ]);

    // Create domains for both companies
    $company1->domains()->create([
        'domain' => 'company1.checkright.test',
    ]);

    $company2->domains()->create([
        'domain' => 'company2.checkright.test',
    ]);

    // Set session data on company1 domain
    $response1 = $this->withSession(['company1_key' => 'company1_value'])
        ->withHeaders(['Host' => 'company1.checkright.test'])
        ->get('/admin/login');

    $response1->assertOk();
    $response1->assertSessionHas('company1_key', 'company1_value');

    // Access company2 domain - should not have company1 session data
    $response2 = $this->withHeaders(['Host' => 'company2.checkright.test'])
        ->get('/admin/login');

    $response2->assertOk();
    $response2->assertSessionMissing('company1_key');
});
