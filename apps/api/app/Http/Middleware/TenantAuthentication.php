<?php

namespace App\Http\Middleware;

use App\Services\SessionManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant authentication middleware.
 *
 * Handles authentication for tenant domains with proper session isolation
 * and cross-domain authentication state management.
 */
class TenantAuthentication
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if not a tenant domain or tenancy not initialized
        if (SessionManager::isCentralDomain($request) || ! tenancy()->initialized) {
            return $next($request);
        }

        // Check if user is authenticated for this tenant
        $user = Auth::user();

        if ($user && $this->isUserAuthorizedForTenant($user)) {
            // User is properly authenticated for this tenant
            return $next($request);
        }

        // Clear any stale tenant session data
        SessionManager::cleanTenantSession($request);

        // Redirect to tenant login or central domain login
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // For web requests, redirect to appropriate login
        $loginUrl = $this->getTenantLoginUrl($request);

        return redirect()->guest($loginUrl);
    }

    /**
     * Check if user is authorized for current tenant.
     */
    private function isUserAuthorizedForTenant($user): bool
    {
        if (! $user) {
            return false;
        }

        $tenantId = tenant('id');

        // Check if user belongs to this tenant
        return $user->tenant_id === $tenantId ||
               $user->hasRole('super_admin') ||
               $user->companies()->where('companies.id', $tenantId)->exists();
    }

    /**
     * Get appropriate login URL for tenant.
     */
    private function getTenantLoginUrl(Request $request): string
    {
        // For tenant domains, use Filament admin login
        return '/admin/login';
    }
}
