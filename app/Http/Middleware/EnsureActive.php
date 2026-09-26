<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stops suspended accounts from using tokens they already hold.
 */
class EnsureActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (int) $user->is_active === 0) {
            $user->tokens()->delete();
            return response()->json(['message' => 'Your account is suspended.'], 403);
        }

        return $next($request);
    }
}
