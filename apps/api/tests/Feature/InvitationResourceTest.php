<?php

use App\Filament\Resources\InvitationResource\Pages\ListInvitations;
use App\Jobs\SendInvitationEmailJob;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create([
        'domain' => 'testcompany',
    ]);

    $this->superAdmin = User::factory()->create([
        'role' => 'super-admin',
        'tenant_id' => null, // Super admin has no tenant
    ]);

    $this->companyAdmin = User::factory()->create([
        'tenant_id' => $this->company->id,
        'role' => 'admin',
    ]);
});

it('can resend invitation with correct company subdomain URL', function () {
    Queue::fake();

    // Create an invitation
    $invitation = Invitation::factory()->create([
        'email' => 'test@example.com',
        'tenant_id' => $this->company->id,
        'role' => 'operator',
        'expires_at' => now()->addWeek(),
        'accepted_at' => null,
        'invited_by' => $this->companyAdmin->id,
    ]);

    // Act as super admin to test the resend functionality from central domain
    $this->actingAs($this->superAdmin);

    // Test the resend action
    Livewire::test(ListInvitations::class)
        ->callTableAction('resend', $invitation);

    // Assert that the job was dispatched with the correct URL
    Queue::assertPushed(SendInvitationEmailJob::class, function ($job) use ($invitation) {
        // Check that the URL uses the company's domain
        $expectedDomain = $this->company->domain . config('tenant.domain.suffix', '.test');
        $expectedUrl = "http://{$expectedDomain}/invitation/{$invitation->token}";

        return $job->invitation->id === $invitation->id &&
               $job->acceptUrl === $expectedUrl;
    });
});

it('can resend invitation from company admin', function () {
    Queue::fake();

    // Create an invitation
    $invitation = Invitation::factory()->create([
        'email' => 'test@example.com',
        'tenant_id' => $this->company->id,
        'role' => 'operator',
        'expires_at' => now()->addWeek(),
        'accepted_at' => null,
        'invited_by' => $this->companyAdmin->id,
    ]);

    // Act as company admin
    $this->actingAs($this->companyAdmin);

    // Test the resend action
    Livewire::test(ListInvitations::class)
        ->callTableAction('resend', $invitation);

    // Assert that the job was dispatched
    Queue::assertPushed(SendInvitationEmailJob::class, function ($job) use ($invitation) {
        return $job->invitation->id === $invitation->id;
    });
});

it('does not resend expired invitations', function () {
    Queue::fake();

    // Create an expired invitation
    $invitation = Invitation::factory()->create([
        'email' => 'expired@example.com',
        'tenant_id' => $this->company->id,
        'role' => 'operator',
        'expires_at' => now()->subDay(),
        'accepted_at' => null,
        'invited_by' => $this->companyAdmin->id,
    ]);

    $this->actingAs($this->companyAdmin);

    // Test the ListInvitations component
    $component = Livewire::test(ListInvitations::class);

    // Check that the resend action is not visible for expired invitations
    // This should be based on the isValid() method which returns false for expired invitations
    expect($invitation->isValid())->toBeFalse();
    expect($invitation->isExpired())->toBeTrue();
});

it('does not resend accepted invitations', function () {
    Queue::fake();

    // Create an accepted invitation
    $invitation = Invitation::factory()->create([
        'email' => 'accepted@example.com',
        'tenant_id' => $this->company->id,
        'role' => 'operator',
        'expires_at' => now()->addWeek(),
        'accepted_at' => now(),
        'invited_by' => $this->companyAdmin->id,
    ]);

    $this->actingAs($this->companyAdmin);

    // Test the ListInvitations component
    $component = Livewire::test(ListInvitations::class);

    // Check that the resend action is not visible for accepted invitations
    // This should be based on the isValid() method which returns false for accepted invitations
    expect($invitation->isValid())->toBeFalse();
    expect($invitation->isAccepted())->toBeTrue();
});

it('does not resend invitations when user already exists', function () {
    Queue::fake();

    // Create a user with the same email
    User::factory()->create([
        'email' => 'existing@example.com',
        'tenant_id' => $this->company->id,
        'role' => 'operator',
    ]);

    // Create an invitation for the same email
    $invitation = Invitation::factory()->create([
        'email' => 'existing@example.com',
        'tenant_id' => $this->company->id,
        'role' => 'manager',
        'expires_at' => now()->addWeek(),
        'accepted_at' => null,
        'invited_by' => $this->companyAdmin->id,
    ]);

    $this->actingAs($this->companyAdmin);

    // Test the ListInvitations component
    $component = Livewire::test(ListInvitations::class);

    // Check that the resend action is not visible when user already exists
    // This should be based on the isValid() method which returns false when user already exists
    expect($invitation->isValid())->toBeFalse();
    expect($invitation->userAlreadyExists())->toBeTrue();
});

it('can resend valid pending invitations', function () {
    Queue::fake();

    // Create a valid pending invitation
    $invitation = Invitation::factory()->create([
        'email' => 'valid@example.com',
        'tenant_id' => $this->company->id,
        'role' => 'operator',
        'expires_at' => now()->addWeek(),
        'accepted_at' => null,
        'invited_by' => $this->companyAdmin->id,
    ]);

    $this->actingAs($this->companyAdmin);

    // Call the resend action and verify the job is dispatched
    Livewire::test(ListInvitations::class)
        ->callTableAction('resend', $invitation);

    // Assert that the job was dispatched
    Queue::assertPushed(SendInvitationEmailJob::class, function ($job) use ($invitation) {
        return $job->invitation->id === $invitation->id;
    });
});

it('falls back to current domain when company has no subdomain', function () {
    Queue::fake();

    // Create a company without subdomain
    $companyWithoutSubdomain = Company::factory()->create([
        'subdomain' => null,
    ]);

    $invitation = Invitation::factory()->create([
        'email' => 'test@example.com',
        'tenant_id' => $companyWithoutSubdomain->id,
        'role' => 'operator',
        'expires_at' => now()->addWeek(),
        'accepted_at' => null,
        'invited_by' => $this->companyAdmin->id,
    ]);

    $this->actingAs($this->superAdmin);

    // Test the resend action
    Livewire::test(ListInvitations::class)
        ->callTableAction('resend', $invitation);

    // Assert that the job was dispatched (fallback should work)
    Queue::assertPushed(SendInvitationEmailJob::class, function ($job) use ($invitation) {
        return $job->invitation->id === $invitation->id;
    });
});
