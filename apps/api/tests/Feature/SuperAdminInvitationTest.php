<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminInvitationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function super_admin_can_invite_users_to_specific_company()
    {
        // Create a super admin
        $superAdmin = User::factory()->create([
            'role' => 'super-admin',
            'tenant_id' => null,
        ]);

        // Create a test company
        $company = Company::factory()->create([
            'name' => 'Test Company',
        ]);

        // Authenticate as super admin
        $this->actingAs($superAdmin);

        // Test invitation API with company selection
        $response = $this->postJson('/api/invitations', [
            'email' => 'test@example.com',
            'role' => 'admin',
            'tenant_id' => $company->id,
        ]);

        $response->assertStatus(201);

        // Verify invitation was created with correct tenant_id
        $this->assertDatabaseHas('invitations', [
            'email' => 'test@example.com',
            'role' => 'admin',
            'tenant_id' => $company->id,
            'invited_by' => $superAdmin->id,
        ]);
    }

    /** @test */
    public function super_admin_must_specify_company_for_non_super_admin_invitations()
    {
        // Create a super admin
        $superAdmin = User::factory()->create([
            'role' => 'super-admin',
            'tenant_id' => null,
        ]);

        // Authenticate as super admin
        $this->actingAs($superAdmin);

        // Test invitation API without company selection should fail
        $response = $this->postJson('/api/invitations', [
            'email' => 'test@example.com',
            'role' => 'admin',
            // Missing tenant_id
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tenant_id']);
    }

    /** @test */
    public function super_admin_can_invite_super_admin_without_company()
    {
        // Create a super admin
        $superAdmin = User::factory()->create([
            'role' => 'super-admin',
            'tenant_id' => null,
        ]);

        // Authenticate as super admin
        $this->actingAs($superAdmin);

        // Test super admin invitation (no tenant_id required)
        $response = $this->postJson('/api/invitations', [
            'email' => 'newsuper@example.com',
            'role' => 'super-admin',
            // No tenant_id needed for super-admin role
        ]);

        $response->assertStatus(201);

        // Verify invitation was created with null tenant_id
        $this->assertDatabaseHas('invitations', [
            'email' => 'newsuper@example.com',
            'role' => 'super-admin',
            'tenant_id' => null,
            'invited_by' => $superAdmin->id,
        ]);
    }

    /** @test */
    public function regular_admin_cannot_specify_different_company()
    {
        // Create a company and admin user
        $company = Company::factory()->create();
        $admin = User::factory()->create([
            'role' => 'admin',
            'tenant_id' => $company->id,
        ]);

        // Create another company
        $otherCompany = Company::factory()->create();

        // Authenticate as regular admin
        $this->actingAs($admin);

        // Test invitation API - tenant_id should be ignored for regular admins
        $response = $this->postJson('/api/invitations', [
            'email' => 'test@example.com',
            'role' => 'manager',
            'tenant_id' => $otherCompany->id, // This should be ignored
        ]);

        $response->assertStatus(201);

        // Verify invitation was created with admin's tenant_id, not the specified one
        $this->assertDatabaseHas('invitations', [
            'email' => 'test@example.com',
            'role' => 'manager',
            'tenant_id' => $company->id, // Should use admin's company
            'invited_by' => $admin->id,
        ]);
    }
}
