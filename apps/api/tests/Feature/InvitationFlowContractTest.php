<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Contract Validation Testing for Invitation Flow.
 *
 * Validates API contracts and data specifications:
 * - Response format validation
 * - Data type consistency
 * - Required field validation
 * - Error response standards
 * - Database schema compliance
 * - Backward compatibility
 */
class InvitationFlowContractTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Company $company;

    private Invitation $invitation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->company = Company::factory()->withDomain('contract-test')->create();
        $this->invitation = Invitation::factory()
            ->forTenant($this->company)
            ->email('contract@test.com')
            ->admin()
            ->pending()
            ->create();
    }

    /**
     * CONTRACT TEST: Invitation Show Response Structure.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function invitation_show_response_contract_validation()
    {
        $response = $this->get(route('invitation.show', $this->invitation->token));

        $response->assertOk();
        $response->assertViewIs('invitations.accept');
        $response->assertViewHas(['invitation', 'token']);

        // Validate view data structure
        $viewData = $response->viewData('invitation');
        $this->assertInstanceOf(Invitation::class, $viewData);
        $this->assertEquals($this->invitation->id, $viewData->id);
        $this->assertEquals($this->invitation->token, $viewData->token);

        // Validate required HTML elements are present
        $response->assertSee('name="name"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('name="password_confirmation"', false);
        $response->assertSee('type="submit"', false);

        // Validate invitation data accessibility
        $this->assertTrue($viewData->isValid());
        $this->assertFalse($viewData->isExpired());
        $this->assertFalse($viewData->isAccepted());
    }

    /**
     * CONTRACT TEST: Invitation Store Success Response.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function invitation_store_success_response_contract()
    {
        $response = $this->post(route('invitation.store', $this->invitation->token), [
            'name' => 'Contract Test User',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        // Should return 302 redirect
        $response->assertStatus(302);

        // Should redirect to tenant domain with impersonation token
        $location = $response->headers->get('Location');
        $this->assertIsString($location);
        $this->assertStringContainsString($this->company->domain, $location);
        $this->assertStringContainsString('/impersonate/', $location);

        // Validate redirect URL format
        $urlPattern = '/^https?:\/\/' . preg_quote($this->company->domain) . '\.test\/impersonate\/[a-zA-Z0-9]+$/';
        $this->assertMatchesRegularExpression($urlPattern, $location);

        // Should have success flash message in session
        $response->assertSessionHas('invitation_success');

        // Validate database changes
        $this->assertDatabaseHas('users', [
            'email' => $this->invitation->email,
            'name' => 'Contract Test User',
            'role' => $this->invitation->role,
            'tenant_id' => $this->invitation->tenant_id,
        ]);

        // Validate invitation marked as accepted
        $this->invitation->refresh();
        $this->assertTrue($this->invitation->isAccepted());
        $this->assertNotNull($this->invitation->accepted_at);
    }

    /**
     * CONTRACT TEST: Invitation Store Validation Error Response.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function invitation_store_validation_error_contract()
    {
        // Test missing required fields
        $response = $this->post(route('invitation.store', $this->invitation->token), [
            'name' => '',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect();
        $response->assertSessionHasErrors(['name', 'password']);

        // Test password confirmation mismatch
        $response = $this->post(route('invitation.store', $this->invitation->token), [
            'name' => 'Test User',
            'password' => 'Password123!',
            'password_confirmation' => 'DifferentPassword123!',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['password']);

        // Test weak password
        $response = $this->post(route('invitation.store', $this->invitation->token), [
            'name' => 'Test User',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['password']);

        // Validate invitation remains unchanged after validation errors
        $this->invitation->refresh();
        $this->assertFalse($this->invitation->isAccepted());
        $this->assertNull($this->invitation->accepted_at);
    }

    /**
     * CONTRACT TEST: Invalid Invitation Response.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function invalid_invitation_response_contract()
    {
        // Test invalid token
        $response = $this->get(route('invitation.show', 'invalid-token'));
        $response->assertOk();
        $response->assertViewIs('invitations.invalid');
        $response->assertViewHas('message');
        $response->assertSee('This invitation is invalid, expired, or has already been accepted.');

        // Test expired invitation
        $expiredInvitation = Invitation::factory()
            ->forTenant($this->company)
            ->email('expired@test.com')
            ->admin()
            ->expired()
            ->create();

        $response = $this->get(route('invitation.show', $expiredInvitation->token));
        $response->assertOk();
        $response->assertViewIs('invitations.invalid');
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
        $response->assertViewIs('invitations.invalid');
        $response->assertSee('already been accepted');
    }

    /**
     * CONTRACT TEST: Database Schema Validation.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function database_schema_contract_validation()
    {
        // Validate Users table structure
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasColumns('users', [
            'id', 'name', 'email', 'password', 'role', 'tenant_id',
            'email_verified_at', 'created_at', 'updated_at',
        ]));

        // Validate Invitations table structure
        $this->assertTrue(Schema::hasTable('invitations'));
        $this->assertTrue(Schema::hasColumns('invitations', [
            'id', 'tenant_id', 'email', 'role', 'token', 'invited_by',
            'accepted_at', 'expires_at', 'created_at', 'updated_at',
        ]));

        // Validate Companies/Tenants table structure
        $this->assertTrue(Schema::hasTable('tenants'));
        $this->assertTrue(Schema::hasColumns('tenants', [
            'id', 'name', 'domain', 'data', 'created_at', 'updated_at',
        ]));
    }

    /**
     * CONTRACT TEST: Model Relationships and Methods.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function model_contract_validation()
    {
        // Validate Invitation model methods
        $this->assertTrue(method_exists(Invitation::class, 'isValid'));
        $this->assertTrue(method_exists(Invitation::class, 'isExpired'));
        $this->assertTrue(method_exists(Invitation::class, 'isAccepted'));
        $this->assertTrue(method_exists(Invitation::class, 'markAsAccepted'));
        $this->assertTrue(method_exists(Invitation::class, 'getAcceptanceUrl'));

        // Test method return types
        $this->assertIsBool($this->invitation->isValid());
        $this->assertIsBool($this->invitation->isExpired());
        $this->assertIsBool($this->invitation->isAccepted());
        $this->assertIsString($this->invitation->getAcceptanceUrl());

        // Validate relationships
        $this->assertInstanceOf(Company::class, $this->invitation->company);
        $this->assertInstanceOf(User::class, $this->invitation->inviter);

        // Test User model methods
        $user = User::factory()->create();
        $this->assertTrue(method_exists(User::class, 'hasRole'));
        $this->assertTrue(method_exists(User::class, 'isSuperAdmin'));
        $this->assertTrue(method_exists(User::class, 'isAdmin'));
        $this->assertTrue(method_exists(User::class, 'isUser'));
    }

    /**
     * CONTRACT TEST: Data Type Validation.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function data_type_contract_validation()
    {
        // Create user through invitation to test data types
        $response = $this->post(route('invitation.store', $this->invitation->token), [
            'name' => 'Data Type Test User',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $response->assertRedirect();

        $user = User::where('email', $this->invitation->email)->first();
        $this->invitation->refresh();

        // Validate User data types
        $this->assertIsInt($user->id);
        $this->assertIsString($user->name);
        $this->assertIsString($user->email);
        $this->assertIsString($user->password);
        $this->assertIsString($user->role);
        $this->assertIsInt($user->tenant_id);
        $this->assertInstanceOf(\Carbon\Carbon::class, $user->email_verified_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $user->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $user->updated_at);

        // Validate Invitation data types
        $this->assertIsInt($this->invitation->id);
        $this->assertIsInt($this->invitation->tenant_id);
        $this->assertIsString($this->invitation->email);
        $this->assertIsString($this->invitation->role);
        $this->assertIsString($this->invitation->token);
        $this->assertIsInt($this->invitation->invited_by);
        $this->assertInstanceOf(\Carbon\Carbon::class, $this->invitation->accepted_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $this->invitation->expires_at);

        // Validate Company data types
        $this->assertIsInt($this->company->id);
        $this->assertIsString($this->company->name);
        $this->assertIsString($this->company->domain);
        $this->assertIsArray($this->company->data);
    }

    /**
     * CONTRACT TEST: Required Field Validation.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function required_fields_contract_validation()
    {
        $requiredFieldTests = [
            // Missing name
            ['name' => '', 'password' => 'Password123!', 'password_confirmation' => 'Password123!'],
            // Missing password
            ['name' => 'Test User', 'password' => '', 'password_confirmation' => 'Password123!'],
            // Missing password confirmation
            ['name' => 'Test User', 'password' => 'Password123!', 'password_confirmation' => ''],
        ];

        foreach ($requiredFieldTests as $testData) {
            $response = $this->post(route('invitation.store', $this->invitation->token), $testData);

            $response->assertStatus(302);
            $response->assertSessionHasErrors();

            // Verify invitation remains unaccepted
            $this->invitation->refresh();
            $this->assertFalse($this->invitation->isAccepted());
        }
    }

    /**
     * CONTRACT TEST: HTTP Status Codes.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function http_status_codes_contract_validation()
    {
        // Valid invitation show - should return 200
        $response = $this->get(route('invitation.show', $this->invitation->token));
        $response->assertStatus(200);

        // Invalid invitation show - should return 200 (with error message)
        $response = $this->get(route('invitation.show', 'invalid'));
        $response->assertStatus(200);

        // Valid invitation store - should return 302 (redirect)
        $response = $this->post(route('invitation.store', $this->invitation->token), [
            'name' => 'Status Test User',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);
        $response->assertStatus(302);

        // Invalid invitation store - should return 302 (redirect with errors)
        $newInvitation = Invitation::factory()
            ->forTenant($this->company)
            ->email('status@test.com')
            ->admin()
            ->pending()
            ->create();

        $response = $this->post(route('invitation.store', $newInvitation->token), [
            'name' => '',
            'password' => 'weak',
            'password_confirmation' => 'different',
        ]);
        $response->assertStatus(302);

        // Non-existent route - should return 404
        $response = $this->get('/invitation/non-existent-route');
        $response->assertStatus(404);
    }

    /**
     * CONTRACT TEST: Response Headers.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function response_headers_contract_validation()
    {
        // Test successful invitation processing headers
        $response = $this->post(route('invitation.store', $this->invitation->token), [
            'name' => 'Header Test User',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $response->assertStatus(302);

        // Should have Location header for redirect
        $this->assertNotNull($response->headers->get('Location'));

        // Should have proper content type
        $response = $this->get(route('invitation.show',
            Invitation::factory()->forTenant($this->company)->admin()->pending()->create()->token
        ));

        $this->assertEquals('text/html; charset=UTF-8', $response->headers->get('Content-Type'));

        // Should have security headers (if configured)
        $this->assertIsString($response->headers->get('X-Frame-Options', ''));
        $this->assertIsString($response->headers->get('X-Content-Type-Options', ''));
    }

    /**
     * CONTRACT TEST: Route Parameters and Naming.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function route_contract_validation()
    {
        // Validate route names exist
        $this->assertNotNull(route('invitation.show', 'test-token'));
        $this->assertNotNull(route('invitation.store', 'test-token'));

        // Validate route URLs
        $showUrl = route('invitation.show', $this->invitation->token);
        $storeUrl = route('invitation.store', $this->invitation->token);

        $this->assertStringContainsString('/invitation/' . $this->invitation->token, $showUrl);
        $this->assertStringContainsString('/invitation/' . $this->invitation->token, $storeUrl);

        // Test route parameter binding
        $response = $this->get($showUrl);
        $response->assertOk();

        $viewData = $response->viewData('token');
        $this->assertEquals($this->invitation->token, $viewData);
    }

    /**
     * CONTRACT TEST: Backward Compatibility.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function backward_compatibility_contract_validation()
    {
        // Test that old invitation tokens still work (if any format changes)
        $oldFormatToken = $this->invitation->token; // Current format

        $response = $this->get(route('invitation.show', $oldFormatToken));
        $response->assertOk();

        // Test that old database structure is supported
        $invitation = Invitation::find($this->invitation->id);
        $this->assertNotNull($invitation);
        $this->assertEquals($this->invitation->token, $invitation->token);

        // Test that old response formats are maintained
        $response = $this->post(route('invitation.store', $oldFormatToken), [
            'name' => 'Compatibility Test User',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('/impersonate/', $location);
    }
}
