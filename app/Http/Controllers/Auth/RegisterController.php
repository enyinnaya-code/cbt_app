<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RegisterController extends Controller
{
    public function showForm()
    {
        return view('auth.register', ['exams' => Exam::where('is_active', true)->orderBy('sort_order')->get()]);
    }

    /**
     * Public sign-up. Always creates a student; examiners and admins are appointed by an admin.
     */
    public function register(Request $request)
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);   // uniqueness must not depend on letter case

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'preferred_exams' => ['nullable', 'array'],
            'preferred_exams.*' => ['string', Rule::exists('exams', 'slug')],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'user_type' => 4,   // legacy student type; the saving hook sets role = student
            'preferred_exams' => $data['preferred_exams'] ?? [],
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
