<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackLastLogin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Update last login time if user is authenticated and this is a successful response
        if (auth()->check() && $response->getStatusCode() === 200) {
            $user = auth()->user();

            // Only update if last login is more than 5 minutes ago to avoid constant updates
            if (! $user->last_login_at || $user->last_login_at->diffInMinutes(now()) > 5) {
                $user->update(['last_login_at' => now()]);
            }
        }

        return $response;
    }
}
