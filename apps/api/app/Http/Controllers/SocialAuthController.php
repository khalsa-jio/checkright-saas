<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\SocialLoginLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class SocialAuthController extends Controller
{
    /**
     * Redirect user to OAuth provider for authentication.
     */
    public function redirect(Request $request, string $provider)
    {
        try {
            $this->validateProvider($provider);

            // Store tenant context if provided
            if ($request->has('tenant')) {
                session(['oauth_tenant' => $request->get('tenant')]);
            }

            // Store intended URL for post-login redirect
            if ($request->has('intended')) {
                session(['oauth_intended' => $request->get('intended')]);
            }

            return Socialite::driver($provider)->redirect();
        } catch (\Exception $e) {
            return redirect('/admin/login')->withErrors([
                'oauth' => "Failed to initialize {$provider} authentication. Please try again.",
            ]);
        }
    }

    /**
     * Handle OAuth provider callback.
     */
    public function callback(Request $request, string $provider)
    {
        try {
            $this->validateProvider($provider);

            $socialUser = Socialite::driver($provider)->user();

            // Find or create user account
            $user = $this->findOrCreateUser($socialUser, $provider);

            if (! $user) {
                return redirect('/admin/login')->withErrors([
                    'oauth' => 'Unable to create or find user account. Please contact support.',
                ]);
            }

            // Log the user in
            Auth::login($user, true);

            // Update last login timestamp
            $user->update(['last_login_at' => now()]);

            // Get intended URL before clearing session
            $intendedUrl = session('oauth_intended', '/admin');

            // Log successful social login
            SocialLoginLogger::logSuccessfulLogin($user, $provider, [
                'session_id' => session()->getId(),
                'intended_url' => $intendedUrl,
            ]);

            // Clear OAuth session data
            session()->forget(['oauth_tenant', 'oauth_intended']);

            // Redirect to intended URL or admin dashboard
            return redirect($intendedUrl);
        } catch (InvalidStateException $e) {
            // Log state validation failure
            SocialLoginLogger::logStateValidationFailure($provider, [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return redirect('/admin/login')->withErrors([
                'oauth' => 'Authentication session expired. Please try again.',
            ]);
        } catch (\Exception $e) {
            // Log general OAuth failure
            SocialLoginLogger::logFailedLogin($provider, $e->getMessage(), [
                'request_data' => $request->all(),
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
            ]);

            return redirect('/admin/login')->withErrors([
                'oauth' => "Authentication with {$provider} failed. Please try again.",
            ]);
        }
    }

    /**
     * Validate OAuth provider.
     */
    private function validateProvider(string $provider): void
    {
        if (! in_array($provider, ['google', 'facebook', 'instagram'])) {
            throw new \InvalidArgumentException("Unsupported OAuth provider: {$provider}");
        }
    }

    /**
     * Find existing user or create new one from social login.
     */
    private function findOrCreateUser($socialUser, string $provider): ?User
    {
        // Look for existing social account
        $socialAccount = SocialAccount::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($socialAccount) {
            // Update social account info
            $socialAccount->update([
                'avatar' => $socialUser->getAvatar(),
                'token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'expires_in' => $socialUser->expiresIn,
            ]);

            return $socialAccount->user;
        }

        // Look for existing user by email
        $existingUser = User::where('email', $socialUser->getEmail())->first();

        if ($existingUser) {
            // Link social account to existing user
            $this->createSocialAccount($existingUser, $socialUser, $provider);

            return $existingUser;
        }

        // Create new user from social login
        return $this->createUserFromSocial($socialUser, $provider);
    }

    /**
     * Create new user from social login data.
     */
    private function createUserFromSocial($socialUser, string $provider): User
    {
        // Determine tenant context
        $tenantId = session('oauth_tenant');

        if (! $tenantId) {
            // TODO -For now, we'll require tenant context for social login
            // This could be enhanced to allow social login to create super admin users
            throw new \Exception('Tenant context required for social login');
        }

        // Create user
        $user = User::create([
            'name' => $socialUser->getName() ?: $socialUser->getNickname() ?: 'Social User',
            'email' => $socialUser->getEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make(Str::random(32)), // Random password since they'll use social login
            'tenant_id' => $tenantId,
            'role' => 'operator', // Default role, can be changed by admin
        ]);

        // Create associated social account
        $this->createSocialAccount($user, $socialUser, $provider);

        // Log account creation
        \Log::info("New user created via {$provider} OAuth", [
            'user_id' => $user->id,
            'email' => $user->email,
            'provider' => $provider,
            'tenant_id' => $tenantId,
        ]);

        return $user;
    }

    /**
     * Create social account record.
     */
    private function createSocialAccount(User $user, $socialUser, string $provider): SocialAccount
    {
        return SocialAccount::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'name' => $socialUser->getName(),
            'email' => $socialUser->getEmail(),
            'avatar' => $socialUser->getAvatar(),
            'token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken,
            'expires_in' => $socialUser->expiresIn,
        ]);
    }

    /**
     * Build authorization URL manually for stateless mobile OAuth.
     */
    private function buildAuthorizationUrl(string $provider, string $state): string
    {
        $config = config("services.{$provider}");

        if (! $config || ! isset($config['client_id']) || ! isset($config['redirect'])) {
            throw new \Exception("OAuth configuration missing for {$provider}");
        }

        $baseUrls = [
            'google' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'facebook' => 'https://www.facebook.com/v18.0/dialog/oauth',
            'instagram' => 'https://api.instagram.com/oauth/authorize',
        ];

        if (! isset($baseUrls[$provider])) {
            throw new \Exception("Unsupported provider: {$provider}");
        }

        $scopes = [
            'google' => 'openid profile email',
            'facebook' => 'email',
            'instagram' => 'user_profile,user_media',
        ];

        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect'],
            'scope' => $scopes[$provider],
            'response_type' => 'code',
            'state' => $state,
        ];

        // Add provider-specific parameters
        if ($provider === 'google') {
            $params['access_type'] = 'offline';
            $params['prompt'] = 'consent';
        }

        return $baseUrls[$provider] . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for user data without using sessions.
     */
    private function exchangeCodeForUser(string $provider, string $code)
    {
        // Set up the request parameters needed by Socialite
        $originalRequest = request();
        $tempRequest = $originalRequest->duplicate();
        $tempRequest->merge([
            'code' => $code,
        ]);

        // Replace the request temporarily
        app()->instance('request', $tempRequest);

        try {
            // Get user data from the provider using stateless approach
            $driver = Socialite::driver($provider);

            // Use stateless method if available, otherwise fall back to regular method
            if (method_exists($driver, 'stateless')) {
                $socialUser = $driver->stateless()->user();
            } else {
                $socialUser = $driver->user();
            }

            // Restore original request
            app()->instance('request', $originalRequest);

            return $socialUser;
        } catch (\Exception $e) {
            // Restore original request even on failure
            app()->instance('request', $originalRequest);
            throw $e;
        }
    }

    /**
     * Find or create user for mobile OAuth with tenant context.
     */
    private function findOrCreateUserForMobile($socialUser, string $provider, array $contextData): ?User
    {
        // Look for existing social account
        $socialAccount = SocialAccount::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($socialAccount) {
            // Update social account info
            $socialAccount->update([
                'avatar' => $socialUser->getAvatar(),
                'token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'expires_in' => $socialUser->expiresIn,
            ]);

            return $socialAccount->user;
        }

        // Look for existing user by email
        $existingUser = User::where('email', $socialUser->getEmail())->first();

        if ($existingUser) {
            // Link social account to existing user
            $this->createSocialAccount($existingUser, $socialUser, $provider);

            return $existingUser;
        }

        // Create new user from social login using context data
        return $this->createUserFromSocialForMobile($socialUser, $provider, $contextData);
    }

    /**
     * Create new user from social login data for mobile.
     */
    private function createUserFromSocialForMobile($socialUser, string $provider, array $contextData): User
    {
        // Use tenant context from stored state data
        $tenantId = $contextData['tenant_id'] ?? null;

        if (! $tenantId) {
            // TODO - For now, we'll require tenant context for social login
            // This could be enhanced to allow social login to create super admin users
            throw new \Exception('Tenant context required for social login');
        }

        // Create user (password is nullable for OAuth-only users)
        $user = User::create([
            'name' => $socialUser->getName() ?: $socialUser->getNickname() ?: 'Social User',
            'email' => $socialUser->getEmail(),
            'email_verified_at' => now(),
            'password' => null, // OAuth-only user, no password needed
            'tenant_id' => $tenantId,
            'role' => 'operator', // Default role, can be changed by admin
        ]);

        // Create associated social account
        $this->createSocialAccount($user, $socialUser, $provider);

        // Log account creation
        \Log::info("New user created via {$provider} mobile OAuth", [
            'user_id' => $user->id,
            'email' => $user->email,
            'provider' => $provider,
            'tenant_id' => $tenantId,
            'device_id' => $contextData['device_id'] ?? null,
            'app_version' => $contextData['app_version'] ?? null,
        ]);

        return $user;
    }

    /**
     * Initialize mobile OAuth flow by returning authorization URL.
     */
    public function mobileInitialize(Request $request, string $provider)
    {
        try {
            $this->validateProvider($provider);

            // Generate and store state for security
            $state = Str::random(40);

            // Store state and any additional context in cache for mobile flow
            $cacheKey = "mobile_oauth_state_{$state}";
            $contextData = [
                'provider' => $provider,
                'tenant_id' => $request->get('tenant_id'),
                'device_id' => $request->get('device_id'),
                'app_version' => $request->get('app_version'),
                'created_at' => now(),
            ];

            cache()->put($cacheKey, $contextData, now()->addMinutes(15));

            // Build authorization URL manually for stateless mobile OAuth
            $authUrl = $this->buildAuthorizationUrl($provider, $state);

            return response()->json([
                'success' => true,
                'data' => [
                    'authorization_url' => $authUrl,
                    'state' => $state,
                    'provider' => $provider,
                ],
            ]);
        } catch (\Exception $e) {
            SocialLoginLogger::logFailedLogin($provider, $e->getMessage(), [
                'request_data' => $request->all(),
                'mobile_oauth' => true,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'OAuth initialization failed',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Handle mobile OAuth callback with authorization code.
     */
    public function mobileCallback(Request $request, string $provider)
    {
        try {
            $this->validateProvider($provider);

            $request->validate([
                'code' => 'required|string',
                'state' => 'required|string',
            ]);

            // Verify state parameter
            $state = $request->get('state');
            $cacheKey = "mobile_oauth_state_{$state}";
            $contextData = cache()->get($cacheKey);

            if (! $contextData) {
                throw new \Exception('Invalid or expired state parameter');
            }

            // Remove state from cache to prevent reuse
            cache()->forget($cacheKey);

            // Verify provider matches
            if ($contextData['provider'] !== $provider) {
                throw new \Exception('Provider mismatch');
            }

            // Exchange authorization code for access token using stateless approach
            $socialUser = $this->exchangeCodeForUser($provider, $request->get('code'));

            if (! $socialUser->getEmail()) {
                throw new \Exception('Email is required for account creation');
            }

            // Find or create user using tenant context from stored state
            $user = $this->findOrCreateUserForMobile($socialUser, $provider, $contextData);

            if (! $user) {
                throw new \Exception('Failed to create or find user account');
            }

            // Generate API tokens for mobile app
            $tokenResult = $user->createToken("mobile-oauth-{$provider}", ['mobile-access']);
            $accessToken = $tokenResult->plainTextToken;

            // Update last login
            $user->update(['last_login_at' => now()]);

            // Log successful login
            SocialLoginLogger::logSuccessfulLogin($user, $provider, [
                'device_id' => $contextData['device_id'] ?? null,
                'app_version' => $contextData['app_version'] ?? null,
                'mobile_oauth' => true,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'access_token' => $accessToken,
                    'token_type' => 'Bearer',
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'avatar_url' => $user->getAvatarUrl(),
                        'tenant_id' => $user->tenant_id,
                        'must_change_password' => $user->must_change_password,
                    ],
                    'provider' => $provider,
                ],
            ]);
        } catch (\Exception $e) {
            SocialLoginLogger::logFailedLogin($provider, $e->getMessage(), [
                'request_data' => $request->all(),
                'mobile_oauth_callback' => true,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'OAuth authentication failed',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
