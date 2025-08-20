<?php

use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stancl\Tenancy\Database\Models\Domain;

uses(RefreshDatabase::class);

/*
 * Test the complete invitation acceptance flow and verify the impersonation route
 * works correctly after our middleware restructuring fix.
 */
it('completes full invitation acceptance flow successfully', function () {
    // Create a company with domain
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'domain' => 'testcompany',
    ]);

    // Create the tenant domain record
    $company->domains()->create([
        'domain' => 'testcompany' . config('tenant.domain.suffix', '.checkright.test'),
    ]);

    // Create a super admin who can send invitations
    $superAdmin = User::factory()->create([
        'role' => 'super-admin',
        'tenant_id' => null,
        'email' => 'admin@checkright.test',
    ]);

    // Create an invitation
    $invitation = Invitation::factory()->create([
        'email' => 'newuser@example.com',
        'role' => 'admin',
        'tenant_id' => $company->id,
        'invited_by' => $superAdmin->id,
        'token' => 'test-invitation-token',
        'expires_at' => now()->addDays(7),
        'accepted_at' => null,
    ]);

    // Test 1: Verify invitation acceptance form loads correctly
    $invitationUrl = $invitation->getAcceptanceUrl();
    $response = $this->get($invitationUrl);
    $response->assertStatus(200);
    $response->assertViewIs('invitations.accept');
    $response->assertViewHas('invitation', $invitation);
    $response->assertViewHas('token', $invitation->token);

    // Test 2: Submit the invitation acceptance form
    $response = $this->withHeaders(['Host' => 'testcompany.checkright.test'])
        ->post("/invitation/{$invitation->token}", [
            'name' => 'John Doe',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

    // Test 3: Verify user was created with correct details
    $newUser = User::where('email', 'newuser@example.com')->first();
    expect($newUser)->not->toBeNull();
    expect($newUser->name)->toBe('John Doe');
    expect($newUser->role)->toBe('admin');
    expect($newUser->tenant_id)->toBe($company->id);
    expect($newUser->email_verified_at)->not->toBeNull();

    // Test 4: Verify invitation was marked as accepted
    $invitation->refresh();
    expect($invitation->accepted_at)->not->toBeNull();

    // Test 5: Verify redirect to tenant admin dashboard (new behavior)
    $response->assertRedirect();
    $redirectUrl = $response->headers->get('Location');
    expect($redirectUrl)->toContain('/admin'); // Direct redirect to admin, no impersonation needed

    // Test 6: Verify user is already authenticated after invitation acceptance
    $this->assertAuthenticated();
    expect(auth()->user()->id)->toBe($newUser->id);
    expect(auth()->user()->email)->toBe('newuser@example.com');
});

it('handles invalid invitation tokens gracefully', function () {
    // Create a dummy company to have a tenant domain context
    $company = Company::factory()->create([
        'domain' => 'testinvalid',
    ]);
    $company->domains()->create([
        'domain' => 'testinvalid' . config('tenant.domain.suffix', '.checkright.test'),
    ]);

    $response = $this->withHeaders(['Host' => 'testinvalid.checkright.test'])
        ->get('/invitation/invalid-token');

    $response->assertStatus(200);
    $response->assertViewIs('invitations.invalid');
    $response->assertViewHas('message', 'This invitation is invalid, expired, or has already been accepted.');
});

it('prevents accepting expired invitations', function () {
    $company = Company::factory()->create();

    // Create domain entry for tenant resolution
    $company->domains()->create([
        'domain' => $company->domain . config('tenant.domain.suffix', '.checkright.test'),
    ]);

    // Create an expired invitation
    $invitation = Invitation::factory()->create([
        'email' => 'expired@example.com',
        'tenant_id' => $company->id,
        'token' => 'expired-invitation-token',
        'expires_at' => now()->subDays(1), // Expired
        'accepted_at' => null,
    ]);

    $response = $this->get($invitation->getAcceptanceUrl());

    $response->assertStatus(200);
    $response->assertViewIs('invitations.invalid');
});

