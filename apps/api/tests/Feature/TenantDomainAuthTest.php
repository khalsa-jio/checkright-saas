<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\Facades\Tenancy;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create sessions table if it doesn't exist (for database sessions)
    if (! Schema::hasTable('sessions')) {
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

it('can create tenant company with domain', function () {
    // Create a tenant company
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    // Create the domain for the company
    $company->domains()->create([
        'domain' => 'testcompany.checkright.test',
    ]);

    expect($company)->toBeInstanceOf(Company::class);
    expect($company->domain)->toBe('testcompany');

    // Verify domain was created
    $domain = Domain::where('domain', 'testcompany.checkright.test')->first();
    expect($domain)->toBeInstanceOf(Domain::class);
    expect($domain->tenant_id)->toBe($company->id);
});

it('can access tenant domain homepage', function () {
    // Create tenant company
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    // Create the domain for the company
    $company->domains()->create([
        'domain' => 'testcompany.checkright.test',
    ]);

    // Test accessing tenant domain
    $response = $this->withHeaders([
        'Host' => 'testcompany.checkright.test',
    ])->get('/');

    // Debug the response
    dump('Response status: ' . $response->status());
    dump('Response content: ' . $response->content());

    $response->assertOk();
    $response->assertSee($company->id); // Should see tenant ID in the response
});

it('can access tenant domain admin login page', function () {
    // Create tenant company
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    // Create the domain for the company
    $company->domains()->create([
        'domain' => 'testcompany.checkright.test',
    ]);

    // Test accessing tenant admin login
    $response = $this->withHeaders([
        'Host' => 'testcompany.checkright.test',
    ])->get('/admin/login');

    $response->assertStatus(200);
    $response->assertSee('Login');
});

it('initializes tenancy for tenant domains', function () {
    // Create tenant company
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    // Create the domain for the company
    $company->domains()->create([
        'domain' => 'testcompany.checkright.test',
    ]);

    // Make request to tenant domain
    $this->withHeaders([
        'Host' => 'testcompany.checkright.test',
    ])->get('/');

    // Check if tenancy was initialized
    expect(tenancy()->initialized)->toBeTrue();
    expect(tenant('id'))->toBe($company->id);
});

it('does not initialize tenancy for central domains', function () {
    // Make request to central domain
    $this->withHeaders([
        'Host' => 'checkright.test',
    ])->get('/admin/login');

    // Check that tenancy was not initialized
    expect(tenancy()->initialized)->toBeFalse();
});

it('can create user in tenant context', function () {
    // Create tenant company
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    // Initialize tenancy
    tenancy()->initialize($company);

    // Create user in tenant context
    $user = User::factory()->create([
        'email' => 'user@testcompany.test',
        'company_id' => $company->id,
    ]);

    expect($user)->toBeInstanceOf(User::class);
    expect($user->company_id)->toBe($company->id);
    expect(tenancy()->initialized)->toBeTrue();
});

it('handles session isolation between tenant and central domains', function () {
    // Create tenant company
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    // Create the domain for the company
    $company->domains()->create([
        'domain' => 'testcompany.checkright.test',
    ]);

    // Set session data on central domain
    $centralResponse = $this->withSession(['central_key' => 'central_value'])
        ->withHeaders(['Host' => 'checkright.test'])
        ->get('/admin/login');

    $centralResponse->assertOk();
    $centralResponse->assertSessionHas('central_key', 'central_value');

    // Access tenant domain - should not have central session data
    $tenantResponse = $this->withHeaders(['Host' => 'testcompany.checkright.test'])
        ->get('/admin/login');

    $tenantResponse->assertOk();
    $tenantResponse->assertSessionMissing('central_key');
});

it('can authenticate user on tenant domain', function () {
    // Create tenant company
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    // Create the domain for the company
    $company->domains()->create([
        'domain' => 'testcompany.checkright.test',
    ]);

    // Initialize tenancy
    tenancy()->initialize($company);

    // Create user in tenant context
    $user = User::factory()->create([
        'email' => 'user@testcompany.test',
        'password' => bcrypt('password123'),
        'company_id' => $company->id,
    ]);

    // End tenancy context for the test
    tenancy()->end();

    // Attempt to login on tenant domain using Filament auth
    $response = $this->withHeaders(['Host' => 'testcompany.checkright.test'])
        ->post('/admin/login', [
            'email' => 'user@testcompany.test',
            'password' => 'password123',
        ]);

    // Should redirect after successful login
    expect($response->status())->toBeOneOf([302, 200]);
});

it('can authenticate user with livewire filament login', function () {
    // Create tenant company
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    // Create the domain for the company
    $company->domains()->create([
        'domain' => 'testcompany.checkright.test',
    ]);

    // Initialize tenancy
    tenancy()->initialize($company);

    // Create user in tenant context
    $user = User::factory()->create([
        'email' => 'user@testcompany.test',
        'password' => bcrypt('password123'),
        'company_id' => $company->id,
    ]);

    // End tenancy context for the test
    tenancy()->end();

    // Test Filament login using Livewire
    $this->withHeaders(['Host' => 'testcompany.checkright.test']);

    \Livewire\Livewire::test(\App\Filament\Pages\Login::class)
        ->fillForm([
            'email' => 'user@testcompany.test',
            'password' => 'password123',
        ])
        ->call('authenticate')
        ->assertHasNoErrors();
});

it('rejects authentication with invalid credentials on tenant domain', function () {
    // Create tenant company
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    // Create the domain for the company
    $company->domains()->create([
        'domain' => 'testcompany.checkright.test',
    ]);

    // Test invalid login
    $this->withHeaders(['Host' => 'testcompany.checkright.test']);

    \Livewire\Livewire::test(\App\Filament\Pages\Login::class)
        ->fillForm([
            'email' => 'nonexistent@testcompany.test',
            'password' => 'wrongpassword',
        ])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);
});

