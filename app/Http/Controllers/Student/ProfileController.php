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

        $rules = ['password' => ['required', 'string', 'min:8', 'max:200', 'confirmed']];
        // A Google-only account has no password yet, so it can set one without proving an old one.
        if ($user->password) {
            $rules['current_password'] = ['required', 'string'];
        }
        $data = $request->validateWithBag('password', $rules);

        if ($user->password && ! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'That is not your current password.'], 'password');
        }

        $user->update(['password' => $data['password']]);
        $user->tokens()->delete();   // sign the phone app out everywhere; this browser stays signed in

        return back()->with('success', 'Password changed.');
    }
}
