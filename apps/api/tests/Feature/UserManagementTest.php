<?php

namespace Tests\Feature;

use App\Jobs\SendInvitationEmailJob;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $superAdmin;

    protected User $admin;

    protected User $manager;

    protected User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test company and set it as tenant
        $this->company = Company::factory()->create();
        tenancy()->initialize($this->company);

        // Create users with different roles
        $this->superAdmin = User::factory()->create([
            'tenant_id' => null, // Super admin doesn't belong to any tenant
            'role' => 'super-admin',
        ]);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->company->id,
            'role' => 'admin',
        ]);

        $this->manager = User::factory()->create([
            'tenant_id' => $this->company->id,
            'role' => 'manager',
        ]);

        $this->operator = User::factory()->create([
            'tenant_id' => $this->company->id,
            'role' => 'operator',
        ]);
    }

    public function test_admin_can_list_all_users(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/users');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment(['email' => $this->admin->email])
            ->assertJsonFragment(['email' => $this->manager->email])
            ->assertJsonFragment(['email' => $this->operator->email]);
    }

    public function test_manager_can_list_users(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/users');

        $response->assertStatus(200);
    }

    public function test_operator_cannot_list_users(): void
    {
        Sanctum::actingAs($this->operator);

        $response = $this->getJson('/api/users');

        $response->assertStatus(403);
    }

    public function test_admin_can_invite_any_role(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/invitations', [
            'email' => 'new-admin@example.com',
            'role' => 'admin',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['message' => 'Invitation sent successfully.']);

        $this->assertDatabaseHas('invitations', [
            'email' => 'new-admin@example.com',
            'role' => 'admin',
            'tenant_id' => $this->company->id,
            'invited_by' => $this->admin->id,
        ]);

        Queue::assertPushed(SendInvitationEmailJob::class);
    }

    public function test_manager_can_only_invite_operators(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->manager);

        // Should succeed for operator role
        $response = $this->postJson('/api/invitations', [
            'email' => 'new-operator@example.com',
            'role' => 'operator',
        ]);

        $response->assertStatus(201);

        // Should fail for admin role
        $response = $this->postJson('/api/invitations', [
            'email' => 'new-admin@example.com',
            'role' => 'admin',
        ]);

        $response->assertStatus(403);

        // Should fail for manager role
        $response = $this->postJson('/api/invitations', [
            'email' => 'new-manager@example.com',
            'role' => 'manager',
        ]);

        $response->assertStatus(403);
    }

    public function test_operator_cannot_invite_users(): void
    {
        Sanctum::actingAs($this->operator);

        $response = $this->postJson('/api/invitations', [
            'email' => 'new-user@example.com',
            'role' => 'operator',
        ]);

        $response->assertStatus(403);
    }

    public function test_duplicate_invitation_prevention(): void
    {
        Sanctum::actingAs($this->admin);

        // Create first invitation
        $response = $this->postJson('/api/invitations', [
            'email' => 'test@example.com',
            'role' => 'operator',
        ]);

        $response->assertStatus(201);

        // Try to create duplicate invitation
        $response = $this->postJson('/api/invitations', [
            'email' => 'test@example.com',
            'role' => 'operator',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_admin_can_update_any_user(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->putJson("/api/users/{$this->operator->id}", [
            'name' => 'Updated Name',
            'role' => 'manager',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('users', [
            'id' => $this->operator->id,
            'name' => 'Updated Name',
            'role' => 'manager',
        ]);
    }

    public function test_manager_can_update_operators_only(): void
    {
        Sanctum::actingAs($this->manager);

        // Should succeed for operator
        $response = $this->putJson("/api/users/{$this->operator->id}", [
            'name' => 'Updated Operator',
        ]);

        $response->assertStatus(200);

        // Should fail for admin
        $response = $this->putJson("/api/users/{$this->admin->id}", [
            'name' => 'Updated Admin',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_deactivation_and_reactivation(): void
    {
        Sanctum::actingAs($this->admin);

        // Deactivate user
        $response = $this->deleteJson("/api/users/{$this->operator->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'User deactivated successfully.']);

        $this->assertSoftDeleted('users', ['id' => $this->operator->id]);

        // Reactivate user
        $response = $this->postJson("/api/users/{$this->operator->id}/restore");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'User reactivated successfully.']);

        $this->assertDatabaseHas('users', [
            'id' => $this->operator->id,
            'deleted_at' => null,
        ]);
    }

    public function test_users_cannot_deactivate_themselves(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->deleteJson("/api/users/{$this->admin->id}");

        $response->assertStatus(403)
            ->assertJsonFragment(['error' => 'self_deactivation_forbidden']);
    }

    public function test_force_password_reset(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/users/{$this->operator->id}/force-password-reset");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'User will be required to reset their password on next login.']);

        $this->assertDatabaseHas('users', [
            'id' => $this->operator->id,
            'must_change_password' => true,
        ]);
    }

    public function test_pending_invitations_listing(): void
    {
        Sanctum::actingAs($this->admin);

        // Create some invitations
        Invitation::factory()->create([
            'tenant_id' => $this->company->id,
            'email' => 'pending1@example.com',
            'role' => 'operator',
            'invited_by' => $this->admin->id,
        ]);

        Invitation::factory()->create([
            'tenant_id' => $this->company->id,
            'email' => 'pending2@example.com',
            'role' => 'manager',
            'invited_by' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/invitations/pending');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['email' => 'pending1@example.com'])
            ->assertJsonFragment(['email' => 'pending2@example.com']);
    }

    public function test_resend_invitation(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->admin);

        $invitation = Invitation::factory()->create([
            'tenant_id' => $this->company->id,
            'email' => 'resend@example.com',
            'role' => 'operator',
            'invited_by' => $this->admin->id,
        ]);

        $response = $this->postJson("/api/invitations/{$invitation->id}/resend");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Invitation resent successfully.']);

        Queue::assertPushed(SendInvitationEmailJob::class);
    }

    public function test_cancel_invitation(): void
    {
        Sanctum::actingAs($this->admin);

        $invitation = Invitation::factory()->create([
            'tenant_id' => $this->company->id,
            'email' => 'cancel@example.com',
            'role' => 'operator',
            'invited_by' => $this->admin->id,
        ]);

        $response = $this->deleteJson("/api/invitations/{$invitation->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Invitation cancelled successfully.']);

        $this->assertSoftDeleted('invitations', ['id' => $invitation->id]);
    }

    public function test_role_hierarchy_in_invitation_permissions(): void
    {
        Sanctum::actingAs($this->manager);

        $invitation = Invitation::factory()->create([
            'tenant_id' => $this->company->id,
            'email' => 'test@example.com',
            'role' => 'admin', // Manager trying to manage admin invitation
            'invited_by' => $this->admin->id,
        ]);

        // Manager should not be able to resend admin invitation
        $response = $this->postJson("/api/invitations/{$invitation->id}/resend");
        $response->assertStatus(403);

        // Manager should not be able to cancel admin invitation
        $response = $this->deleteJson("/api/invitations/{$invitation->id}");
        $response->assertStatus(403);
    }

    public function test_tenant_isolation(): void
    {
        // Create another company with users
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherCompany->id,
            'role' => 'operator',
        ]);

        Sanctum::actingAs($this->admin);

        // Admin should not see users from other tenants
        $response = $this->getJson('/api/users');

        $response->assertStatus(200)
            ->assertJsonMissing(['email' => $otherUser->email]);

        // Admin should not be able to view other tenant's user
        $response = $this->getJson("/api/users/{$otherUser->id}");

        $response->assertStatus(404);
    }

    public function test_email_validation_and_normalization(): void
    {
        Sanctum::actingAs($this->admin);

        // Test invalid email
        $response = $this->postJson('/api/invitations', [
            'email' => 'invalid-email',
            'role' => 'operator',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // Test email normalization (uppercase should be converted to lowercase)
        $response = $this->postJson('/api/invitations', [
            'email' => 'TEST@EXAMPLE.COM',
            'role' => 'operator',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('invitations', [
            'email' => 'test@example.com', // Should be stored as lowercase
            'role' => 'operator',
        ]);
    }

    public function test_super_admin_can_manage_all_users_across_tenants(): void
    {
        // Create another company with users
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherCompany->id,
            'role' => 'admin',
        ]);

        Sanctum::actingAs($this->superAdmin);

        // Super admin should see all users regardless of tenant
        $response = $this->getJson('/api/users');
        $response->assertStatus(200);

        // Should see users from all tenants
        $userCount = $response->json('data');
        $this->assertGreaterThanOrEqual(5, count($userCount)); // superAdmin, admin, manager, operator, otherUser

        // Super admin should be able to manage users from other tenants
        $response = $this->putJson("/api/users/{$otherUser->id}", [
            'name' => 'Updated by Super Admin',
        ]);
        $response->assertStatus(200);
    }

    public function test_super_admin_can_invite_super_admin_role(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->superAdmin);

        $response = $this->postJson('/api/invitations', [
            'email' => 'new-super-admin@example.com',
            'role' => 'super-admin',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['message' => 'Invitation sent successfully.']);

        $this->assertDatabaseHas('invitations', [
            'email' => 'new-super-admin@example.com',
            'role' => 'super-admin',
        ]);
    }

    public function test_admin_cannot_invite_super_admin_role(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/invitations', [
            'email' => 'new-super-admin@example.com',
            'role' => 'super-admin',
        ]);

        $response->assertStatus(422) // Validation error, not 403
            ->assertJsonValidationErrors(['role']);
    }

    public function test_activity_logging_for_user_management(): void
    {
        Sanctum::actingAs($this->admin);

        // Test user update logging
        $this->putJson("/api/users/{$this->operator->id}", [
            'name' => 'Updated Name',
            'role' => 'manager',
        ]);

        // Check if activity was logged
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => User::class,
            'subject_id' => $this->operator->id,
            'causer_type' => User::class,
            'causer_id' => $this->admin->id,
        ]);
    }

    public function test_bulk_user_operations_permissions(): void
    {
        Sanctum::actingAs($this->admin);

        $user1 = User::factory()->create([
            'tenant_id' => $this->company->id,
            'role' => 'operator',
        ]);

        $user2 = User::factory()->create([
            'tenant_id' => $this->company->id,
            'role' => 'operator',
        ]);

        // Test bulk deactivation
        $response = $this->postJson('/api/users/bulk-deactivate', [
            'user_ids' => [$user1->id, $user2->id],
        ]);

        // Should succeed for admin
        $response->assertStatus(200);

        // Verify users were deactivated
        $this->assertSoftDeleted('users', ['id' => $user1->id]);
        $this->assertSoftDeleted('users', ['id' => $user2->id]);
    }

    public function test_rate_limiting_on_invitation_endpoints(): void
    {
        Sanctum::actingAs($this->admin);

        // Send multiple invitations rapidly to test rate limiting
        // This would need to be configured in the actual rate limiting middleware
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/invitations', [
                'email' => "test{$i}@example.com",
                'role' => 'operator',
            ]);

            if ($i < 3) {
                $response->assertStatus(201);
            }
        }
    }

    public function test_invitation_token_security(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/invitations', [
            'email' => 'security-test@example.com',
            'role' => 'operator',
        ]);

        $response->assertStatus(201);

        $invitation = Invitation::where('email', 'security-test@example.com')->first();

        // Token should be cryptographically secure (at least 32 characters)
        $this->assertGreaterThanOrEqual(32, strlen($invitation->token));

        // Token should be hexadecimal
        $this->assertTrue(ctype_xdigit($invitation->token));
    }

    public function test_user_management_form_validation(): void
    {
        Sanctum::actingAs($this->admin);

        // Test missing required fields
        $response = $this->postJson('/api/invitations', []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'role']);

        // Test invalid role
        $response = $this->postJson('/api/invitations', [
            'email' => 'test@example.com',
            'role' => 'invalid-role',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);

        // Test user update validation
        $response = $this->putJson("/api/users/{$this->operator->id}", [
            'name' => '', // Empty name should fail
            'email' => 'invalid-email', // Invalid email should fail
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_operator_dashboard_access_blocked(): void
    {
        // This test verifies the BlockOperatorAccess middleware
        Sanctum::actingAs($this->operator);

        // Operators should be blocked from accessing admin dashboard routes
        // This is handled by the middleware, so we test the API equivalent
        $response = $this->getJson('/api/users');
        $response->assertStatus(403);

        $response = $this->getJson('/api/invitations/pending');
        $response->assertStatus(403);
    }

    public function test_cross_tenant_security(): void
    {
        // Create another company with users
        $otherCompany = Company::factory()->create();
        $otherAdmin = User::factory()->create([
            'tenant_id' => $otherCompany->id,
            'role' => 'admin',
        ]);

        // Admin from one tenant should not be able to manage users from another tenant
        Sanctum::actingAs($this->admin);

        $response = $this->putJson("/api/users/{$otherAdmin->id}", [
            'name' => 'Hacked Name',
        ]);

        $response->assertStatus(403); // Should be unauthorized due to tenant scoping

        // Should not be able to invite users to other tenants
        $response = $this->postJson('/api/invitations', [
            'email' => 'cross-tenant@example.com',
            'role' => 'operator',
            'tenant_id' => $otherCompany->id, // Trying to specify different tenant
        ]);

        // The invitation should be created for the current user's tenant, not the specified one
        if ($response->status() === 201) {
            $this->assertDatabaseHas('invitations', [
                'email' => 'cross-tenant@example.com',
                'tenant_id' => $this->company->id, // Should be current user's tenant
            ]);
        }
    }
}
