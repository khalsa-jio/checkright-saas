<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DeviceManagementController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\TokenManagementController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public authentication routes
Route::post('/invitations/{token}/accept', [AuthController::class, 'acceptInvitation'])
    ->name('invitations.accept');

Route::post('/auth/login', [AuthController::class, 'login'])
    ->name('auth.login');

// Mobile OAuth routes (public, no authentication required for initialization)
Route::prefix('mobile/oauth')->name('mobile.oauth.')->group(function () {
    Route::post('/{provider}/initialize', [SocialAuthController::class, 'mobileInitialize'])
        ->name('initialize');
    Route::post('/{provider}/callback', [SocialAuthController::class, 'mobileCallback'])
        ->name('callback');
});

// Protected routes requiring authentication
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/auth/logout', [AuthController::class, 'logout'])
        ->name('auth.logout');
});

// Mobile API routes with enhanced security
Route::prefix('mobile')->middleware(['auth:sanctum', \App\Http\Middleware\MobileSecurityMiddleware::class])->group(function () {
    // User Management routes for mobile
    Route::prefix('users')->name('mobile.users.')->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])
            ->name('index');
        Route::get('/{user}', [UserManagementController::class, 'show'])
            ->name('show');
        Route::put('/{user}', [UserManagementController::class, 'update'])
            ->name('update');
        Route::delete('/{user}', [UserManagementController::class, 'destroy'])
            ->name('destroy');
        Route::post('/{user}/restore', [UserManagementController::class, 'restore'])
            ->name('restore');
        Route::post('/{user}/force-password-reset', [UserManagementController::class, 'forcePasswordReset'])
            ->name('force-password-reset');
        Route::post('/bulk-deactivate', [UserManagementController::class, 'bulkDeactivate'])
            ->name('bulk-deactivate');
    });

    // Invitation Management routes for mobile
    Route::prefix('invitations')->name('mobile.invitations.')->group(function () {
        Route::post('/', [UserManagementController::class, 'invite'])
            ->name('invite');
        Route::get('/pending', [UserManagementController::class, 'pendingInvitations'])
            ->name('pending');
        Route::post('/{invitation}/resend', [UserManagementController::class, 'resendInvitation'])
            ->name('resend');
        Route::delete('/{invitation}', [UserManagementController::class, 'cancelInvitation'])
            ->name('cancel');
    });

    // Device Management routes for mobile
    Route::prefix('devices')->name('mobile.devices.')->group(function () {
        Route::post('/register', [DeviceManagementController::class, 'register'])
            ->name('register');
        Route::get('/', [DeviceManagementController::class, 'index'])
            ->name('index');
        Route::post('/{deviceId}/trust', [DeviceManagementController::class, 'trust'])
            ->name('trust');
        Route::delete('/{deviceId}/trust', [DeviceManagementController::class, 'revokeTrust'])
            ->name('revoke-trust');
        Route::delete('/{deviceId}', [DeviceManagementController::class, 'destroy'])
            ->name('destroy');
        Route::get('/security-status', [DeviceManagementController::class, 'securityStatus'])
            ->name('security-status');
    });

    // Token Management routes for mobile
    Route::prefix('tokens')->name('mobile.tokens.')->group(function () {
        Route::post('/generate', [TokenManagementController::class, 'generateTokens'])
            ->name('generate');
        Route::post('/refresh', [TokenManagementController::class, 'refreshTokens'])
            ->name('refresh')
            ->withoutMiddleware('auth:sanctum'); // Refresh doesn't require active auth
        Route::get('/info', [TokenManagementController::class, 'getTokenInfo'])
            ->name('info');
        Route::delete('/device', [TokenManagementController::class, 'revokeDeviceTokens'])
            ->name('revoke-device');
        Route::delete('/all', [TokenManagementController::class, 'revokeAllTokens'])
            ->name('revoke-all');
        Route::get('/should-rotate', [TokenManagementController::class, 'shouldRotate'])
            ->name('should-rotate');
        Route::get('/validate', [TokenManagementController::class, 'validateToken'])
            ->name('validate');
    });
});

// Legacy Web API routes (without mobile security middleware for backward compatibility)
Route::middleware('auth:sanctum')->group(function () {
    // User Management routes
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])
            ->name('index');
        Route::get('/{user}', [UserManagementController::class, 'show'])
            ->name('show');
        Route::put('/{user}', [UserManagementController::class, 'update'])
            ->name('update');
        Route::delete('/{user}', [UserManagementController::class, 'destroy'])
            ->name('destroy');
        Route::post('/{user}/restore', [UserManagementController::class, 'restore'])
            ->name('restore');
        Route::post('/{user}/force-password-reset', [UserManagementController::class, 'forcePasswordReset'])
            ->name('force-password-reset');
        Route::post('/bulk-deactivate', [UserManagementController::class, 'bulkDeactivate'])
            ->name('bulk-deactivate');
    });

    // Invitation Management routes
    Route::prefix('invitations')->name('invitations.')->group(function () {
        Route::post('/', [UserManagementController::class, 'invite'])
            ->name('invite');
        Route::get('/pending', [UserManagementController::class, 'pendingInvitations'])
            ->name('pending');
        Route::post('/{invitation}/resend', [UserManagementController::class, 'resendInvitation'])
            ->name('resend');
        Route::delete('/{invitation}', [UserManagementController::class, 'cancelInvitation'])
            ->name('cancel');
    });
});
