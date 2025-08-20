<?php

use App\Models\Company;
use App\Models\SocialAccount;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    $this->company = Company::factory()->create([
        'domain' => 'testcompany',
        'name' => 'Test Company',
    ]);

    // Don't initialize tenant context here - these routes work on central domain
    // The tenant context is passed via query parameter instead

    // Mock Socialite properly for testing
    $this->mockSocialiteDriver = \Mockery::mock('Laravel\Socialite\Contracts\Provider');

    Socialite::shouldReceive('driver')
        ->andReturn($this->mockSocialiteDriver);
});

describe('OAuth Provider Redirect', function () {
    it('redirects to Google OAuth provider', function () {
        // Mock Socialite redirect
        $this->mockSocialiteDriver
            ->shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('https://accounts.google.com/oauth/authorize'));

        $response = $this->get('/auth/google/redirect?tenant=' . $this->company->id);

        expect($response->status())->toBe(302);
        expect(session('oauth_tenant'))->toBe((string) $this->company->id);
    });

    it('redirects to Facebook OAuth provider', function () {
        // Mock Socialite redirect
        $this->mockSocialiteDriver
            ->shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('https://www.facebook.com/dialog/oauth'));

        $response = $this->get('/auth/facebook/redirect?tenant=' . $this->company->id);

        expect($response->status())->toBe(302);
        expect(session('oauth_tenant'))->toBe((string) $this->company->id);
    });

    it('redirects to Instagram OAuth provider', function () {
        // Mock Socialite redirect
        $this->mockSocialiteDriver
            ->shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('https://api.instagram.com/oauth/authorize'));

        $response = $this->get('/auth/instagram/redirect?tenant=' . $this->company->id);

        expect($response->status())->toBe(302);
        expect(session('oauth_tenant'))->toBe((string) $this->company->id);
    });

    it('rejects invalid OAuth provider', function () {
        $response = $this->get('/auth/invalid/redirect');

        expect($response->status())->toBe(404);
    });

    it('stores intended URL for post-login redirect', function () {
        // Mock Socialite redirect
        $this->mockSocialiteDriver
            ->shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('https://accounts.google.com/oauth/authorize'));

        $intendedUrl = '/admin/dashboard';

        $response = $this->get("/auth/google/redirect?tenant={$this->company->id}&intended={$intendedUrl}");

        expect($response->status())->toBe(302);
        expect(session('oauth_intended'))->toBe($intendedUrl);
    });
});

