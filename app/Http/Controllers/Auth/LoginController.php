<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use App\Models\User;

class LoginController extends Controller
{
    // Show the login form
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        // Validate the form data
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Throttle per email + IP. This replaces the old "3 wrong passwords suspends the account" rule,
        // which on a public app would let anyone lock other people out.
        $throttleKey = Str::lower($request->email) . '|' . $request->ip();

        // One address trying many different accounts is also stopped, not only many tries on one account.
        $ipKey = 'login-ip|' . $request->ip();

        foreach ([[$throttleKey, 5], [$ipKey, 30]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $seconds = RateLimiter::availableIn($key);
                return back()->with('error', "Too many attempts. Try again in {$seconds} seconds.");
            }
        }

        if (Auth::attempt($request->only('email', 'password'), $request->filled('remember'))) {
            // Admin-suspended accounts stay blocked until re-activated. This is only said to someone who knows the password,
            // so the sign-in page cannot be used to find out which emails have accounts.
            if ((int) Auth::user()->is_active === 0) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->with('error', 'Your account is suspended.');
            }

            RateLimiter::clear($throttleKey);
            $request->session()->regenerate(); // Prevent session fixation
            return redirect()->route('dashboard');
        }

        RateLimiter::hit($throttleKey, 60);
        RateLimiter::hit($ipKey, 60);

        return back()->with('error', 'Invalid email or password.');
    }


    // Logout user
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('welcome');
    }
}