it('prevents accepting already accepted invitations', function () {
    $company = Company::factory()->create();

    // Create domain entry for tenant resolution
    $company->domains()->create([
        'domain' => $company->domain . config('tenant.domain.suffix', '.checkright.test'),
    ]);

    // Create an already accepted invitation
    $invitation = Invitation::factory()->create([
        'email' => 'accepted@example.com',
        'tenant_id' => $company->id,
        'token' => 'accepted-invitation-token',
        'expires_at' => now()->addDays(7),
        'accepted_at' => now()->subHours(1), // Already accepted
    ]);

    $response = $this->get($invitation->getAcceptanceUrl());

    $response->assertStatus(200);
    $response->assertViewIs('invitations.invalid');
});

it('validates required fields in invitation acceptance form', function () {
    $company = Company::factory()->create();

    // Create domain entry for tenant resolution
    $company->domains()->create([
        'domain' => $company->domain . config('tenant.domain.suffix', '.checkright.test'),
    ]);

    $invitation = Invitation::factory()->create([
        'email' => 'test@example.com',
        'tenant_id' => $company->id,
        'token' => 'valid-invitation-token',
        'expires_at' => now()->addDays(7),
        'accepted_at' => null,
    ]);

    $tenantDomain = $company->domain . '.checkright.test';

    // Test missing name
    $response = $this->withHeaders(['Host' => $tenantDomain])
        ->post("/invitation/{$invitation->token}", [
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['name']);

    // Test missing password
    $response = $this->withHeaders(['Host' => $tenantDomain])
        ->post("/invitation/{$invitation->token}", [
            'name' => 'John Doe',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['password']);

    // Test password confirmation mismatch
    $response = $this->withHeaders(['Host' => $tenantDomain])
        ->post("/invitation/{$invitation->token}", [
            'name' => 'John Doe',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'DifferentPassword',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['password']);
});

it('logs invitation acceptance activity', function () {
    $company = Company::factory()->create();

    // Create domain entry for tenant resolution
    $company->domains()->create([
        'domain' => $company->domain . config('tenant.domain.suffix', '.checkright.test'),
    ]);

    $invitation = Invitation::factory()->create([
        'email' => 'activity@example.com',
        'role' => 'manager',
        'tenant_id' => $company->id,
        'token' => 'activity-test-token',
        'expires_at' => now()->addDays(7),
        'accepted_at' => null,
    ]);

    $response = $this->withHeaders(['Host' => $company->domain . '.checkright.test'])
        ->post("/invitation/{$invitation->token}", [
            'name' => 'Activity User',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

    // Ensure the request was successful
    $response->assertRedirect();

    // Verify activity was logged
    $user = User::where('email', 'activity@example.com')->first();
    expect($user)->not->toBeNull('User should be created after invitation acceptance');

    $activity = $user->activities()->where('description', 'like', '%invitation%')->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties->get('invitation_id'))->toBe($invitation->id);
    expect($activity->properties->get('role'))->toBe('manager');
    expect($activity->properties->get('company_id'))->toBe($company->id);
});

it('generates correct tenant domain URL and logs user in', function () {
    // Test with custom domain suffix
    config(['tenant.domain.suffix' => '.custom']);

    $company = Company::factory()->create([
        'domain' => 'mycompany',
    ]);

    // Create domain entry for tenant resolution
    $company->domains()->create([
        'domain' => $company->domain . '.custom',
    ]);

    $invitation = Invitation::factory()->create([
        'email' => 'domain@example.com',
        'tenant_id' => $company->id,
        'token' => 'domain-test-token',
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->withHeaders(['Host' => 'mycompany.custom'])
        ->post("/invitation/{$invitation->token}", [
            'name' => 'Domain User',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

    $response->assertRedirect();
    $redirectUrl = $response->headers->get('Location');
    expect($redirectUrl)->toContain('/admin'); // Direct redirect to admin dashboard

    // Verify user is authenticated
    $this->assertAuthenticated();
    $user = User::where('email', 'domain@example.com')->first();
    expect($user)->not->toBeNull();
    expect(auth()->user()->id)->toBe($user->id);

    // Reset config for other tests
    config(['tenant.domain.suffix' => '.checkright.test']);
});
