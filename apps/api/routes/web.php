<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Central domain routes
Route::middleware(['web'])
    ->group(function () {
        Route::get('/', function () {
            if (Auth::check()) {
                // If user is logged in, redirect to admin dashboard
                return redirect('/admin');
            }

            // If not logged in, redirect to admin login
            return redirect('/admin/login');
        });

        // Add a simple health check route
        Route::get('/health', function () {
            return response()->json(['status' => 'ok', 'timestamp' => now()]);
        });

        // Email preview route for development
        Route::get('/preview-email/{invitation}', function ($invitationId) {
            if (app()->environment('local')) {
                $invitation = \App\Models\Invitation::findOrFail($invitationId);

                // Generate proper URL based on company domain
                $company = $invitation->company;
                if ($company && $company->domain) {
                    $domain = $company->domain . config('tenant.domain.suffix', '.test');
                } else {
                    $domain = parse_url(config('app.url'), PHP_URL_HOST);
                }

                $protocol = request()->isSecure() ? 'https' : 'http';
                $acceptUrl = "{$protocol}://{$domain}/invitation/{$invitation->token}";

                return new \App\Mail\InvitationMail($invitation, $acceptUrl);
            }
            abort(404);
        })->name('preview.email');

        // Central domain invitation acceptance routes (using different path to avoid collision)
        Route::get('/central-invitation/{token}', function ($token) {
            return app(\App\Http\Controllers\AcceptInvitationController::class)->show($token);
        })->name('invitation.show');

        Route::post('/central-invitation/{token}', function ($token) {
            return app(\App\Http\Controllers\AcceptInvitationController::class)->store(request(), $token);
        })->name('invitation.store');

        // OAuth Social Login Routes
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
            ->where('provider', 'google|facebook|instagram');

        // Password Reset Routes
        Route::prefix('password')->name('password.')->group(function () {
            // Password reset request form (GET)
            Route::get('/reset', function () {
                return view('auth.passwords.email');
            })->name('request');

            // Password reset email submission (POST)
            Route::post('/email', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'sendResetLinkEmail'])
                ->name('email')
                ->middleware('throttle:5,1'); // Rate limit: 5 attempts per minute

            // Password reset form (GET)
            Route::get('/reset/{token}', [App\Http\Controllers\Auth\ResetPasswordController::class, 'showResetForm'])
                ->name('reset');

            // Password reset submission (POST)
            Route::post('/reset', [App\Http\Controllers\Auth\ResetPasswordController::class, 'reset'])
                ->name('update')
                ->middleware('throttle:5,1'); // Rate limit: 5 attempts per minute
        });
    });
