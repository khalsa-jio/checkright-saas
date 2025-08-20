<?php

use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company1 = Company::factory()->create(['name' => 'Company One']);
    $this->company2 = Company::factory()->create(['name' => 'Company Two']);

    $this->superAdmin = User::factory()->create([
        'role' => 'super-admin',
        'tenant_id' => null,
    ]);

    $this->existingInvitation = Invitation::factory()->create([
        'tenant_id' => $this->company1->id,
        'email' => 'test@example.com',
        'role' => 'operator',
    ]);
});

it('prevents creating duplicate invitation for same email and company', function () {
    $this->actingAs($this->superAdmin);

    $component = Livewire::test(\App\Filament\Resources\InvitationResource\Pages\CreateInvitation::class)
        ->fillForm([
            'tenant_id' => $this->company1->id,
            'email' => 'test@example.com', // Same email as existing invitation
            'role' => 'admin',
            'expires_at' => now()->addDays(7),
        ])
        ->call('create');

    // Should not create a duplicate invitation
    $this->assertDatabaseMissing('invitations', [
        'tenant_id' => $this->company1->id,
        'email' => 'test@example.com',
        'role' => 'admin',
    ]);
});

it('allows creating invitation for same email but different company', function () {
    $this->actingAs($this->superAdmin);

    $component = Livewire::test(\App\Filament\Resources\InvitationResource\Pages\CreateInvitation::class)
        ->fillForm([
            'tenant_id' => $this->company2->id, // Different company
            'email' => 'test@example.com', // Same email as existing invitation
            'role' => 'admin',
            'expires_at' => now()->addDays(7),
        ])
        ->call('create');

    $component->assertHasNoFormErrors();

    // Verify invitation was created
    $this->assertDatabaseHas('invitations', [
        'tenant_id' => $this->company2->id,
        'email' => 'test@example.com',
        'role' => 'admin',
    ]);
});

it('prevents updating invitation company to create duplicate', function () {
    // Create invitation for company2
    $invitation = Invitation::factory()->create([
        'tenant_id' => $this->company2->id,
        'email' => 'test@example.com',
        'role' => 'operator',
    ]);

    $this->actingAs($this->superAdmin);

    // Try to update to company1 (where there's already an invitation for this email)
    $component = Livewire::test(\App\Filament\Resources\InvitationResource\Pages\EditInvitation::class, [
        'record' => $invitation->id,
    ])->fillForm([
        'tenant_id' => $this->company1->id, // This should cause conflict
        'email' => 'test@example.com',
        'role' => 'operator',
        'expires_at' => $invitation->expires_at,
    ])->call('save');

    // Invitation should not be updated
    $invitation->refresh();
    expect($invitation->tenant_id)->toBe($this->company2->id); // Should still be company2
});

it('allows updating invitation to same company', function () {
    $this->actingAs($this->superAdmin);

    $component = Livewire::test(\App\Filament\Resources\InvitationResource\Pages\EditInvitation::class, [
        'record' => $this->existingInvitation->id,
    ])->fillForm([
        'tenant_id' => $this->company1->id, // Same company
        'email' => 'test@example.com', // Same email
        'role' => 'admin', // Just changing role
        'expires_at' => $this->existingInvitation->expires_at,
    ])->call('save');

    $component->assertHasNoFormErrors();

    // Verify role was updated
    $this->existingInvitation->refresh();
    expect($this->existingInvitation->role)->toBe('admin');
});
