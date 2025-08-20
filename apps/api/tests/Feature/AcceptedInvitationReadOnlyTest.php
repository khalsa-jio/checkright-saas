<?php

use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->superAdmin = User::factory()->create([
        'role' => 'super-admin',
        'tenant_id' => null,
    ]);
    $this->admin = User::factory()->create([
        'tenant_id' => $this->company->id,
        'role' => 'admin',
    ]);
});

it('prevents editing accepted invitations for super admin', function () {
    // Create an accepted invitation
    $invitation = Invitation::factory()->create([
        'tenant_id' => $this->company->id,
        'accepted_at' => now(),
        'email' => 'accepted@example.com',
        'role' => 'operator',
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->get("/admin/invitations/{$invitation->id}/edit");

    // Should redirect back to index with a warning
    $response->assertRedirect('/admin/invitations');
});

it('prevents editing accepted invitations for company admin', function () {
    // Create an accepted invitation
    $invitation = Invitation::factory()->create([
        'tenant_id' => $this->company->id,
        'accepted_at' => now(),
        'email' => 'accepted@example.com',
        'role' => 'operator',
    ]);

    $response = $this->actingAs($this->admin)
        ->get("/admin/invitations/{$invitation->id}/edit");

    // Should redirect back to index with a warning
    $response->assertRedirect('/admin/invitations');
});

it('allows editing pending invitations', function () {
    // Create a pending invitation
    $invitation = Invitation::factory()->create([
        'tenant_id' => $this->company->id,
        'accepted_at' => null,
        'email' => 'pending@example.com',
        'role' => 'operator',
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->actingAs($this->admin)
        ->get("/admin/invitations/{$invitation->id}/edit");

    // Should be able to access edit page
    $response->assertStatus(200);
});

it('shows company name in invitation table for super admin', function () {
    // Create invitations for different companies
    $company1 = Company::factory()->create(['name' => 'Test Company 1']);
    $company2 = Company::factory()->create(['name' => 'Test Company 2']);

    Invitation::factory()->create([
        'tenant_id' => $company1->id,
        'email' => 'user1@example.com',
    ]);

    Invitation::factory()->create([
        'tenant_id' => $company2->id,
        'email' => 'user2@example.com',
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->get('/admin/invitations');

    $response->assertStatus(200);
    $response->assertSee('Test Company 1');
    $response->assertSee('Test Company 2');
});

it('does not show company column for regular admin', function () {
    // Create invitations for different companies to test visibility
    $otherCompany = Company::factory()->create(['name' => 'Other Company']);

    // Create invitation for the admin's company
    Invitation::factory()->create([
        'tenant_id' => $this->company->id,
        'email' => 'user@example.com',
    ]);

    $response = $this->actingAs($this->admin)
        ->get('/admin/invitations');

    $response->assertStatus(200);
    // Should not see other company names since company column is hidden
    $response->assertDontSee('Other Company');
    // Should see the invitation email though
    $response->assertSee('user@example.com');
});
