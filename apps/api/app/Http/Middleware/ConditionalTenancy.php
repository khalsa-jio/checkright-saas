<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optimized conditional tenancy middleware with performance enhancements.
 *
 * Features:
 * - Domain-based routing with in-memory caching
 * - Performance optimizations for high-traffic scenarios
 * - Proper error handling and fallbacks
 * - Scalable for future hybrid tenancy approach
 */
class ConditionalTenancy
{
    private static array $domainCache = [];

    public function handle(Request $request, Closure $next): Response
    {
        $domain = $request->getHost();

        // Skip if no domain (CLI, malformed requests)
        if (empty($domain)) {
            return $next($request);
        }

        // Use in-memory cache for performance (reset per request)
        $cacheKey = 'domain_type_' . $domain;

        if (! isset(self::$domainCache[$cacheKey])) {
            $centralDomains = config('tenancy.central_domains', []);
            self::$domainCache[$cacheKey] = in_array($domain, $centralDomains, true);
        }

        $isCentralDomain = self::$domainCache[$cacheKey];

        // If this is a central domain, skip tenancy initialization
        if ($isCentralDomain) {
            return $next($request);
        }

        // For tenant domains, initialize tenancy only
        // Session configuration is handled by TenantSessionBootstrapper at the right time
        try {
            // Initialize tenancy middleware
            $tenancyMiddleware = new InitializeTenancyByDomain(
                app(Tenancy::class),
                app(DomainTenantResolver::class)
            );

            return $tenancyMiddleware->handle($request, $next);
        } catch (\Exception $e) {
            // Log the error for debugging
            logger()->error('ConditionalTenancy error', [
                'domain' => $domain,
                'error' => $e->getMessage(),
                'url' => $request->fullUrl(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw the exception to let Laravel handle it properly
            throw $e;
        }
    }

    // Removed configureTenantSessions() method
    // Session configuration is now handled by TenantSessionBootstrapper in the correct middleware order

    /**
     * Clear domain cache (useful for testing).
     */
    public static function clearCache(): void
    {
        self::$domainCache = [];
    }
}