describe('OAuth Provider Callback', function () {
    it('creates new user from Google OAuth callback', function () {
        // Mock Socialite user data
        $mockUser = \Mockery::mock(\Laravel\Socialite\Two\User::class);
        $mockUser->shouldReceive('getId')->andReturn('123456789');
        $mockUser->shouldReceive('getName')->andReturn('John Doe');
        $mockUser->shouldReceive('getEmail')->andReturn('john@example.com');
        $mockUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');
        $mockUser->token = 'mock-access-token';
        $mockUser->refreshToken = 'mock-refresh-token';
        $mockUser->expiresIn = 3600;

        $this->mockSocialiteDriver
            ->shouldReceive('user')
            ->once()
            ->andReturn($mockUser);

        // Set tenant context in session
        session(['oauth_tenant' => $this->company->id]);

        $response = $this->get('/auth/google/callback');

        expect($response->status())->toBe(302);
        expect($response->headers->get('Location'))->toContain('/admin');

        // Verify user was created
        $user = User::where('email', 'john@example.com')->first();
        expect($user)->not->toBeNull();
        expect($user->name)->toBe('John Doe');
        expect($user->email)->toBe('john@example.com');
        expect($user->role)->toBe('operator');

        // Verify social account was created
        $socialAccount = SocialAccount::where('user_id', $user->id)->first();
        expect($socialAccount)->not->toBeNull();
        expect($socialAccount->provider)->toBe('google');
        expect($socialAccount->provider_id)->toBe('123456789');
        expect($socialAccount->token)->toBe('mock-access-token');
    });

    it('links social account to existing user', function () {
        // Create existing user
        $existingUser = User::factory()->create([
            'email' => 'john@example.com',
            'name' => 'John Existing',
        ]);

        // Mock Socialite
        $socialiteUser = new SocialiteUser();
        $socialiteUser->id = '123456789';
        $socialiteUser->name = 'John Doe';
        $socialiteUser->email = 'john@example.com';
        $socialiteUser->avatar = 'https://example.com/avatar.jpg';
        $socialiteUser->token = 'mock-access-token';

        Socialite::shouldReceive('driver')
            ->with('facebook')
            ->andReturnSelf()
            ->shouldReceive('user')
            ->andReturn($socialiteUser);

        session(['oauth_tenant' => $this->company->id]);

        $response = $this->get('/auth/facebook/callback');

        expect($response->status())->toBe(302);

        // Verify social account was linked to existing user
        $socialAccount = SocialAccount::where('user_id', $existingUser->id)->first();
        expect($socialAccount)->not->toBeNull();
        expect($socialAccount->provider)->toBe('facebook');
        expect($socialAccount->provider_id)->toBe('123456789');

        // Verify user count didn't increase
        expect(User::count())->toBe(1);
    });

    it('logs in user with existing social account', function () {
        // Create user with social account
        $user = User::factory()->create(['email' => 'john@example.com']);
        $socialAccount = SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'instagram',
            'provider_id' => '123456789',
        ]);

        // Mock Socialite with updated info
        $socialiteUser = new SocialiteUser();
        $socialiteUser->id = '123456789';
        $socialiteUser->name = 'John Updated';
        $socialiteUser->email = 'john@example.com';
        $socialiteUser->avatar = 'https://example.com/new-avatar.jpg';
        $socialiteUser->token = 'new-access-token';

        Socialite::shouldReceive('driver')
            ->with('instagram')
            ->andReturnSelf()
            ->shouldReceive('user')
            ->andReturn($socialiteUser);

        $response = $this->get('/auth/instagram/callback');

        expect($response->status())->toBe(302);
        expect(auth()->check())->toBeTrue();
        expect(auth()->user()->id)->toBe($user->id);

        // Verify social account was updated
        $socialAccount->refresh();
        expect($socialAccount->avatar)->toBe('https://example.com/new-avatar.jpg');
        expect($socialAccount->token)->toBe('new-access-token');
    });

    it('handles OAuth state exception', function () {
        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturnSelf()
            ->shouldReceive('user')
            ->andThrow(new InvalidStateException());

        $response = $this->get('/auth/google/callback');

        expect($response->status())->toBe(302);
        expect($response->headers->get('Location'))->toContain('/admin/login');
        expect(session('errors')->first('oauth'))->toContain('Authentication session expired');
    });

    it('handles general OAuth exception', function () {
        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturnSelf()
            ->shouldReceive('user')
            ->andThrow(new Exception('OAuth error'));

        $response = $this->get('/auth/google/callback');

        expect($response->status())->toBe(302);
        expect($response->headers->get('Location'))->toContain('/admin/login');
        expect(session('errors')->first('oauth'))->toContain('Authentication with google failed');
    });

    it('requires tenant context for new user creation', function () {
        // Mock Socialite without tenant context
        $socialiteUser = new SocialiteUser();
        $socialiteUser->id = '123456789';
        $socialiteUser->name = 'John Doe';
        $socialiteUser->email = 'newuser@example.com';

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturnSelf()
            ->shouldReceive('user')
            ->andReturn($socialiteUser);

        // Don't set tenant context
        $response = $this->get('/auth/google/callback');

        expect($response->status())->toBe(302);
        expect($response->headers->get('Location'))->toContain('/admin/login');
        expect(session('errors')->first('oauth'))->toContain('Authentication with google failed');
    });

    it('redirects to intended URL after successful login', function () {
        $user = User::factory()->create(['email' => 'john@example.com']);
        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => '123456789',
        ]);

        // Mock Socialite user
        $mockUser = \Mockery::mock(\Laravel\Socialite\Two\User::class);
        $mockUser->shouldReceive('getId')->andReturn('123456789');
        $mockUser->shouldReceive('getEmail')->andReturn('john@example.com');
        $mockUser->shouldReceive('getName')->andReturn('John Test');
        $mockUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');
        $mockUser->token = 'test-token';
        $mockUser->refreshToken = null;
        $mockUser->expiresIn = 3600;

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturnSelf()
            ->shouldReceive('user')
            ->andReturn($mockUser);

        // Set intended URL in session first
        $this->withSession(['oauth_intended' => '/admin/dashboard']);

        $response = $this->get('/auth/google/callback');

        expect($response->status())->toBe(302);
        expect($response->headers->get('Location'))->toContain('/admin/dashboard');
        expect(session()->has('oauth_intended'))->toBeFalse();
    });
});

