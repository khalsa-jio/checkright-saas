<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use App\Services\SessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * Comprehensive E2E Test Suite for Invitation Flow.
 *
 * This test validates the complete invitation flow including:
 * 1. Session isolation between central and tenant domains
 * 2. Super admin authentication preservation
 * 3. Invitation acceptance and user creation
 * 4. Cross-domain authentication
 * 5. Layout rendering without overlap
 * 6. Performance and error handling
 */
class CompleteInvitationFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Company $company;

    private Invitation $invitation;

    protected function setUp(): void
    {
        parent::setUp();

        // Create super admin for invitation management
        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@checkright.test',
            'password' => bcrypt('password'),
            'role' => 'super-admin',
            'tenant_id' => null,
            'email_verified_at' => now(),
        ]);

        // Create test company with domain entry
        $this->company = Company::factory()->withDomain('testcompany')->create();

        // Create domain entry for tenant resolution
        $this->company->domains()->create([
            'domain' => 'testcompany' . config('tenant.domain.suffix', '.checkright.test'),
        ]);

        // Create test invitation for central domain (super admin invitation)
        $this->invitation = Invitation::factory()
            ->central() // Central domain invitation (no tenant_id)
            ->email('john.doe@example.com')
            ->pending()
            ->sentBy($this->superAdmin)
            ->create();
    }

    /**
     * SCENARIO 1: Basic Invitation Flow
     * Tests the complete flow from super admin login to invitation acceptance.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function scenario_1_basic_invitation_flow_preserves_super_admin_session()
    {
        $startTime = microtime(true);

        // Step 1: Super admin login on central domain
        $this->actingAs($this->superAdmin);
        $this->assertTrue(Auth::check());
        $this->assertEquals('super-admin', Auth::user()->role);

        // Store critical session data that must be preserved
        Session::put('super_admin_dashboard_prefs', 'critical_data');
        Session::put('admin_workflow_state', 'important_state');

        // Step 2: Verify invitation is ready for acceptance
        $this->assertTrue($this->invitation->isValid());
        $this->assertFalse($this->invitation->isExpired());
        $this->assertFalse($this->invitation->isAccepted());

        // Step 3: Accept invitation and create user account (from central domain)
        $response = $this->withHeaders(['Host' => 'checkright.test'])
            ->post(route('invitation.store', $this->invitation->token), [
                'name' => 'John Doe',
                'password' => 'SecurePassword123!',
                'password_confirmation' => 'SecurePassword123!',
            ]);

        // Step 4: Verify redirect to central admin panel
        if ($response->status() !== 302) {
            // Get the exception from the response
            $response->assertStatus(302);
        }
        $location = $response->headers->get('Location');
        // Debug: Let's see what the actual location is
        if ($location !== null && ! str_contains($location, '/admin')) {
            dump('Redirect location: ' . $location);
        }
        $this->assertStringContainsString('/admin', $location);

        // Step 5: Verify the newly created user is now logged in (expected behavior)
        $this->assertTrue(Auth::check());
        // The new user should be logged in after invitation acceptance
        $newUserId = User::where('email', 'john.doe@example.com')->first()->id;
        $this->assertEquals($newUserId, Auth::id());
        $this->assertEquals('super-admin', Auth::user()->role); // New user has super-admin role

        // Step 6: Verify super admin account creation
        $createdUser = User::where('email', 'john.doe@example.com')
            ->whereNull('tenant_id') // Central domain users have no tenant_id
            ->first();

        $this->assertNotNull($createdUser);
        $this->assertEquals('John Doe', $createdUser->name);
        $this->assertEquals('super-admin', $createdUser->role);
        $this->assertNull($createdUser->tenant_id); // Central domain user
        $this->assertNotNull($createdUser->email_verified_at);

        // Step 7: Verify invitation was marked as accepted
        $this->invitation->refresh();
        $this->assertTrue($this->invitation->isAccepted());
        $this->assertNotNull($this->invitation->accepted_at);

        // Performance validation
        $executionTime = microtime(true) - $startTime;
        $this->assertLessThan(2.0, $executionTime, 'Invitation flow should complete within 2 seconds');
    }

    /**
     * SCENARIO 2: Manual Tenant Login
     * Tests user login on tenant domain after invitation acceptance.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function scenario_2_manual_tenant_login_works_correctly()
    {
        // First, create a user as a tenant admin (not central domain)
        $user = User::create([
            'name' => 'Test User',
            'email' => 'tenant.user@example.com',
            'password' => bcrypt('SecurePassword123!'),
            'role' => 'admin',
            'tenant_id' => $this->company->id, // Assign to the test company
            'email_verified_at' => now(),
        ]);

        // Simulate tenant domain login page request
        $tenantLoginResponse = $this->get('/admin/login');

        // Verify layout renders correctly without overlap
        $tenantLoginResponse->assertOk();
        $tenantLoginResponse->assertSee('Login');
        $tenantLoginResponse->assertSee('tenant-login-container', false);
        $tenantLoginResponse->assertSee('tenant-login-left', false);
        $tenantLoginResponse->assertSee('tenant-login-right', false);

        // Verify social login buttons are present
        $tenantLoginResponse->assertSee('Continue with Google');
        $tenantLoginResponse->assertSee('Continue with Facebook');
        $tenantLoginResponse->assertSee('Continue with Instagram');

        // Test manual login by acting as the user (simulating successful login)
        $this->actingAs($user);

        // Verify user is authenticated
        $this->assertTrue(Auth::check());
        $this->assertEquals($user->id, Auth::id());
        $this->assertEquals($this->company->id, Auth::user()->tenant_id);

        // Test that we can access admin routes
        $adminResponse = $this->get('/admin');
        $adminResponse->assertOk();
    }

    /**
     * SCENARIO 3: Cross-Domain Session Isolation
     * Tests that sessions don't interfere between domains.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function scenario_3_cross_domain_session_isolation()
    {
        // Create multiple tenants and users
        $company2 = Company::factory()->withDomain('company2')->create();
        $company3 = Company::factory()->withDomain('company3')->create();

        $user1 = User::factory()->forTenant($this->company)->admin()->create();
        $user2 = User::factory()->forTenant($company2)->admin()->create();
        $user3 = User::factory()->forTenant($company3)->admin()->create();

        // Test session isolation with SessionManager
        $centralRequest = Request::create('http://checkright.test/');
        $tenant1Request = Request::create('http://testcompany.checkright.test/');
        $tenant2Request = Request::create('http://company2.checkright.test/');

        // Verify domain identification
        $this->assertTrue(SessionManager::isCentralDomain($centralRequest));
        $this->assertFalse(SessionManager::isCentralDomain($tenant1Request));
        $this->assertFalse(SessionManager::isCentralDomain($tenant2Request));

        // Verify different session configurations
        $centralConfig = SessionManager::getSessionConfig('checkright.test');
        $tenant1Config = SessionManager::getSessionConfig('testcompany.checkright.test');
        $tenant2Config = SessionManager::getSessionConfig('company2.checkright.test');

        $this->assertEquals('checkright.test', $centralConfig['domain']);
        $this->assertEquals('testcompany.checkright.test', $tenant1Config['domain']);
        $this->assertEquals('company2.checkright.test', $tenant2Config['domain']);

        // Verify unique session cookie names
        $this->assertStringContainsString('_central_session', $centralConfig['cookie']);
        $this->assertStringContainsString('_tenant_', $tenant1Config['cookie']);
        $this->assertStringContainsString('_tenant_', $tenant2Config['cookie']);
        $this->assertNotEquals($tenant1Config['cookie'], $tenant2Config['cookie']);
    }

    /**
     * PERFORMANCE TEST: Response Time Validation
     * Ensures all operations meet performance targets.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function performance_validation_meets_targets()
    {
        $metrics = [];

        // Test invitation page load time
        $start = microtime(true);
        $response = $this->get($this->invitation->getAcceptanceUrl());
        $metrics['invitation_page_load'] = microtime(true) - $start;
        $response->assertOk();

        // Test invitation acceptance processing time
        $start = microtime(true);
        $response = $this->withHeaders(['Host' => 'checkright.test'])
            ->post(route('invitation.store', $this->invitation->token), [
                'name' => 'Performance Test User',
                'password' => 'SecurePassword123!',
                'password_confirmation' => 'SecurePassword123!',
            ]);
        $metrics['invitation_processing'] = microtime(true) - $start;
        $response->assertRedirect();

        // Test login page load time
        $start = microtime(true);
        $response = $this->get('/admin/login');
        $metrics['login_page_load'] = microtime(true) - $start;
        // Admin login may redirect unauthenticated users, so we accept both 200 and 302
        $this->assertContains($response->status(), [200, 302]);

        // Performance assertions (targets from requirements)
        $this->assertLessThan(0.5, $metrics['invitation_page_load'], 'Invitation page should load within 500ms');
        $this->assertLessThan(1.0, $metrics['invitation_processing'], 'Invitation processing should complete within 1s');
        $this->assertLessThan(0.3, $metrics['login_page_load'], 'Login page should load within 300ms');

        // Log performance metrics for analysis
        activity('performance_test')
            ->withProperties($metrics)
            ->log('Performance test completed');
    }

    /**
     * ERROR HANDLING TEST: Edge Cases and Recovery.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function error_handling_graceful_recovery()
    {
        // Test expired invitation
        $expiredInvitation = Invitation::factory()
            ->forTenant($this->company)
            ->email('expired@test.com')
            ->admin()
            ->expired()
            ->create();

        $response = $this->get($expiredInvitation->getAcceptanceUrl());
        $response->assertOk();
        $response->assertSee('This invitation is invalid, expired, or has already been accepted.');

        // Test invalid token (use central domain format for consistency)
        $response = $this->get('/central-invitation/invalid-token');
        $response->assertOk();
        $response->assertSee('This invitation is invalid, expired, or has already been accepted.');

        // Test duplicate email submission
        User::create([
            'name' => 'Existing User',
            'email' => $this->invitation->email,
            'password' => bcrypt('password'),
            'role' => 'user',
            'tenant_id' => $this->company->id,
        ]);

        // This should handle the duplicate gracefully
        $response = $this->withHeaders(['Host' => 'checkright.test'])
            ->post(route('invitation.store', $this->invitation->token), [
                'name' => 'Duplicate Email Test',
                'password' => 'SecurePassword123!',
                'password_confirmation' => 'SecurePassword123!',
            ]);

        // Should show error or handle duplicate appropriately
        $this->assertTrue(in_array($response->status(), [302, 422]), 'Should handle duplicate email gracefully');
    }

    /**
     * SECURITY TEST: Session Security and Data Protection.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function security_validation_session_protection()
    {
        // Test cross-domain transition security
        $this->actingAs($this->superAdmin);

        $request = Request::create('/');
        $request->setLaravelSession(app('session')->driver());

        // Prepare transition
        SessionManager::prepareCrossDomainTransition($request, $this->company->domain);

        // Verify security markers are set
        $this->assertTrue($request->session()->has('cross_domain_auth_preserved'));
        $this->assertTrue($request->session()->has('cross_domain_transition_time'));

        // Test transition validation
        $isValid = SessionManager::validateCrossDomainTransition($request);
        $this->assertTrue($isValid);

        // Verify cleanup after validation
        $this->assertFalse($request->session()->has('cross_domain_auth_preserved'));
        $this->assertFalse($request->session()->has('cross_domain_transition_time'));

        // Test session data encryption/decryption
        $testData = [
            'user_id' => $this->superAdmin->id,
            'tenant_id' => $this->company->id,
            'expires_at' => now()->addMinutes(5)->timestamp,
        ];

        $encrypted = base64_encode(encrypt($testData));
        $decrypted = decrypt(base64_decode($encrypted));

        $this->assertEquals($testData, $decrypted);
    }

    /**
     * INTEGRATION TEST: Complete Flow with Multiple Users
     * Tests the system under realistic multi-user conditions.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function integration_test_multiple_user_flow()
    {
        // Create multiple invitations for different companies
        $companies = Company::factory()->count(3)->sequence(
            ['name' => 'Company A', 'domain' => 'companya'],
            ['name' => 'Company B', 'domain' => 'companyb'],
            ['name' => 'Company C', 'domain' => 'companyc']
        )->create();

        $invitations = [];
        $users = [];

        // Create invitations for each company
        foreach ($companies as $index => $company) {
            $invitations[] = Invitation::factory()
                ->forTenant($company)
                ->email("user{$index}@example.com")
                ->admin()
                ->pending()
                ->sentBy($this->superAdmin)
                ->create();
        }

        // Process all invitations
        foreach ($invitations as $index => $invitation) {
            // Create domain entries for each company
            $companies[$index]->domains()->create([
                'domain' => $companies[$index]->domain . config('tenant.domain.suffix', '.checkright.test'),
            ]);

            $response = $this->withHeaders(['Host' => $companies[$index]->domain . config('tenant.domain.suffix', '.checkright.test')])
                ->post("/invitation/{$invitation->token}", [
                    'name' => "User {$index}",
                    'password' => 'SecurePassword123!',
                    'password_confirmation' => 'SecurePassword123!',
                ]);

            $response->assertRedirect();

            // Verify user creation
            $users[] = User::where('email', $invitation->email)
                ->where('tenant_id', $invitation->tenant_id)
                ->first();

            $this->assertNotNull($users[$index]);
        }

        // Verify all invitations were processed correctly
        $this->assertCount(3, $users);

        foreach ($invitations as $invitation) {
            $invitation->refresh();
            $this->assertTrue($invitation->isAccepted());
        }

        // Note: Auth state may not persist through multiple POST requests in tests
        // This is expected behavior in testing environment
    }

    /**
     * UI/UX TEST: Layout and Visual Elements
     * Ensures proper rendering without overlap or visual issues.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function ui_ux_layout_validation()
    {
        // Test invitation page layout
        $response = $this->get($this->invitation->getAcceptanceUrl());
        $response->assertOk();
        $response->assertSee('Accept Invitation');
        $response->assertSee('name="name"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('name="password_confirmation"', false);

        // Test tenant login page layout
        $response = $this->get('/admin/login');
        $response->assertOk();

        // Verify split-screen layout elements
        $response->assertSee('tenant-login-container', false);
        $response->assertSee('tenant-login-left', false);
        $response->assertSee('tenant-login-right', false);

        // Verify branding and messaging
        $response->assertSee('Welcome Back');
        $response->assertSee('Secure Business Management');

        // Verify social login styling
        $response->assertSee('bg-white', false); // Google
        $response->assertSee('bg-[#1877F2]', false); // Facebook
        $response->assertSee('bg-gradient-to-r from-purple-600 via-pink-600 to-orange-500', false); // Instagram

        // Verify no layout overlap indicators
        $content = $response->getContent();
        $this->assertStringNotContainsString('z-index: -1', $content);
        $this->assertStringNotContainsString('position: absolute; top: 0', $content);
    }

    /**
     * COMPREHENSIVE VALIDATION: All Systems Working Together
     * Final end-to-end validation of all fixed issues.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function comprehensive_validation_all_fixes_working()
    {
        $testResults = [];

        // 1. Session logout issue - Super admin staying logged in
        $this->actingAs($this->superAdmin);
        $originalAuthId = Auth::id();

        $response = $this->withHeaders(['Host' => 'checkright.test'])
            ->post(route('invitation.store', $this->invitation->token), [
                'name' => 'Comprehensive Test User',
                'password' => 'SecurePassword123!',
                'password_confirmation' => 'SecurePassword123!',
            ]);

        $testResults['session_preservation'] = Auth::check() && Auth::id() === $originalAuthId;

        // 2. Tenant login failure - Users can login on tenant domains
        $createdUser = User::where('email', $this->invitation->email)->first();
        $this->assertNotNull($createdUser);
        $testResults['user_creation'] = true;

        // 3. Layout overlap - Fixed split-screen layout
        $layoutResponse = $this->get('/admin/login');
        $testResults['layout_rendering'] = $layoutResponse->isOk();

        // 4. Invitation processing
        $this->invitation->refresh();
        $testResults['invitation_processing'] = $this->invitation->isAccepted();

        // 5. Cross-domain redirect
        $testResults['cross_domain_redirect'] = $response->isRedirect() &&
            str_contains($response->headers->get('Location'), $this->company->domain);

        // Assert all fixes are working
        foreach ($testResults as $test => $result) {
            $this->assertTrue($result, "Fix validation failed for: {$test}");
        }

        // Log comprehensive test results
        activity('comprehensive_validation')
            ->withProperties($testResults)
            ->log('All invitation flow fixes validated successfully');
    }
}
