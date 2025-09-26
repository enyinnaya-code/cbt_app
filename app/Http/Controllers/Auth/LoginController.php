<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
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

        // Get the user by email
        $user = User::where('email', $request->email)->first();

        // Check if the user exists
        if ($user) {
            // If the user is suspended
            if ($user->is_active == 0) {
                return back()->with('error', 'Your account is suspended.');
            }

            // Check if the user has remaining attempts
            if ($user->login_attempts > 0) {
                // Attempt to log the user in
                $credentials = $request->only('email', 'password');

                if (Auth::attempt($credentials, $request->filled('remember'))) {
                    // Reset login attempts on successful login
                    $user->update(['login_attempts' => 3]);
                    $request->session()->regenerate(); // Prevent session fixation
                    return redirect()->route('dashboard'); // Redirect to dashboard on success
                } else {
                    // Subtract 1 from login attempts on failure
                    $user->decrement('login_attempts');

                    // If attempts reach 0, suspend the account
                    if ($user->login_attempts == 0) {
                        $user->update(['is_active' => 0]);
                        return back()->with('error', 'Your account is suspended due to multiple failed login attempts.');
                    }

                    // Show the error with the remaining attempts
                    return back()->with('error', 'Incorrect password. You have ' . $user->login_attempts . ' remaining attempts.');
                }
            } else {
                // Account is locked out, show suspended message
                return back()->with('error', 'Your account is suspended due to multiple failed login attempts.');
            }
        } else {
            // If the user does not exist
            return back()->with('error', 'Invalid email or password.');
        }
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
