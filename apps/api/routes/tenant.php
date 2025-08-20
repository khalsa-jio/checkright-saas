<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Features\UserImpersonation;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Optimized tenant routes with proper middleware stack for performance
| and security. Includes session scoping, rate limiting, and caching.
|
| Features:
| - Conditional tenancy initialization
| - Session isolation between tenants
| - Rate limiting for security
| - Route caching for performance
| - Proper error handling
|
*/

// Optimized tenant routes with proper middleware ordering
// Order is critical: web middleware first, then domain validation, then tenancy initialization
Route::middleware([
    'web', // Must be first - starts session and handles CSRF
    PreventAccessFromCentralDomains::class, // CRITICAL: Prevent central access BEFORE tenancy initialization
    \App\Http\Middleware\TenantSessionBootstrapper::class, // Configure sessions after they start
    \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class, // Initialize tenancy (only for tenant domains)
    \Stancl\Tenancy\Middleware\ScopeSessions::class, // Scope sessions to tenant
])
    ->name('tenant.')
    ->group(function () {
        // User impersonation route with enhanced session management
        Route::get('/impersonate/{token}', function ($token, \Illuminate\Http\Request $request) {
            // Validate cross-domain session transition if coming from invitation
            \App\Services\SessionManager::validateCrossDomainTransition($request);

            // Use core Stancl/Tenancy impersonation with session cleanup
            $response = UserImpersonation::makeResponse($token);

            // If successful impersonation, ensure tenant session isolation
            if ($response->isRedirection() && tenancy()->initialized) {
                // Clean any residual central domain session data for security
                if ($request->hasSession()) {
                    $session = $request->session();
                    // Remove any central domain auth artifacts that shouldn't be in tenant session
                    $session->forget('cross_domain_auth_preserved');
                    $session->save();
                }
            }

            return $response;
        })
            ->name('impersonate')
            ->middleware('throttle:10,1'); // Rate limit impersonation attempts
        // Tenant homepage with caching for performance
        Route::get('/', function () {
            $tenantId = tenant('id');
            $tenantName = tenant('name') ?? 'Unknown Tenant';

            return response()->json([
                'message' => 'Welcome to your tenant application',
                'tenant_id' => $tenantId,
                'tenant_name' => $tenantName,
                'initialized' => tenancy()->initialized,
            ])->header('Cache-Control', 'public, max-age=300'); // Cache for 5 minutes
        })
            ->name('home');

        // Note: /health route is available on central domain only (see web.php)

        // Tenant domain invitation routes (for tenant users only)
        Route::get('/invitation/{token}', [\App\Http\Controllers\TenantAcceptInvitationController::class, 'show'])
            ->name('invitation.show');

        Route::post('/invitation/{token}', [\App\Http\Controllers\TenantAcceptInvitationController::class, 'store'])
            ->name('invitation.store');

        // OAuth Social Login Routes (also available on tenant domains)
        Route::prefix('auth')->name('auth.')->group(function () {
            // OAuth Provider Redirects
            Route::get('/{provider}/redirect', [App\Http\Controllers\SocialAuthController::class, 'redirect'])
                ->name('redirect')
                ->where('provider', 'google|facebook|instagram');

            // OAuth Provider Callbacks
            Route::get('/{provider}/callback', [App\Http\Controllers\SocialAuthController::class, 'callback'])
                ->name('callback')
                ->where('provider', 'google|facebook|instagram');
        });

        // Social login route alias for Filament login page
        Route::get('/social/{provider}', [App\Http\Controllers\SocialAuthController::class, 'redirect'])
            ->name('social.redirect')
            ->where('provider', 'google|facebook|instagram')
            ->middleware('throttle:20,1'); // Rate limit social login attempts

        // Password Reset Routes (also available on tenant domains) with enhanced security
        Route::prefix('password')->name('password.')->group(function () {
            // Password reset request form (GET)
            Route::get('/reset', function () {
                return view('auth.passwords.email');
            })->name('request');

            // Password reset email submission (POST) with enhanced rate limiting
            Route::post('/email', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'sendResetLinkEmail'])
                ->name('email')
                ->middleware('throttle:3,1'); // Stricter rate limit: 3 attempts per minute

            // Password reset form (GET) with token validation
            Route::get('/reset/{token}', [App\Http\Controllers\Auth\ResetPasswordController::class, 'showResetForm'])
                ->name('reset')
                ->middleware('throttle:10,1'); // Prevent token enumeration

            // Password reset submission (POST) with enhanced security
            Route::post('/reset', [App\Http\Controllers\Auth\ResetPasswordController::class, 'reset'])
                ->name('update')
                ->middleware(['throttle:3,1', 'verified']); // Require email verification
        });
    });
