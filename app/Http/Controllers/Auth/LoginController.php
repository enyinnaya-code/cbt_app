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
        return view('index');
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

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->with('error', "Too many attempts. Try again in {$seconds} seconds.");
        }

        $user = User::where('email', $request->email)->first();

        // Admin-suspended accounts (and any locked by the old rule) stay blocked until re-activated.
        if ($user && $user->is_active == 0) {
            return back()->with('error', 'Your account is suspended.');
        }

        if (Auth::attempt($request->only('email', 'password'), $request->filled('remember'))) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate(); // Prevent session fixation
            return redirect()->route('dashboard');
        }

        RateLimiter::hit($throttleKey, 60);

        return back()->with('error', 'Invalid email or password.');
    }


    // Logout user
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
