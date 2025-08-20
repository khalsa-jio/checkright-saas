<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Session management service for multi-tenant applications.
 *
 * Handles session isolation, cleanup, and cross-domain transitions
 * while preserving authentication state where needed.
 */
class SessionManager
{
    /**
     * Clean tenant-specific session data without affecting central domain auth.
     */
    public static function cleanTenantSession(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $session = $request->session();

        // Store critical central domain data before cleanup
        $authKey = 'login_web_' . sha1(config('app.key'));
        $preservedData = [
            '_token' => $session->get('_token'),
            $authKey => $session->get($authKey),
            '_previous' => $session->get('_previous'),
            '_flash' => $session->get('_flash'),
            'invitation_success' => $session->get('invitation_success'),
            'url.intended' => $session->get('url.intended'),
            'password_hash_web' => $session->get('password_hash_web'),
        ];

        // Remove tenant-specific session data only
        $tenantKeys = $session->all();
        foreach ($tenantKeys as $key => $value) {
            // Only remove tenant-specific keys, preserve auth and core session data
            if (str_starts_with($key, 'tenant_') ||
                str_starts_with($key, 'filament_') ||
                (str_starts_with($key, 'admin_') && ! in_array($key, array_keys($preservedData)))) {
                $session->forget($key);
            }
        }

        // Restore preserved central domain data
        foreach ($preservedData as $key => $value) {
            if ($value !== null) {
                $session->put($key, $value);
            }
        }

        $session->save();
    }

    /**
     * Prepare session for cross-domain transition.
     */
    public static function prepareCrossDomainTransition(Request $request, string $targetDomain): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $session = $request->session();
        $centralDomains = config('tenancy.central_domains', []);
        $isCentralTarget = in_array($targetDomain, $centralDomains, true);

        // Store current auth state for cross-domain preservation
        $authKey = 'login_web_' . sha1(config('app.key'));
        $currentAuth = $session->get($authKey);

        if ($currentAuth) {
            // Mark that we have valid authentication for this transition
            $session->put('cross_domain_auth_preserved', true);
            $session->put('cross_domain_transition_time', now()->timestamp);
        }

        // Set appropriate cookie settings for the target domain
        if (! $isCentralTarget) {
            // For tenant domains, ensure session isolation but allow redirects
            config([
                'session.same_site' => 'lax', // Allow cross-domain redirects
                'session.secure' => $request->isSecure(),
                'session.http_only' => true,
            ]);
        }

        // Save session before redirect
        $session->save();
    }

    /**
     * Clear all tenant-specific cookies.
     */
    public static function clearTenantCookies(): void
    {
        $cookies = $_COOKIE;

        foreach ($cookies as $name => $value) {
            if (str_contains($name, '_tenant_') || str_contains($name, 'filament')) {
                Cookie::queue(Cookie::forget($name));
            }
        }
    }

    /**
     * Check if current request is on central domain.
     */
    public static function isCentralDomain(Request $request): bool
    {
        $host = $request->getHost();
        $centralDomains = config('tenancy.central_domains', []);

        return in_array($host, $centralDomains, true);
    }

    /**
     * Validate and handle cross-domain session transitions.
     */
    public static function validateCrossDomainTransition(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        $session = $request->session();

        // Check if this is a valid cross-domain transition
        if ($session->has('cross_domain_auth_preserved')) {
            $transitionTime = $session->get('cross_domain_transition_time', 0);
            $maxTransitionAge = 300; // 5 minutes

            if ((now()->timestamp - $transitionTime) <= $maxTransitionAge) {
                // Valid transition, clean up transition markers
                $session->forget(['cross_domain_auth_preserved', 'cross_domain_transition_time']);

                return true;
            } else {
                // Expired transition, clean up
                $session->forget(['cross_domain_auth_preserved', 'cross_domain_transition_time']);
            }
        }

        return false;
    }

    /**
     * Get appropriate session configuration for domain.
     */
    public static function getSessionConfig(string $domain): array
    {
        $centralDomains = config('tenancy.central_domains', []);
        $isCentralDomain = in_array($domain, $centralDomains, true);

        if ($isCentralDomain) {
            return [
                'domain' => $domain,
                'cookie' => config('app.name', 'laravel') . '_central_session',
                'same_site' => 'lax',
                'http_only' => true,
                'secure' => app()->environment('production'),
            ];
        }

        // For tenant domains
        $domainParts = explode('.', $domain);
        $subdomain = $domainParts[0] ?? 'default';

        return [
            'domain' => $domain,
            'cookie' => config('app.name', 'laravel') . '_tenant_' . substr(md5($subdomain), 0, 8) . '_session',
            'same_site' => 'lax',
            'http_only' => true,
            'secure' => app()->environment('production'),
        ];
    }
}
