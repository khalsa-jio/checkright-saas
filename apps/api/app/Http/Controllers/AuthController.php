<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptInvitationRequest;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\TenantCreationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function __construct(
        private TenantCreationService $tenantCreationService
    ) {}

    /**
     * Accept invitation and create user account.
     */
    public function acceptInvitation(AcceptInvitationRequest $request, string $token): JsonResponse
    {
        try {
            $user = $this->tenantCreationService->acceptInvitation(
                $token,
                $request->validated()
            );

            // Create authentication token for the newly registered user with role-based abilities
            $authToken = $user->createToken('auth-token', $user->getTokenAbilities())->plainTextToken;

            return response()->json([
                'message' => 'Account created successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'tenant_id' => $user->tenant_id,
                ],
                'token' => $authToken,
                'company' => $user->company->name ?? null,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to accept invitation',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * User login with Remember Me support.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');
        $rememberMe = $request->boolean('remember_me', false);
        $email = $request->email;

        // Rate limiting key
        $key = 'login:' . $request->ip() . ':' . $email;

        // Check rate limit (5 attempts per minute)
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => 'Too many login attempts. Please try again in ' . $seconds . ' seconds.',
            ], 429);
        }

        // Find user by email
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            // Increment rate limiter on failed attempt
            RateLimiter::hit($key, 60);

            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Clear rate limiter on successful login
        RateLimiter::clear($key);

        // Determine token expiration based on Remember Me
        $tokenExpiration = $rememberMe
            ? now()->addDays(config('auth.remember_me_duration_days', 30))
            : now()->addDay();

        // Create authentication token with appropriate expiration and role-based abilities
        $tokenName = $rememberMe ? 'auth-token-remember' : 'auth-token';
        $token = $user->createToken($tokenName, $user->getTokenAbilities(), $tokenExpiration);

        // Update last login timestamp
        $user->update(['last_login_at' => now()]);

        // Log successful login
        activity('user_login')
            ->performedOn($user)
            ->withProperties([
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'remember_me' => $rememberMe,
                'token_expires_at' => $tokenExpiration,
            ])
            ->log('User logged in successfully');

        return response()->json([
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'tenant_id' => $user->tenant_id,
            ],
            'token' => $token->plainTextToken,
            'expires_at' => $tokenExpiration->toISOString(),
            'remember_me' => $rememberMe,
        ]);
    }

    /**
     * User logout.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        // Revoke the current token
        $request->user()->currentAccessToken()->delete();

        // Log logout activity
        activity('user_logout')
            ->performedOn($user)
            ->withProperties([
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])
            ->log('User logged out successfully');

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }
}
