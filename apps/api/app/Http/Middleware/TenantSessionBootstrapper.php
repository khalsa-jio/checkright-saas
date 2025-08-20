<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optimized tenant session bootstrapper with enhanced performance and security.
 *
 * Features:
 * - Efficient session domain isolation
 * - Memory caching for performance
 * - Proper tenant/central domain separation
 * - Enhanced security with SameSite and Secure cookie settings
 * - Scalable for future hybrid tenancy
 */
class TenantSessionBootstrapper
{
    private static array $configCache = [];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip in testing environment to avoid interfering with tenant initialization
        if (app()->environment('testing')) {
            return $next($request);
        }

        $host = $request->getHost();

        // Validate host is not empty
        if (empty($host)) {
            return $next($request);
        }

        // CRITICAL: Exit early for central domains to prevent session conflicts
        $centralDomains = config('tenancy.central_domains', []);
        if (in_array($host, $centralDomains, true)) {
            return $next($request);
        }

        try {
            $this->configureSessions($host, $request);
            $this->handleSessionTransition($request);
        } catch (\Exception $e) {
            // Log the error but don't break the request
            logger()->warning('TenantSessionBootstrapper error', [
                'host' => $host,
                'error' => $e->getMessage(),
                'url' => $request->fullUrl(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return $next($request);
    }

    /**
     * Configure session settings based on domain type.
     */
    private function configureSessions(string $host, Request $request): void
    {
        // Use in-memory cache for performance within request
        // Allow reconfiguration for domain switching scenarios
        $cacheKey = 'session_config_' . $host;

        if (isset(self::$configCache[$cacheKey])) {
            // For tenant domains, allow reconfiguration if tenancy state changed
            $centralDomains = config('tenancy.central_domains', []);
            $isCentralDomain = in_array($host, $centralDomains, true);

            if ($isCentralDomain || tenancy()->initialized) {
                return; // Already configured and stable
            }
        }

        $centralDomains = config('tenancy.central_domains', []);
        $isCentralDomain = in_array($host, $centralDomains, true);

        if ($isCentralDomain) {
            $this->configureCentralDomainSessions($host, $request);
        } else {
            $this->configureTenantDomainSessions($host, $request);
        }

        // Mark as configured
        self::$configCache[$cacheKey] = true;
    }

    /**
     * Configure sessions for central domains.
     */
    private function configureCentralDomainSessions(string $host, Request $request): void
    {
        // Configure central domain sessions with specific domain isolation
        config([
            'session.domain' => $host,
            'session.cookie' => config('app.name', 'laravel') . '_central_session',
            'session.secure' => $request->isSecure(),
            'session.same_site' => 'lax', // Allow cross-site for OAuth flows and redirects
            'session.lifetime' => config('session.lifetime', 120),
            'session.http_only' => true, // XSS protection
            'session.encrypt' => config('session.encrypt', false),
        ]);
    }

    /**
     * Configure sessions for tenant domains.
     */
    private function configureTenantDomainSessions(string $host, Request $request): void
    {
        // For tenant domains, configure sessions immediately for proper isolation
        // The session configuration needs to be set before tenancy initialization

        // Extract potential tenant ID from domain for unique cookie naming
        $domainParts = explode('.', $host);
        $subdomain = $domainParts[0] ?? 'default';

        config([
            'session.domain' => $host,
            'session.cookie' => config('app.name', 'laravel') . '_tenant_' . substr(md5($subdomain), 0, 8) . '_session',
            'session.secure' => $request->isSecure(),
            'session.same_site' => 'lax', // Allow for cross-domain redirects during impersonation
            'session.lifetime' => config('session.lifetime', 120),
            'session.http_only' => true, // XSS protection
            'session.encrypt' => config('session.encrypt', false),
        ]);

        // Configure tenant-specific session table if using database sessions and tenancy is initialized
        if (config('session.driver') === 'database' && tenancy()->initialized) {
            config(['session.table' => 'tenant_sessions']);
        }
    }

    /**
     * Handle session transitions between domains.
     */
    private function handleSessionTransition(Request $request): void
    {
        // Only handle transitions if we have an active session
        if (! $request->hasSession()) {
            return;
        }

        $session = $request->session();
        $host = $request->getHost();
        $centralDomains = config('tenancy.central_domains', []);
        $isCentralDomain = in_array($host, $centralDomains, true);

        // Handle invitation success flash messages across domains
        if ($session->has('invitation_success')) {
            // For tenant domains, clear central domain specific session data
            // but preserve the success message for display
            if (! $isCentralDomain) {
                $successMessage = $session->get('invitation_success');
                // Clean tenant-specific data without affecting central auth
                \App\Services\SessionManager::cleanTenantSession($request);
                // Restore success message for display
                $session->flash('success', $successMessage);
            }
        }

        // For tenant domains, ensure session isolation from central domain
        if (! $isCentralDomain && tenancy()->initialized) {
            // Regenerate session ID for security without affecting central domain sessions
            $session->migrate(false);
        }
    }

    /**
     * Clear configuration cache (useful for testing).
     */
    public static function clearCache(): void
    {
        self::$configCache = [];
    }
}
