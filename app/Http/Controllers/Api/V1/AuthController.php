<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GoogleIdTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    private const MAX_DEVICES = 10;

    public function register(Request $request): JsonResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);   // uniqueness must not depend on letter case

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:200'],
            'device_name' => ['required', 'string', 'max:60'],
            'preferred_exams' => ['nullable', 'array'],
            'preferred_exams.*' => ['string', Rule::exists('exams', 'slug')],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => $data['password'],
            'user_type' => 4,   // legacy student type; the saving hook sets role = student
            'preferred_exams' => $data['preferred_exams'] ?? [],
        ]);

        return $this->tokenResponse($user, $data['device_name'], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:60'],
        ]);

        $key = 'api-login|' . Str::lower($data['email']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['message' => 'Too many attempts. Try again in ' . RateLimiter::availableIn($key) . ' seconds.'], 429);
        }

        $user = User::where('email', $data['email'])->first();

        // Google-only accounts have no password, so they can never match here.
        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        if ((int) $user->is_active === 0) {
            return response()->json(['message' => 'Your account is suspended.'], 403);
        }

        RateLimiter::clear($key);

        return $this->tokenResponse($user, $data['device_name']);
    }

    public function google(Request $request, GoogleIdTokenVerifier $verifier): JsonResponse
    {
        $data = $request->validate([
            'id_token' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:60'],
        ]);

        if (! $verifier->isConfigured()) {
            return response()->json(['message' => 'Google sign-in is not available yet.'], 503);
        }

        $google = $verifier->verify($data['id_token']);
        if (! $google) {
            return response()->json(['message' => 'Google sign-in failed. Please try again.'], 401);
        }

        $user = User::where('google_id', $google['sub'])->first();

        if (! $user) {
            $user = User::where('email', $google['email'])->first();

            if ($user) {
                // Never let a Google login attach itself to a staff account.
                if ($user->role !== User::ROLE_STUDENT) {
                    return response()->json(['message' => 'This account signs in with email and password.'], 403);
                }

                $user->google_id = $google['sub'];
                $user->avatar_url ??= $google['picture'];

                // Email addresses are not verified at sign-up. If this one never was, someone else may have
                // registered it first, so drop their password and sessions: Google proves who owns the address.
                if (! $user->email_verified_at) {
                    $user->password = null;
                    $user->email_verified_at = now();
                    $user->tokens()->delete();
                }
                $user->save();
            } else {
                // forceFill: google_id and email_verified_at are deliberately not mass-assignable.
                $user = (new User)->forceFill([
                    'name' => $google['name'],
                    'email' => $google['email'],
                    'password' => null,
                    'user_type' => 4,
                    'google_id' => $google['sub'],
                    'avatar_url' => $google['picture'],
                    'email_verified_at' => now(),
                ]);
                $user->save();
            }
        }

        // A user created a moment ago has no is_active loaded yet (the column default applies in the database).
        if ($user->is_active !== null && (int) $user->is_active === 0) {
            return response()->json(['message' => 'Your account is suspended.'], 403);
        }

        return $this->tokenResponse($user, $data['device_name']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    private function tokenResponse(User $user, string $deviceName, int $status = 200): JsonResponse
    {
        // One token per phone: signing in again on the same device replaces its old token.
        $user->tokens()->where('name', $deviceName)->delete();

        // Keep the newest few; slice in PHP because OFFSET without LIMIT is invalid SQL on MySQL.
        $extra = $user->tokens()->orderByDesc('id')->pluck('id')->slice(self::MAX_DEVICES - 1);
        if ($extra->isNotEmpty()) {
            $user->tokens()->whereIn('id', $extra)->delete();
        }

        return response()->json([
            'token' => $user->createToken($deviceName)->plainTextToken,
            'user' => $this->userPayload($user),
        ], $status);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'avatar_url' => $user->avatar_url,
            'preferred_exams' => $user->preferred_exams ?? [],
            'has_password' => $user->password !== null,
        ];
    }
}
