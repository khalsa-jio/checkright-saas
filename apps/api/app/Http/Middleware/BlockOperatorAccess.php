<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockOperatorAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // Block operators from accessing admin dashboard
        if ($user && $user->isOperator()) {
            abort(403, 'Operators are not allowed to access the admin dashboard. Please use the mobile app.');
        }

        return $next($request);
    }
}
