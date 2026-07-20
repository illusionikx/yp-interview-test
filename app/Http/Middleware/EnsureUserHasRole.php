<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * Aborts 403 when there is no authenticated user, or when the
     * authenticated user's role does not match the required role
     * parameter (D-07). Never a hidden-nav-only check — this is the
     * server-side enforcement point for RBAC-04.
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (! $request->user() || $request->user()->role->value !== $role) {
            abort(403);
        }

        return $next($request);
    }
}
