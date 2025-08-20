<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use App\Services\SessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class SessionIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a super admin user for testing
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
    public function it_preserves_central_domain_auth_during_cross_domain_transition()
    {
        // Simulate being logged in as super admin on central domain
        $this->actingAs($this->superAdmin);

        // Create a company and invitation
        $company = Company::factory()->withDomain('testcompany')->create();
        $invitation = Invitation::factory()
            ->forTenant($company)
            ->email('test@example.com')
            ->admin()
            ->pending()
            ->create();

        // Simulate session state before invitation acceptance
        Session::put('test_central_data', 'important_central_info');
        Session::put('login_web_' . sha1(config('app.key')), $this->superAdmin->id);

        // Create a mock request for the invitation acceptance
        $request = Request::create('/invitation/' . $invitation->token, 'POST', [
            'name' => 'Test User',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);
        $request->setLaravelSession(app('session')->driver());

        // Prepare cross-domain transition
        SessionManager::prepareCrossDomainTransition($request, $company->domain);

        // Verify that auth is preserved for transition
        $this->assertTrue($request->session()->has('cross_domain_auth_preserved'));
        $this->assertTrue($request->session()->has('cross_domain_transition_time'));

        // Simulate what happens on tenant domain after redirect
        $tenantRequest = Request::create('http://testcompany.test/impersonate/token');
        $tenantRequest->setLaravelSession($request->session());

        // Validate the transition
        $isValidTransition = SessionManager::validateCrossDomainTransition($tenantRequest);
        $this->assertTrue($isValidTransition);

        // Verify transition markers are cleaned up after validation
        $this->assertFalse($tenantRequest->session()->has('cross_domain_auth_preserved'));
        $this->assertFalse($tenantRequest->session()->has('cross_domain_transition_time'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_cleans_tenant_session_data_without_affecting_central_auth()
    {
        // Simulate being logged in as super admin
        $this->actingAs($this->superAdmin);

        $request = Request::create('/');
        $request->setLaravelSession(app('session')->driver());

        // Add both central and tenant data to session
        $session = $request->session();
        $authKey = 'login_web_' . sha1(config('app.key'));
        $session->put($authKey, $this->superAdmin->id);
        $session->put('_token', 'csrf_token_value');
        $session->put('tenant_specific_data', 'should_be_removed');
        $session->put('filament_admin_panel', 'should_be_removed');
        $session->put('admin_custom_data', 'should_be_removed');
        $session->put('invitation_success', 'should_be_preserved');

        // Clean tenant session data
        SessionManager::cleanTenantSession($request);

        // Verify central auth is preserved
        $this->assertEquals($this->superAdmin->id, $session->get($authKey));
        $this->assertEquals('csrf_token_value', $session->get('_token'));
        $this->assertEquals('should_be_preserved', $session->get('invitation_success'));

        // Verify tenant data is removed
        $this->assertNull($session->get('tenant_specific_data'));
        $this->assertNull($session->get('filament_admin_panel'));
        $this->assertNull($session->get('admin_custom_data'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_identifies_central_domain_correctly()
    {
        $centralRequest = Request::create('http://checkright.test/');
        $tenantRequest = Request::create('http://testcompany.checkright.test/');

        $this->assertTrue(SessionManager::isCentralDomain($centralRequest));
        $this->assertFalse(SessionManager::isCentralDomain($tenantRequest));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_generates_appropriate_session_config_for_domains()
    {
        // Test central domain config
        $centralConfig = SessionManager::getSessionConfig('checkright.test');
        $this->assertEquals('checkright.test', $centralConfig['domain']);
        $this->assertStringContainsString('_central_session', $centralConfig['cookie']);
        $this->assertEquals('lax', $centralConfig['same_site']);

        // Test tenant domain config
        $tenantConfig = SessionManager::getSessionConfig('testcompany.checkright.test');
        $this->assertEquals('testcompany.checkright.test', $tenantConfig['domain']);
        $this->assertStringContainsString('_tenant_', $tenantConfig['cookie']);
        $this->assertEquals('lax', $tenantConfig['same_site']);
        $this->assertTrue($tenantConfig['http_only']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_expired_cross_domain_transitions()
    {
        $request = Request::create('/');
        $request->setLaravelSession(app('session')->driver());

        $session = $request->session();

        // Set up expired transition data
        $session->put('cross_domain_auth_preserved', true);
        $session->put('cross_domain_transition_time', now()->subMinutes(10)->timestamp); // 10 minutes ago

        // Validate transition
        $isValidTransition = SessionManager::validateCrossDomainTransition($request);

        // Should be invalid due to expiration
        $this->assertFalse($isValidTransition);

        // Should clean up expired transition data
        $this->assertFalse($session->has('cross_domain_auth_preserved'));
        $this->assertFalse($session->has('cross_domain_transition_time'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_valid_cross_domain_transitions()
    {
        $request = Request::create('/');
        $request->setLaravelSession(app('session')->driver());

        $session = $request->session();

        // Set up valid transition data
        $session->put('cross_domain_auth_preserved', true);
        $session->put('cross_domain_transition_time', now()->subMinutes(2)->timestamp); // 2 minutes ago

        // Validate transition
        $isValidTransition = SessionManager::validateCrossDomainTransition($request);

        // Should be valid
        $this->assertTrue($isValidTransition);

        // Should clean up transition data after successful validation
        $this->assertFalse($session->has('cross_domain_auth_preserved'));
        $this->assertFalse($session->has('cross_domain_transition_time'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function invitation_acceptance_preserves_super_admin_session()
    {
        // Login as super admin
        $this->actingAs($this->superAdmin);

        // Create company and invitation
        $company = Company::factory()->withDomain('testcompany')->create();
        $invitation = Invitation::factory()
            ->forTenant($company)
            ->email('newuser@example.com')
            ->admin()
            ->pending()
            ->create();

        // Store some session data that should be preserved
        Session::put('super_admin_dashboard_settings', 'important_settings');

        // Accept the invitation
        $response = $this->post(route('invitation.store', $invitation->token), [
            'name' => 'New User',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        // Should redirect to tenant domain
        $this->assertEquals(302, $response->status());

        // Verify we're still authenticated as super admin on central domain
        $this->assertTrue(auth()->check());
        $this->assertEquals($this->superAdmin->id, auth()->id());
        $this->assertEquals('super_admin', auth()->user()->role);

        // Verify invitation was processed
        $invitation->refresh();
        $this->assertTrue($invitation->isAccepted());

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'name' => 'New User',
            'tenant_id' => $company->id,
        ]);
    }
}
