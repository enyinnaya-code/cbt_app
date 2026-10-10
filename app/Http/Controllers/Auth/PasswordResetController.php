<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/** "Forgot my password": a link goes to the email the account was registered with, and only that link can set a new password. */
class PasswordResetController extends Controller
{
    public function request()
    {
        return view('auth.forgot');
    }

    /**
     * Always answers the same way, whether or not the email has an account, so this page cannot be used to find out who
     * is registered. Suspended accounts are not sent a link.
     */
    public function email(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);
        $email = Str::lower(trim($data['email']));

        $user = User::where('email', $email)->first();

        if ($user && (int) $user->is_active === 1) {
            try {
                Password::sendResetLink(['email' => $email]);
            } catch (\Throwable $e) {
                // A mail server that is down must not tell the visitor anything: log it for whoever runs the server.
                Log::error('Password reset email could not be sent', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }
        }

        return back()->with('status', 'If that email has a TestaCBT account, we have sent a link to choose a new password. It can take a few minutes. Check your spam folder too.');
    }

    public function form(Request $request, string $token)
    {
        return view('auth.reset', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', PasswordRule::min(8)->letters()->numbers(), 'max:200', 'confirmed'],
        ]);

        $holder = User::where('email', Str::lower(trim($data['email'])))->first();
        if ($holder && (int) $holder->is_active === 0) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'This reset link is not valid any more. Ask for a new one from the sign-in page.']);
        }

        $status = Password::reset(
            ['email' => Str::lower(trim($data['email'])), 'password' => $data['password'], 'password_confirmation' => $request->input('password_confirmation'), 'token' => $data['token']],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'must_change_password' => false, 'remember_token' => Str::random(60)])->save();
                $user->tokens()->delete();   // every phone signs in again with the new password
                if (config('session.driver') === 'database') {
                    DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
                }

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            // The same words for a wrong, used or expired link, so nothing is given away.
            return back()->withInput($request->only('email'))->withErrors(['email' => 'This reset link is not valid any more. Ask for a new one from the sign-in page.']);
        }

        return redirect()->route('login')->with('success', 'Your password has been changed. Sign in with the new one.');
    }
}
