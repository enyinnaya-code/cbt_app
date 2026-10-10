<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('student.profile', [
            'user' => $request->user(),
            'exams' => Exam::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'preferred_exams' => ['nullable', 'array'],
            'preferred_exams.*' => ['string', Rule::exists('exams', 'slug')],
            'target_exam' => ['nullable', Rule::exists('exams', 'slug')],
            'target_exam_date' => ['nullable', 'date', 'after_or_equal:today', 'before:' . now()->addYears(3)->toDateString()],
            'explanation_language' => ['required', 'in:en,pcm'],
        ]);

        $request->user()->update([
            'name' => $data['name'],
            'preferred_exams' => $data['preferred_exams'] ?? [],
            'target_exam' => $data['target_exam'] ?? null,
            'target_exam_date' => $data['target_exam_date'] ?? null,
            'explanation_language' => $data['explanation_language'],
        ]);

        return back()->with('success', 'Your settings are saved.');
    }

    public function password(Request $request)
    {
        $user = $request->user();

        $rules = ['password' => ['required', 'string', \Illuminate\Validation\Rules\Password::min(8)->letters()->numbers(), 'max:200', 'confirmed']];
        // A Google-only account has no password yet, and a just-reset account has just used the default to sign in:
        // neither needs to prove an old one. Everyone else does.
        $mustChange = (bool) $user->must_change_password;
        if ($user->password && ! $mustChange) {
            $rules['current_password'] = ['required', 'string'];
        }
        $data = $request->validateWithBag('password', $rules);

        if ($data['password'] === \App\Http\Controllers\Console\UserController::DEFAULT_PASSWORD) {
            return back()->withErrors(['password' => 'Please choose a password of your own, not the shared one.'], 'password');
        }

        if ($user->password && ! $mustChange && ! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'That is not your current password.'], 'password');
        }

        $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();
        $user->tokens()->delete();   // sign the phone app out everywhere; this browser stays signed in

        return back()->with('success', 'Password changed.');
    }
}
