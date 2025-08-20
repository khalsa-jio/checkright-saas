<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class SocialLoginLogger
{
    /**
     * Log successful social login attempt.
     */
    public static function logSuccessfulLogin(User $user, string $provider, array $context = []): void
    {
        $logData = [
            'event' => 'social_login_success',
            'user_id' => $user->id,
            'email' => $user->email,
            'provider' => $provider,
            'tenant_id' => $user->tenant_id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now(),
            'context' => $context,
        ];

        // Log for application monitoring
        Log::info('Social login successful', $logData);

        // Store in activity log table if you have one
        // ActivityLog::create([
        //     'user_id' => $user->id,
        //     'event' => 'social_login_success',
        //     'properties' => $logData,
        //     'created_at' => now(),
        // ]);
    }

    /**
     * Log failed social login attempt.
     */
    public static function logFailedLogin(string $provider, string $error, array $context = []): void
    {
        $logData = [
            'event' => 'social_login_failed',
            'provider' => $provider,
            'error' => $error,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now(),
            'context' => $context,
        ];

        // Log for security monitoring
        Log::warning('Social login failed', $logData);
    }

    /**
     * Log OAuth state validation failure.
     */
    public static function logStateValidationFailure(string $provider, array $context = []): void
    {
        $logData = [
            'event' => 'oauth_state_validation_failed',
            'provider' => $provider,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now(),
            'context' => $context,
        ];

        // Log for security monitoring
        Log::warning('OAuth state validation failed', $logData);
    }

    /**
     * Log social account linking/unlinking events.
     */
    public static function logAccountAction(User $user, string $provider, string $action, array $context = []): void
    {
        $logData = [
            'event' => "social_account_{$action}",
            'user_id' => $user->id,
            'email' => $user->email,
            'provider' => $provider,
            'action' => $action,
            'tenant_id' => $user->tenant_id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now(),
            'context' => $context,
        ];

        Log::info("Social account {$action}", $logData);
    }
}
