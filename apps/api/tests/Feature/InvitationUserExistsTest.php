<?php

use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->admin = User::factory()->create([
        'tenant_id' => $this->company->id,
        'role' => 'admin',
    ]);
});

it('can detect when user already exists for invitation', function () {
    // Create a user
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'tenant_id' => $this->company->id,
        'role' => 'operator',
    ]);

    // Create an invitation for the same email
    $invitation = Invitation::factory()->create([
        'email' => 'test@example.com',
        'tenant_id' => $this->company->id,
        'role' => 'manager',
        'accepted_at' => null,
    ]);

    expect($invitation->userAlreadyExists())->toBeTrue();
    expect($invitation->existingUser())->not->toBeNull();
    expect($invitation->existingUser()->email)->toBe('test@example.com');
    expect($invitation->getStatus())->toBe('user_exists');
});

it('returns false when no user exists for invitation', function () {
    $invitation = Invitation::factory()->create([
        'email' => 'nonexistent@example.com',
        'tenant_id' => $this->company->id,
        'role' => 'operator',
        'accepted_at' => null,
    ]);

    expect($invitation->userAlreadyExists())->toBeFalse();
    expect($invitation->existingUser())->toBeNull();
    expect($invitation->getStatus())->toBe('pending');
});

it('returns correct status for accepted invitation', function () {
    $invitation = Invitation::factory()->create([
        'email' => 'accepted@example.com',
        'tenant_id' => $this->company->id,
        'role' => 'operator',
        'accepted_at' => now(),
    ]);

    expect($invitation->getStatus())->toBe('accepted');
});

it('returns correct status for expired invitation', function () {
    $invitation = Invitation::factory()->create([
        'email' => 'expired@example.com',
        'tenant_id' => $this->company->id,
        'role' => 'operator',
        'accepted_at' => null,
        'expires_at' => now()->subDay(),
    ]);

    expect($invitation->getStatus())->toBe('expired');
});

it('does not include invitations with existing users in pending scope', function () {
    // Create a user
    User::factory()->create([
        'email' => 'existing@example.com',
        'tenant_id' => $this->company->id,
    ]);

    // Create invitations
    $invitationWithExistingUser = Invitation::factory()->create([
        'email' => 'existing@example.com',
        'tenant_id' => $this->company->id,
        'accepted_at' => null,
    ]);

    $normalInvitation = Invitation::factory()->create([
        'email' => 'new@example.com',
        'tenant_id' => $this->company->id,
        'accepted_at' => null,
    ]);

    $pendingInvitations = Invitation::pending()->get();

    expect($pendingInvitations)->toHaveCount(1);
    expect($pendingInvitations->first()->id)->toBe($normalInvitation->id);
});

it('includes invitations with existing users in userExists scope', function () {
    // Create a user
    User::factory()->create([
        'email' => 'existing@example.com',
        'tenant_id' => $this->company->id,
    ]);

    // Create invitations
    $invitationWithExistingUser = Invitation::factory()->create([
        'email' => 'existing@example.com',
        'tenant_id' => $this->company->id,
        'accepted_at' => null,
    ]);

    $normalInvitation = Invitation::factory()->create([
        'email' => 'new@example.com',
        'tenant_id' => $this->company->id,
        'accepted_at' => null,
    ]);

    $userExistsInvitations = Invitation::userExists()->get();

    expect($userExistsInvitations)->toHaveCount(1);
    expect($userExistsInvitations->first()->id)->toBe($invitationWithExistingUser->id);
});

it('invitation is not valid when user already exists', function () {
    // Create a user
    User::factory()->create([
        'email' => 'existing@example.com',
        'tenant_id' => $this->company->id,
    ]);

    // Create an invitation for the same email
    $invitation = Invitation::factory()->create([
        'email' => 'existing@example.com',
        'tenant_id' => $this->company->id,
        'accepted_at' => null,
        'expires_at' => now()->addWeek(),
    ]);

    expect($invitation->isValid())->toBeFalse();
});

it('only checks users within same tenant', function () {
    $otherCompany = Company::factory()->create();

    // Create a user in different tenant
    User::factory()->create([
        'email' => 'test@example.com',
        'tenant_id' => $otherCompany->id,
    ]);

    // Create an invitation in our tenant
    $invitation = Invitation::factory()->create([
        'email' => 'test@example.com',
        'tenant_id' => $this->company->id,
        'accepted_at' => null,
    ]);

    expect($invitation->userAlreadyExists())->toBeFalse();
    expect($invitation->existingUser())->toBeNull();
    expect($invitation->getStatus())->toBe('pending');
});
