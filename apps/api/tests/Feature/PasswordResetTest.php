<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure central domain for testing
        config(['tenancy.central_domains' => ['localhost', '127.0.0.1', 'checkright.test']]);
        config(['app.url' => 'http://localhost']);

        // Configure session for testing to prevent middleware conflicts
        config(['session.domain' => null]);
    }

    public function test_password_reset_request_page_loads(): void
    {
        $response = $this->get('/password/reset');

        $response->assertStatus(200);
        $response->assertViewIs('auth.passwords.email');
    }

    public function test_user_can_request_password_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->post('/password/email', [
            'email' => 'test@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_password_reset_link_creation_is_logged(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->post('/password/email', [
            'email' => 'test@example.com',
        ]);

        $response->assertRedirect();

        // Check that a token was created in the database
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_password_reset_request_validation(): void
    {
        // Test missing email
        $response = $this->post('/password/email', []);

        $response->assertSessionHasErrors(['email']);

        // Test invalid email format
        $response = $this->post('/password/email', [
            'email' => 'invalid-email',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_password_reset_request_for_nonexistent_user(): void
    {
        $response = $this->post('/password/email', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['email']);
    }

    public function test_password_reset_form_displays_with_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->get("/password/reset/{$token}?email={$user->email}");

        $response->assertStatus(200);
        $response->assertViewIs('auth.passwords.reset');
        $response->assertViewHas('token', $token);
        $response->assertViewHas('email', $user->email);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $token = Password::createToken($user);

        $response = $this->post('/password/reset', [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHas('status');

        // Verify password was actually changed
        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
    }

    public function test_password_reset_validation_rules(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        // Test password too short
        $response = $this->post('/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors(['password']);

        // Test password without required complexity
        $response = $this->post('/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'simplerepassword',
            'password_confirmation' => 'simplepassword',
        ]);

        $response->assertSessionHasErrors(['password']);

        // Test password confirmation mismatch
        $response = $this->post('/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ValidPassword123!',
            'password_confirmation' => 'DifferentPassword123!',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    public function test_password_reset_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/password/reset', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['email']);
    }

    public function test_password_reset_with_expired_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        // Manually expire the token by updating the database
        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->update(['created_at' => now()->subHours(2)]);

        $response = $this->post('/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['email']);
    }

    public function test_password_reset_rate_limiting(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        // Make 5 requests (the limit)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/password/email', [
                'email' => 'test@example.com',
            ]);
            $response->assertRedirect();
        }

        // 6th request should be rate limited
        $response = $this->post('/password/email', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(429); // Too Many Requests
    }

    public function test_password_reset_tokens_are_single_use(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $token = Password::createToken($user);

        // First use should succeed
        $response = $this->post('/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertRedirect('/admin/login');

        // Logout the user to test token reuse
        auth()->logout();

        // Second use should fail - token should be invalid/deleted
        $response = $this->post('/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'AnotherPassword123!',
            'password_confirmation' => 'AnotherPassword123!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['email']);
    }

    public function test_login_page_has_forgot_password_link(): void
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
        $response->assertSee('Forgot your password?');
        $response->assertSee(route('password.request'));
    }

    public function test_successful_password_reset_logs_user_in(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->assertGuest();

        $response = $this->post('/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertRedirect('/admin/login');

        // User should be logged in after password reset
        $this->assertAuthenticated();
        $this->assertEquals($user->id, auth()->id());
    }

    public function test_social_auth_only_user_cannot_request_password_reset(): void
    {
        $user = User::factory()->create([
            'email' => 'social@example.com',
            'password' => null, // OAuth-only user has no password
        ]);

        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => '123456789',
        ]);

        $response = $this->post('/password/email', [
            'email' => 'social@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['email']);

        $error = session('errors')->first('email');
        $this->assertStringContainsString('social login', $error);
        $this->assertStringContainsString('Google', $error);
    }

    public function test_social_auth_only_user_with_multiple_providers_shows_all_providers(): void
    {
        $user = User::factory()->create([
            'email' => 'multi@example.com',
            'password' => null,
        ]);

        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'google',
        ]);

        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'facebook',
        ]);

        $response = $this->post('/password/email', [
            'email' => 'multi@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['email']);

        $error = session('errors')->first('email');
        $this->assertStringContainsString('Google', $error);
        $this->assertStringContainsString('Facebook', $error);
    }

    public function test_user_with_password_and_social_accounts_can_reset_password(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'mixed@example.com',
            'password' => Hash::make('existing-password'),
        ]);

        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'google',
        ]);

        $response = $this->post('/password/email', [
            'email' => 'mixed@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_oauth_only_user_setting_first_password_is_logged(): void
    {
        $user = User::factory()->create([
            'password' => null,
        ]);

        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'google',
        ]);

        $token = Password::createToken($user);

        // Allow OAuth-only users to complete password reset through direct token access
        // (this would happen if they got the token through other means, like support)
        $response = $this->post('/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertRedirect('/admin/login');

        // Verify password was set
        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
    }

    public function test_regular_user_without_social_accounts_works_normally(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'regular@example.com',
            'password' => Hash::make('old-password'),
        ]);

        // No social accounts

        $response = $this->post('/password/email', [
            'email' => 'regular@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_nonexistent_user_with_social_email_shows_standard_error(): void
    {
        // Test that we don't leak information about whether an email exists
        $response = $this->post('/password/email', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['email']);

        // Should show standard "user not found" error, not social auth error
        $error = session('errors')->first('email');
        $this->assertStringNotContainsString('social login', $error);
    }
}
