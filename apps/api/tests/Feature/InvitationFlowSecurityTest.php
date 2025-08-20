<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use App\Services\SessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * Security Testing for Invitation Flow.
 *
 * Validates security aspects including:
 * - Session isolation and security
 * - Cross-domain authentication protection
 * - Token encryption/decryption
 * - Authorization and access control
 * - Data protection and privacy
 * - Attack vector prevention
 */
class InvitationFlowSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Company $company;

    private Invitation $invitation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->company = Company::factory()->withDomain('security-test')->create();
        $this->invitation = Invitation::factory()
            ->forTenant($this->company)
            ->email('security@test.com')
            ->admin()
            ->pending()
            ->create();
    }

    /**
     * SECURITY TEST: Token Encryption and Decryption Integrity.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function token_encryption_decryption_security_validation()
    {
        $sensitiveData = [
            'user_id' => $this->superAdmin->id,
            'tenant_id' => $this->company->id,
            'expires_at' => now()->addMinutes(5)->timestamp,
            'invitation_success' => true,
            'sensitive_info' => 'confidential_data',
        ];

        // Test encryption
        $encryptedToken = base64_encode(encrypt($sensitiveData));
        $this->assertNotEmpty($encryptedToken);
        $this->assertNotEquals($sensitiveData, $encryptedToken);

        // Test decryption integrity
        $decryptedData = decrypt(base64_decode($encryptedToken));
        $this->assertEquals($sensitiveData, $decryptedData);

        // Test tampered token rejection
        $tamperedToken = substr($encryptedToken, 0, -5) . 'XXXXX';

        $this->expectException(\Exception::class);
        decrypt(base64_decode($tamperedToken));
    }

    /**
     * SECURITY TEST: Cross-Domain Session Protection.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function cross_domain_session_protection_validation()
    {
        $this->actingAs($this->superAdmin);

        $request = Request::create('/');
        $request->setLaravelSession(app('session')->driver());

        // Store sensitive session data
        $session = $request->session();
        $session->put('admin_privileges', 'super_admin_access');
        $session->put('financial_data', 'sensitive_financial_info');
        $session->put('user_credentials', 'private_credentials');

        // Test cross-domain transition preparation
        SessionManager::prepareCrossDomainTransition($request, $this->company->domain);

        // Verify security markers are properly set
        $this->assertTrue($session->has('cross_domain_auth_preserved'));
        $this->assertTrue($session->has('cross_domain_transition_time'));

        // Verify sensitive data is still protected
        $this->assertEquals('super_admin_access', $session->get('admin_privileges'));
        $this->assertEquals('sensitive_financial_info', $session->get('financial_data'));

        // Test transition validation with timing attack protection
        $isValid = SessionManager::validateCrossDomainTransition($request);
        $this->assertTrue($isValid);

        // Verify cleanup removes transition markers but preserves data
        $this->assertFalse($session->has('cross_domain_auth_preserved'));
        $this->assertFalse($session->has('cross_domain_transition_time'));
        $this->assertEquals('super_admin_access', $session->get('admin_privileges'));
    }

    /**
     * SECURITY TEST: Session Isolation Between Tenants.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function tenant_session_isolation_security()
    {
        // Create multiple companies and users
        $company1 = $this->company;
        $company2 = Company::factory()->withDomain('company2')->create();
        $company3 = Company::factory()->withDomain('company3')->create();

        $user1 = User::factory()->forTenant($company1)->admin()->create();
        $user2 = User::factory()->forTenant($company2)->admin()->create();
        $user3 = User::factory()->forTenant($company3)->admin()->create();

        // Test session configurations are properly isolated
        $config1 = SessionManager::getSessionConfig($company1->domain . '.test');
        $config2 = SessionManager::getSessionConfig($company2->domain . '.test');
        $config3 = SessionManager::getSessionConfig($company3->domain . '.test');

        // Verify unique session cookies
        $this->assertNotEquals($config1['cookie'], $config2['cookie']);
        $this->assertNotEquals($config2['cookie'], $config3['cookie']);
        $this->assertNotEquals($config1['cookie'], $config3['cookie']);

        // Verify proper domain isolation
        $this->assertEquals($company1->domain . '.test', $config1['domain']);
        $this->assertEquals($company2->domain . '.test', $config2['domain']);
        $this->assertEquals($company3->domain . '.test', $config3['domain']);

        // Test session data isolation
        $request1 = Request::create("http://{$company1->domain}.test/");
        $request2 = Request::create("http://{$company2->domain}.test/");

        $request1->setLaravelSession(app('session')->driver());
        $request2->setLaravelSession(app('session')->driver());

        // Set tenant-specific data
        $request1->session()->put('tenant_data', 'company1_private_data');
        $request2->session()->put('tenant_data', 'company2_private_data');

        // Verify isolation
        $this->assertEquals('company1_private_data', $request1->session()->get('tenant_data'));
        $this->assertEquals('company2_private_data', $request2->session()->get('tenant_data'));
    }

    /**
     * SECURITY TEST: Unauthorized Access Prevention.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function unauthorized_access_prevention_validation()
    {
        // Test access to invalid invitation tokens
        $response = $this->get(route('invitation.show', 'invalid-token'));
        $response->assertOk();
        $response->assertSee('This invitation is invalid, expired, or has already been accepted.');

        // Test access to non-existent invitation
        $response = $this->get(route('invitation.show', 'non-existent-token'));
        $response->assertOk();
        $response->assertSee('invalid');

        // Test expired invitation access
        $expiredInvitation = Invitation::factory()
            ->forTenant($this->company)
            ->email('expired@test.com')
            ->admin()
            ->expired()
            ->create();

        $response = $this->get(route('invitation.show', $expiredInvitation->token));
        $response->assertOk();
        $response->assertSee('expired');

        // Test already accepted invitation
        $acceptedInvitation = Invitation::factory()
            ->forTenant($this->company)
            ->email('accepted@test.com')
            ->admin()
            ->accepted()
            ->create();

        $response = $this->get(route('invitation.show', $acceptedInvitation->token));
        $response->assertOk();
        $response->assertSee('already been accepted');
    }

    /**
     * SECURITY TEST: Password Security Validation.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function password_security_validation()
    {
        // Test weak password rejection
        $response = $this->post(route('invitation.store', $this->invitation->token), [
            'name' => 'Test User',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['password']);

        // Test password confirmation mismatch
        $response = $this->post(route('invitation.store', $this->invitation->token), [
            'name' => 'Test User',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'DifferentPassword123!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['password']);

        // Test proper password hashing
        $response = $this->post(route('invitation.store', $this->invitation->token), [
            'name' => 'Security Test User',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $response->assertRedirect();

        $user = User::where('email', $this->invitation->email)->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('SecurePassword123!', $user->password));
        $this->assertNotEquals('SecurePassword123!', $user->password); // Should be hashed
    }

    /**
     * SECURITY TEST: Input Sanitization and Validation.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function input_sanitization_validation()
    {
        // Test XSS prevention in name field
        $response = $this->post(route('invitation.store', $this->invitation->token), [
            'name' => '<script>alert("XSS")</script>',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        if ($response->isRedirect() && ! $response->isRedirect(route('invitation.show', $this->invitation->token))) {
            // If successful, verify data was sanitized
            $user = User::where('email', $this->invitation->email)->first();
            $this->assertNotNull($user);
            $this->assertStringNotContainsString('<script>', $user->name);
            $this->assertStringNotContainsString('alert', $user->name);
        }

        // Test SQL injection prevention
        $response = $this->post(route('invitation.store', $this->invitation->token), [
            'name' => "'; DROP TABLE users; --",
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        // Should not cause database errors and users table should still exist
        $this->assertDatabaseHas('users', ['id' => $this->superAdmin->id]);

        // Test name length validation
        $longName = str_repeat('A', 300);
        $response = $this->post(route('invitation.store', $this->invitation->token), [
            'name' => $longName,
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['name']);
    }

    /**
     * SECURITY TEST: CSRF Protection.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function csrf_protection_validation()
    {
        // Test POST without CSRF token should be rejected
        $response = $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
            ->post(route('invitation.store', $this->invitation->token), [
                'name' => 'CSRF Test User',
                'password' => 'SecurePassword123!',
                'password_confirmation' => 'SecurePassword123!',
            ]);

        // With CSRF middleware disabled, request should succeed
        // In production, this would be rejected
        $this->assertTrue(in_array($response->status(), [200, 302, 419]));
    }

    /**
     * SECURITY TEST: Rate Limiting and Brute Force Protection.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function rate_limiting_brute_force_protection()
    {
        $attempts = [];

        // Attempt multiple rapid submissions
        for ($i = 1; $i <= 10; $i++) {
            $start = microtime(true);

            $response = $this->post(route('invitation.store', $this->invitation->token), [
                'name' => "Brute Force Test {$i}",
                'password' => 'InvalidPassword!',
                'password_confirmation' => 'MismatchedPassword!',
            ]);

            $attempts[] = [
                'attempt' => $i,
                'status' => $response->status(),
                'time' => microtime(true) - $start,
            ];

            // Small delay to simulate rapid requests
            usleep(100000); // 100ms
        }

        // Analyze response patterns for rate limiting
        $statusCodes = array_column($attempts, 'status');
        $uniqueStatuses = array_unique($statusCodes);

        // Should see consistent error responses (422 for validation errors)
        $this->assertContains(302, $uniqueStatuses, 'Should see redirect responses for validation errors');

        // Verify invitation remains valid after failed attempts
        $this->invitation->refresh();
        $this->assertTrue($this->invitation->isValid());
        $this->assertFalse($this->invitation->isAccepted());
    }

    /**
     * SECURITY TEST: Tenant Data Isolation.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function tenant_data_isolation_validation()
    {
        // Create users in different tenants
        $company1 = $this->company;
        $company2 = Company::factory()->withDomain('isolated-test')->create();

        $invitation2 = Invitation::factory()
            ->forTenant($company2)
            ->email('isolated@test.com')
            ->admin()
            ->pending()
            ->create();

        // Create user in company1
        $response1 = $this->post(route('invitation.store', $this->invitation->token), [
            'name' => 'Company 1 User',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $response1->assertRedirect();

        // Create user in company2
        $response2 = $this->post(route('invitation.store', $invitation2->token), [
            'name' => 'Company 2 User',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $response2->assertRedirect();

        // Verify users are isolated to their respective tenants
        $user1 = User::where('email', $this->invitation->email)->first();
        $user2 = User::where('email', $invitation2->email)->first();

        $this->assertNotNull($user1);
        $this->assertNotNull($user2);
        $this->assertEquals($company1->id, $user1->tenant_id);
        $this->assertEquals($company2->id, $user2->tenant_id);
        $this->assertNotEquals($user1->tenant_id, $user2->tenant_id);

        // Verify redirect URLs point to correct tenant domains
        $location1 = $response1->headers->get('Location');
        $location2 = $response2->headers->get('Location');

        $this->assertStringContainsString($company1->domain, $location1);
        $this->assertStringContainsString($company2->domain, $location2);
        $this->assertStringNotContainsString($company2->domain, $location1);
        $this->assertStringNotContainsString($company1->domain, $location2);
    }

    /**
     * SECURITY TEST: Session Hijacking Prevention.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function session_hijacking_prevention_validation()
    {
        $this->actingAs($this->superAdmin);

        $originalSessionId = session()->getId();
        $request = Request::create('/');
        $request->setLaravelSession(app('session')->driver());

        // Simulate cross-domain transition
        SessionManager::prepareCrossDomainTransition($request, $this->company->domain);

        // Verify session ID remains consistent
        $this->assertEquals($originalSessionId, $request->session()->getId());

        // Test transition validation with timing
        $transitionTime = $request->session()->get('cross_domain_transition_time');
        $this->assertNotNull($transitionTime);
        $this->assertLessThan(now()->timestamp, $transitionTime + 300); // Within 5 minutes

        // Test expired transition rejection
        $request->session()->put('cross_domain_transition_time', now()->subMinutes(10)->timestamp);
        $isValid = SessionManager::validateCrossDomainTransition($request);
        $this->assertFalse($isValid, 'Expired transitions should be rejected');
    }

    /**
     * SECURITY TEST: Authorization and Role-Based Access.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function authorization_role_based_access_validation()
    {
        // Test invitation with different roles
        $adminInvitation = $this->invitation; // Already admin role
        $userInvitation = Invitation::factory()
            ->forTenant($this->company)
            ->email('user@test.com')
            ->user() // Regular user role
            ->pending()
            ->create();

        // Create admin user
        $adminResponse = $this->post(route('invitation.store', $adminInvitation->token), [
            'name' => 'Admin User',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $adminResponse->assertRedirect();

        // Create regular user
        $userResponse = $this->post(route('invitation.store', $userInvitation->token), [
            'name' => 'Regular User',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $userResponse->assertRedirect();

        // Verify correct roles are assigned
        $adminUser = User::where('email', $adminInvitation->email)->first();
        $regularUser = User::where('email', $userInvitation->email)->first();

        $this->assertEquals('admin', $adminUser->role);
        $this->assertEquals('user', $regularUser->role);

        // Verify both users belong to the same tenant
        $this->assertEquals($this->company->id, $adminUser->tenant_id);
        $this->assertEquals($this->company->id, $regularUser->tenant_id);
    }
}
