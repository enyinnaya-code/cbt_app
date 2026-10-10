<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After an admin resets a password to the shared default, that password may only be used to choose a new one: every other
 * page sends the person to Profile until they have.
 */
class MustChangePassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password && ! $request->routeIs('profile', 'profile.password', 'logout')) {
            return redirect()->route('profile')->with('error', 'Please choose a new password before you continue.');
        }

        return $next($request);
    }
}
