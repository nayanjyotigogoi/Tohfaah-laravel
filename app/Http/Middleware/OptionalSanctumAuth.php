<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OptionalSanctumAuth
{
    /**
     * Attempt Sanctum authentication IF a Bearer token is present.
     * Do NOT block the request if no token exists.
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->bearerToken()) {
            Auth::shouldUse('sanctum');
            Auth::guard('sanctum')->user();
        }

        return $next($request);
    }
}