describe('Social Account Model', function () {
    it('determines if token is expired', function () {
        $account = SocialAccount::factory()->create([
            'expires_in' => 3600,
            'updated_at' => now()->subHours(2), // Token expired 1 hour ago
        ]);

        expect($account->isTokenExpired())->toBeTrue();

        // Create a fresh account with current timestamp
        $freshAccount = SocialAccount::factory()->create([
            'expires_in' => 3600,
        ]);
        expect($freshAccount->isTokenExpired())->toBeFalse();
    });

    it('returns provider display name', function () {
        $account = SocialAccount::factory()->create(['provider' => 'google']);
        expect($account->provider_display_name)->toBe('Google');

        $account->update(['provider' => 'facebook']);
        expect($account->provider_display_name)->toBe('Facebook');

        $account->update(['provider' => 'instagram']);
        expect($account->provider_display_name)->toBe('Instagram');
    });

    it('belongs to user', function () {
        $user = User::factory()->create();
        $account = SocialAccount::factory()->create(['user_id' => $user->id]);

        expect($account->user->id)->toBe($user->id);
    });
});

describe('User Avatar Functionality', function () {
    it('returns avatar URL from social accounts', function () {
        $user = User::factory()->create();

        // Create social account with avatar
        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'avatar' => 'https://example.com/google-avatar.jpg',
        ]);

        expect($user->getAvatarUrl())->toBe('https://example.com/google-avatar.jpg');
    });

    it('returns most recent avatar when multiple social accounts exist', function () {
        $user = User::factory()->create();

        // Create older social account
        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'avatar' => 'https://example.com/old-avatar.jpg',
            'updated_at' => now()->subDay(),
        ]);

        // Create newer social account
        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'facebook',
            'avatar' => 'https://example.com/new-avatar.jpg',
            'updated_at' => now(),
        ]);

        expect($user->getAvatarUrl())->toBe('https://example.com/new-avatar.jpg');
    });

    it('returns null when no avatar available', function () {
        $user = User::factory()->create();

        // Create social account without avatar
        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'avatar' => null,
        ]);

        expect($user->getAvatarUrl())->toBeNull();
    });

    it('returns avatar URL from specific provider', function () {
        $user = User::factory()->create();

        // Create multiple social accounts
        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'avatar' => 'https://example.com/google-avatar.jpg',
        ]);

        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'facebook',
            'avatar' => 'https://example.com/facebook-avatar.jpg',
        ]);

        expect($user->getAvatarUrlFromProvider('google'))->toBe('https://example.com/google-avatar.jpg');
        expect($user->getAvatarUrlFromProvider('facebook'))->toBe('https://example.com/facebook-avatar.jpg');
        expect($user->getAvatarUrlFromProvider('instagram'))->toBeNull();
    });
});
