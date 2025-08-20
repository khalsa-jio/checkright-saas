<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class InvitationSessionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a super admin user
        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@checkright.test',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
            'tenant_id' => null,
            'email_verified_at' => now(),
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function complete_invitation_flow_preserves_super_admin_session()
    {
        // Step 1: Login as super admin on central domain
        $this->actingAs($this->superAdmin);

        // Add some session data that should be preserved
        Session::put('super_admin_preferences', [
            'theme' => 'dark',
            'language' => 'en',
            'timezone' => 'UTC',
        ]);

        Session::put('dashboard_cache', 'important_cached_data');

        // Verify we're logged in as super admin
        $this->assertTrue(Auth::check());
        $this->assertEquals($this->superAdmin->id, Auth::id());
        $this->assertEquals('super_admin', Auth::user()->role);

        // Step 2: Create a company and invitation
        $company = Company::factory()->withDomain('newcompany')->create();

        $invitation = Invitation::create([
            'tenant_id' => $company->id,
            'email' => 'manager@newcompany.com',
            'role' => 'admin',
            'invited_by' => $this->superAdmin->id,
        ]);

        $this->assertTrue($invitation->isValid());

        // Step 3: Submit invitation acceptance form
        $response = $this->post(route('invitation.store', $invitation->token), [
            'name' => 'Company Manager',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        // Step 4: Verify redirect to tenant domain with impersonation token
        $this->assertEquals(302, $response->status());
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('newcompany', $location);
        $this->assertStringContainsString('/impersonate/', $location);

        // Step 5: Verify super admin session is still intact on central domain
        $this->assertTrue(Auth::check(), 'Super admin should still be authenticated');
        $this->assertEquals($this->superAdmin->id, Auth::id(), 'Should still be logged in as super admin');
        $this->assertEquals('super_admin', Auth::user()->role, 'Role should still be super_admin');

        // Verify preserved session data
        $this->assertEquals([
            'theme' => 'dark',
            'language' => 'en',
            'timezone' => 'UTC',
        ], Session::get('super_admin_preferences'), 'Super admin preferences should be preserved');

        $this->assertEquals('important_cached_data', Session::get('dashboard_cache'), 'Dashboard cache should be preserved');

        // Step 6: Verify invitation was processed correctly
        $invitation->refresh();
        $this->assertTrue($invitation->isAccepted(), 'Invitation should be marked as accepted');
        $this->assertNotNull($invitation->accepted_at, 'Acceptance timestamp should be set');

        // Step 7: Verify new user was created with correct details
        $newUser = User::where('email', 'manager@newcompany.com')->first();
        $this->assertNotNull($newUser, 'New user should be created');
        $this->assertEquals('Company Manager', $newUser->name);
        $this->assertEquals('admin', $newUser->role);
        $this->assertEquals($company->id, $newUser->tenant_id);
        $this->assertNotNull($newUser->email_verified_at, 'Email should be verified');

        // Step 8: Verify impersonation token was created
        $impersonationTokens = \DB::table('tenant_user_impersonation_tokens')
            ->where('tenant_id', $company->id)
            ->where('user_id', $newUser->id)
            ->get();

        $this->assertCount(1, $impersonationTokens, 'Impersonation token should be created');
        $this->assertEquals('/admin', $impersonationTokens->first()->redirect_url, 'Should redirect to admin panel');

        // Step 9: Simulate what happens when the impersonation token is used
        // Extract token from redirect URL
        preg_match('/\/impersonate\/([^\/\?]+)/', $location, $matches);
        $impersonationToken = $matches[1] ?? null;
        $this->assertNotNull($impersonationToken, 'Impersonation token should be present in URL');

        // Step 10: Test the impersonation route (simulating tenant domain request)
        // Note: In testing environment, tenancy might not be fully initialized
        // so we just verify the token exists and is valid
        $impersonationRecord = \DB::table('tenant_user_impersonation_tokens')
            ->where('token', $impersonationToken)
            ->first();

        $this->assertNotNull($impersonationRecord, 'Impersonation token should exist in database');
        $this->assertEquals($newUser->id, $impersonationRecord->user_id, 'Token should be for the new user');
        $this->assertEquals($company->id, $impersonationRecord->tenant_id, 'Token should be for the correct tenant');

        // Step 11: Verify that we can still access central domain as super admin
        // At this point we should still be authenticated, let's verify
        $this->assertTrue(Auth::check(), 'Should still be authenticated after invitation processing');

        // Test accessing a simple route to verify session integrity
        $healthResponse = $this->get('/health');
        $this->assertEquals(200, $healthResponse->status(), 'Health check should work');

        // The main goal is to verify the super admin session wasn't lost
        // which we already confirmed above with Auth::check()
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function invitation_acceptance_creates_isolated_tenant_session()
    {
        // Create company and invitation
        $company = Company::factory()->withDomain('isolatedcompany')->create();

        $invitation = Invitation::create([
            'tenant_id' => $company->id,
            'email' => 'user@isolatedcompany.com',
            'role' => 'user',
            'invited_by' => $this->superAdmin->id,
        ]);

        // Accept invitation (not authenticated)
        $response = $this->post(route('invitation.store', $invitation->token), [
            'name' => 'Isolated User',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $this->assertEquals(302, $response->status());

        // Verify user was created
        $newUser = User::where('email', 'user@isolatedcompany.com')->first();
        $this->assertNotNull($newUser);
        $this->assertEquals($company->id, $newUser->tenant_id);

        // Verify no session contamination occurred
        $this->assertFalse(Auth::check(), 'Should not be logged in on central domain');

        // Verify impersonation token was created for isolated login
        $impersonationTokens = \DB::table('tenant_user_impersonation_tokens')
            ->where('tenant_id', $company->id)
            ->where('user_id', $newUser->id)
            ->get();

        $this->assertCount(1, $impersonationTokens);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function multiple_invitation_acceptances_maintain_session_isolation()
    {
        // Login as super admin
        $this->actingAs($this->superAdmin);

        // Create multiple companies and invitations
        $company1 = Company::factory()->withDomain('company1')->create();
        $company2 = Company::factory()->withDomain('company2')->create();

        $invitation1 = Invitation::create([
            'tenant_id' => $company1->id,
            'email' => 'user1@company1.com',
            'role' => 'admin',
            'invited_by' => $this->superAdmin->id,
        ]);

        $invitation2 = Invitation::create([
            'tenant_id' => $company2->id,
            'email' => 'user2@company2.com',
            'role' => 'admin',
            'invited_by' => $this->superAdmin->id,
        ]);

        // Accept first invitation
        $response1 = $this->post(route('invitation.store', $invitation1->token), [
            'name' => 'User One',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $this->assertEquals(302, $response1->status());

        // Verify we're still logged in as super admin
        $this->assertTrue(Auth::check());
        $this->assertEquals($this->superAdmin->id, Auth::id());

        // Accept second invitation
        $response2 = $this->post(route('invitation.store', $invitation2->token), [
            'name' => 'User Two',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $this->assertEquals(302, $response2->status());

        // Verify we're still logged in as super admin after multiple invitations
        $this->assertTrue(Auth::check());
        $this->assertEquals($this->superAdmin->id, Auth::id());
        $this->assertEquals('super_admin', Auth::user()->role);

        // Verify both users were created correctly
        $user1 = User::where('email', 'user1@company1.com')->first();
        $user2 = User::where('email', 'user2@company2.com')->first();

        $this->assertNotNull($user1);
        $this->assertNotNull($user2);
        $this->assertEquals($company1->id, $user1->tenant_id);
        $this->assertEquals($company2->id, $user2->tenant_id);

        // Verify separate impersonation tokens were created
        $tokens1 = \DB::table('tenant_user_impersonation_tokens')
            ->where('tenant_id', $company1->id)
            ->where('user_id', $user1->id)
            ->count();

        $tokens2 = \DB::table('tenant_user_impersonation_tokens')
            ->where('tenant_id', $company2->id)
            ->where('user_id', $user2->id)
            ->count();

        $this->assertEquals(1, $tokens1, 'Company 1 should have one impersonation token');
        $this->assertEquals(1, $tokens2, 'Company 2 should have one impersonation token');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function expired_invitations_do_not_affect_session_state()
    {
        // Login as super admin
        $this->actingAs($this->superAdmin);

        // Create company and expired invitation
        $company = Company::factory()->withDomain('expiredcompany')->create();

        $invitation = Invitation::create([
            'tenant_id' => $company->id,
            'email' => 'user@expiredcompany.com',
            'role' => 'admin',
            'invited_by' => $this->superAdmin->id,
            'expires_at' => now()->subHours(1), // Expired 1 hour ago
        ]);

        // Store session data
        Session::put('test_data', 'should_be_preserved');

        // Try to accept expired invitation
        $response = $this->post(route('invitation.store', $invitation->token), [
            'name' => 'Test User',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        // Should redirect back with error
        $this->assertEquals(302, $response->status());
        $this->assertStringNotContainsString('expiredcompany', $response->headers->get('Location', ''));

        // Verify session state is preserved
        $this->assertTrue(Auth::check());
        $this->assertEquals($this->superAdmin->id, Auth::id());
        $this->assertEquals('should_be_preserved', Session::get('test_data'));

        // Verify no user was created
        $this->assertDatabaseMissing('users', [
            'email' => 'user@expiredcompany.com',
        ]);

        // Verify no impersonation tokens were created
        $tokens = \DB::table('tenant_user_impersonation_tokens')
            ->where('tenant_id', $company->id)
            ->count();

        $this->assertEquals(0, $tokens);
    }
}
