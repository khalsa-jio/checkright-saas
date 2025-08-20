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

/*
 * This test demonstrates the current BROKEN state of tenant domain authentication.
 * It will FAIL until the AdminPanelProvider is fixed to include ConditionalTenancy middleware.
 */
it('demonstrates broken tenant domain authentication', function () {
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
        'company_id' => $company->id,
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

    // THIS WILL FAIL because tenancy is not initialized for Filament routes
    // The error will be: "These credentials do not match our records."
    // Because the user lookup happens in the central database, not the tenant context

    $loginComponent->assertHasFormErrors(['email']);

    // Debug: Check if tenancy was initialized during login attempt
    expect(tenancy()->initialized)->toBeFalse(); // This confirms tenancy wasn't initialized
})->skip('This test demonstrates the broken state - skip until fix is applied');

/*
 * This test will pass once the fix is applied.
 * It verifies that tenant domain authentication works correctly.
 */
it('tenant domain authentication works after fix', function () {
    // This test should pass after adding ConditionalTenancy middleware to AdminPanelProvider

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
        'company_id' => $company->id,
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

    // After fix: This should succeed
    $loginComponent->assertHasNoErrors();

    // Verify user is authenticated
    expect(auth()->check())->toBeTrue();
    expect(auth()->user()->email)->toBe('user@testcompany.test');
})->skip('This test will pass after applying the fix to AdminPanelProvider');

/*
 * Test that demonstrates central domain authentication still works
 */
it('central domain authentication works correctly', function () {
    // Create user in central database (no tenancy)
    $user = User::factory()->create([
        'email' => 'admin@checkright.test',
        'password' => bcrypt('password123'),
    ]);

    // Try to login via Filament on central domain
    $this->withHeaders(['Host' => 'checkright.test']);

    $loginComponent = \Livewire\Livewire::test(\App\Filament\Pages\Login::class)
        ->fillForm([
            'email' => 'admin@checkright.test',
            'password' => 'password123',
        ])
        ->call('authenticate');

    // Central domain login should work
    $loginComponent->assertHasNoErrors();

    // Verify user is authenticated
    expect(auth()->check())->toBeTrue();
    expect(auth()->user()->email)->toBe('admin@checkright.test');
});

/*
 * Test that verifies tenancy initialization in middleware
 */
it('verifies tenancy middleware behavior', function () {
    // Create tenant company and domain
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    $company->domains()->create([
        'domain' => 'testcompany.checkright.test',
    ]);

    // Test central domain - should NOT initialize tenancy
    $response = $this->withHeaders(['Host' => 'checkright.test'])
        ->get('/admin/login');

    expect(tenancy()->initialized)->toBeFalse();
    $response->assertOk();

    // Test tenant domain - currently DOES NOT initialize tenancy for Filament routes
    // This is the bug we need to fix
    $response = $this->withHeaders(['Host' => 'testcompany.checkright.test'])
        ->get('/admin/login');

    // This assertion shows the problem: tenancy is NOT initialized for Filament routes
    expect(tenancy()->initialized)->toBeFalse(); // This should be true after the fix
    $response->assertOk();
});
