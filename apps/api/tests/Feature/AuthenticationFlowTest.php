<?php

use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

describe('Authentication Flow', function () {
    beforeEach(function () {
        // Clear rate limiters before each test
        RateLimiter::clear('login:127.0.0.1:test@example.com');
    });

    it('can access health check endpoint', function () {
        $response = $this->get('/health');
        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    });

    describe('Invitation Acceptance', function () {
        it('can accept a valid invitation and create user account', function () {
            $company = Company::factory()->create();

            $invitation = Invitation::factory()->create([
                'tenant_id' => $company->id,
                'email' => 'newuser@example.com',
                'role' => 'admin',
                'token' => 'valid-invitation-token',
                'expires_at' => now()->addDays(7),
                'accepted_at' => null,
            ]);

            $response = $this->postJson('/api/invitations/valid-invitation-token/accept', [
                'name' => 'John Doe',
                'password' => 'SecurePassword123!',
                'password_confirmation' => 'SecurePassword123!',
            ]);

            $response->assertStatus(201)
                ->assertJsonStructure([
                    'message',
                    'user' => ['id', 'name', 'email', 'role', 'tenant_id'],
                    'token',
                    'company',
                ]);

            $this->assertDatabaseHas('users', [
                'name' => 'John Doe',
                'email' => 'newuser@example.com',
                'tenant_id' => $company->id,
                'role' => 'admin',
            ]);

            // Verify invitation is marked as accepted
            $invitation->refresh();
            expect($invitation->accepted_at)->not->toBeNull();
        });

        it('rejects invalid invitation token', function () {
            $response = $this->postJson('/api/invitations/invalid-token/accept', [
                'name' => 'John Doe',
                'password' => 'SecurePassword123!',
                'password_confirmation' => 'SecurePassword123!',
            ]);

            $response->assertStatus(400)
                ->assertJson([
                    'message' => 'Failed to accept invitation',
                ]);
        });

        it('validates required fields for invitation acceptance', function () {
            $company = Company::factory()->create();

            $invitation = Invitation::factory()->create([
                'tenant_id' => $company->id,
                'token' => 'valid-token',
                'expires_at' => now()->addDays(7),
            ]);

            $response = $this->postJson('/api/invitations/valid-token/accept', [
                // Missing required fields
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['name', 'password']);
        });

        it('validates password confirmation', function () {
            $company = Company::factory()->create();

            $invitation = Invitation::factory()->create([
                'tenant_id' => $company->id,
                'token' => 'valid-token',
                'expires_at' => now()->addDays(7),
            ]);

            $response = $this->postJson('/api/invitations/valid-token/accept', [
                'name' => 'John Doe',
                'password' => 'SecurePassword123!',
                'password_confirmation' => 'DifferentPassword',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['password']);
        });
    });

    describe('User Login', function () {
        it('can login with valid credentials', function () {
            $company = Company::factory()->create();

            $user = User::factory()->create([
                'email' => 'test@example.com',
                'password' => Hash::make('password123'),
                'tenant_id' => $company->id,
                'role' => 'admin',
            ]);

            $response = $this->postJson('/api/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password123',
                'remember_me' => false,
            ]);

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'user' => ['id', 'name', 'email', 'role', 'tenant_id'],
                    'token',
                    'expires_at',
                    'remember_me',
                ]);

            // Verify last_login_at was updated
            $user->refresh();
            expect($user->last_login_at)->not->toBeNull();
        });

        it('can login with remember me enabled', function () {
            $company = Company::factory()->create();

            $user = User::factory()->create([
                'email' => 'test@example.com',
                'password' => Hash::make('password123'),
                'tenant_id' => $company->id,
            ]);

            $response = $this->postJson('/api/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password123',
                'remember_me' => true,
            ]);

            $response->assertStatus(200)
                ->assertJson(['remember_me' => true]);
        });

        it('rejects invalid credentials', function () {
            $company = Company::factory()->create();

            User::factory()->create([
                'email' => 'test@example.com',
                'password' => Hash::make('password123'),
                'tenant_id' => $company->id,
            ]);

            $response = $this->postJson('/api/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ]);

            $response->assertStatus(401)
                ->assertJson(['message' => 'Invalid credentials']);
        });

        it('implements rate limiting for failed login attempts', function () {
            $company = Company::factory()->create();

            User::factory()->create([
                'email' => 'test@example.com',
                'password' => Hash::make('password123'),
                'tenant_id' => $company->id,
            ]);

            // Make 5 failed attempts
            for ($i = 0; $i < 5; $i++) {
                $this->postJson('/api/auth/login', [
                    'email' => 'test@example.com',
                    'password' => 'wrongpassword',
                ]);
            }

            // 6th attempt should be rate limited
            $response = $this->postJson('/api/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ]);

            $response->assertStatus(429);
        });

        it('validates required login fields', function () {
            $response = $this->postJson('/api/auth/login', [
                // Missing email and password
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['email', 'password']);
        });
    });

    describe('User Logout', function () {
        it('can logout authenticated user', function () {
            $company = Company::factory()->create();

            $user = User::factory()->create([
                'tenant_id' => $company->id,
            ]);

            // Create a token for the user
            $token = $user->createToken('test-token');

            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ])->postJson('/api/auth/logout');

            $response->assertStatus(200)
                ->assertJson(['message' => 'Logout successful']);

            // Verify token was revoked
            $this->assertDatabaseMissing('personal_access_tokens', [
                'id' => $token->accessToken->id,
            ]);
        });

        it('requires authentication for logout', function () {
            $response = $this->postJson('/api/auth/logout');

            $response->assertStatus(401);
        });
    });

    describe('Protected Routes', function () {
        it('can access user profile with valid token', function () {
            $company = Company::factory()->create();

            $user = User::factory()->create([
                'tenant_id' => $company->id,
            ]);

            $token = $user->createToken('test-token');

            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ])->getJson('/api/user');

            $response->assertStatus(200)
                ->assertJson([
                    'id' => $user->id,
                    'email' => $user->email,
                ]);
        });

        it('rejects access without valid token', function () {
            $response = $this->getJson('/api/user');

            $response->assertStatus(401);
        });
    });
});