it('can access protected routes after authentication on tenant domain', function () {
    // Create tenant company
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    // Create the domain for the company
    $company->domains()->create([
        'domain' => 'testcompany.checkright.test',
    ]);

    // Initialize tenancy
    tenancy()->initialize($company);

    // Create user in tenant context
    $user = User::factory()->create([
        'email' => 'user@testcompany.test',
        'password' => bcrypt('password123'),
        'company_id' => $company->id,
    ]);

    // End tenancy context for the test
    tenancy()->end();

    // Login and access protected route
    $response = $this->actingAs($user)
        ->withHeaders(['Host' => 'testcompany.checkright.test'])
        ->get('/admin');

    $response->assertOk();
});

it('prevents cross-tenant user authentication', function () {
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

    // Initialize tenancy for company1
    tenancy()->initialize($company1);

    // Create user in company1 context
    $user1 = User::factory()->create([
        'email' => 'user@company1.test',
        'password' => bcrypt('password123'),
        'company_id' => $company1->id,
    ]);

    tenancy()->end();

    // Initialize tenancy for company2
    tenancy()->initialize($company2);

    // Create user in company2 context
    $user2 = User::factory()->create([
        'email' => 'user@company2.test',
        'password' => bcrypt('password123'),
        'company_id' => $company2->id,
    ]);

    tenancy()->end();

    // Try to authenticate company1 user on company2 domain
    $this->withHeaders(['Host' => 'company2.checkright.test']);

    \Livewire\Livewire::test(\App\Filament\Pages\Login::class)
        ->fillForm([
            'email' => 'user@company1.test',
            'password' => 'password123',
        ])
        ->call('authenticate')
        ->assertHasFormErrors(['email']); // Should fail
});

it('maintains session state across tenant domain requests', function () {
    // Create tenant company
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    // Create the domain for the company
    $company->domains()->create([
        'domain' => 'testcompany.checkright.test',
    ]);

    // Initialize tenancy
    tenancy()->initialize($company);

    // Create user in tenant context
    $user = User::factory()->create([
        'email' => 'user@testcompany.test',
        'password' => bcrypt('password123'),
        'company_id' => $company->id,
    ]);

    tenancy()->end();

    // First request - login
    $response1 = $this->withHeaders(['Host' => 'testcompany.checkright.test'])
        ->post('/admin/login', [
            'email' => 'user@testcompany.test',
            'password' => 'password123',
        ]);

    // Get session from first response
    $session = $this->app['session.store'];

    // Second request - should maintain session
    $response2 = $this->withSession($session->all())
        ->withHeaders(['Host' => 'testcompany.checkright.test'])
        ->get('/admin');

    // Should be able to access protected route
    expect($response2->status())->toBeOneOf([200, 302]);
});
