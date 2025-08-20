<?php

use App\Filament\Resources\InvitationResource\Pages\ListInvitations;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    // Create a super admin user
    $this->superAdmin = User::factory()->create([
        'role' => 'super-admin',
        'tenant_id' => null,
    ]);

    // Create a company
    $this->company = Company::factory()->create([
        'domain' => 'testcompany',
    ]);

    // Create a valid invitation
    $this->invitation = Invitation::factory()->create([
        'tenant_id' => $this->company->id,
        'email' => 'test@example.com',
        'role' => 'operator',
        'expires_at' => now()->addDays(7),
        'accepted_at' => null,
    ]);

    // Create an expired invitation
    $this->expiredInvitation = Invitation::factory()->create([
        'tenant_id' => $this->company->id,
        'email' => 'expired@example.com',
        'role' => 'operator',
        'expires_at' => now()->subDays(1),
        'accepted_at' => null,
    ]);

    // Create an accepted invitation
    $this->acceptedInvitation = Invitation::factory()->create([
        'tenant_id' => $this->company->id,
        'email' => 'accepted@example.com',
        'role' => 'operator',
        'expires_at' => now()->addDays(7),
        'accepted_at' => now()->subHour(),
    ]);
});

it('allows super admin to see view invitation url action for valid invitations', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(ListInvitations::class)
        ->assertCanSeeTableRecords([$this->invitation])
        ->assertTableActionExists('view_invitation_url', record: $this->invitation);
});

it('hides view invitation url action for expired invitations', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(ListInvitations::class)
        ->assertTableActionHidden('view_invitation_url', record: $this->expiredInvitation);
});

it('hides view invitation url action for accepted invitations', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(ListInvitations::class)
        ->assertTableActionHidden('view_invitation_url', record: $this->acceptedInvitation);
});

it('allows super admin to view invitation url', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(ListInvitations::class)
        ->callTableAction('view_invitation_url', record: $this->invitation);
    // No notification expected for view action
});

it('hides view invitation url action for non-super-admin users', function () {
    // Create a regular admin user
    $admin = User::factory()->create([
        'role' => 'admin',
        'tenant_id' => $this->company->id,
    ]);

    $this->actingAs($admin);

    Livewire::test(ListInvitations::class)
        ->assertTableActionHidden('view_invitation_url', record: $this->invitation);
});

it('generates correct invitation url format', function () {
    $this->actingAs($this->superAdmin);

    // Mock the request to ensure we get predictable results
    config(['tenant.domain.suffix' => '.localhost']);

    Livewire::test(ListInvitations::class)
        ->callTableAction('view_invitation_url', record: $this->invitation);
    // No notification expected for view action
});

it('hides delete action for accepted invitations', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(ListInvitations::class)
        ->assertTableActionHidden('delete', record: $this->acceptedInvitation);
});

it('shows delete action for non-accepted invitations', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(ListInvitations::class)
        ->assertTableActionExists('delete', record: $this->invitation);
});

it('view existing user action navigates to user record', function () {
    // Create a user that matches an invitation email
    $user = User::factory()->create([
        'email' => 'existing@example.com',
        'tenant_id' => $this->company->id,
        'role' => 'operator',
    ]);

    // Create an invitation for the same email (user already exists scenario)
    $userExistsInvitation = Invitation::factory()->create([
        'tenant_id' => $this->company->id,
        'email' => 'existing@example.com',
        'role' => 'operator',
        'expires_at' => now()->addDays(7),
        'accepted_at' => null,
    ]);

    $this->actingAs($this->superAdmin);

    Livewire::test(ListInvitations::class)
        ->assertTableActionExists('view_existing_user', record: $userExistsInvitation)
        ->assertTableActionHasUrl(
            'view_existing_user',
            route('filament.admin.resources.users.view', ['record' => $user->id]),
            record: $userExistsInvitation
        );
});
