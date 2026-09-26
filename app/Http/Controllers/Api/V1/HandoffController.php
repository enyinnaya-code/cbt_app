<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Lets the app open the website already signed in, so a student who joined with Google (and so has no password)
 * can still unlock subjects. The app asks for a link; the link works once, for five minutes, and only leads to the
 * unlock and purchase pages.
 */
class HandoffController extends Controller
{
    private const PAGES = ['/checkout', '/orders', '/pricing'];

    /** POST /api/v1/web-link */
    public function link(Request $request): JsonResponse
    {
        $data = $request->validate([
            'page' => ['nullable', Rule::in(self::PAGES)],
            'exam' => ['nullable', Rule::exists('exams', 'slug')],
            'subjects' => ['nullable', 'array', 'max:40'],
            'subjects.*' => ['string', 'max:100'],
        ]);

        $user = $request->user();
        abort_if($user->canManageQuestions(), 403, 'Staff accounts sign in on the website.');

        $query = http_build_query(array_filter(['exam' => $data['exam'] ?? null, 'subjects' => $data['subjects'] ?? null]));
        $to = ($data['page'] ?? '/checkout') . ($query ? '?' . $query : '');

        $nonce = Str::random(40);
        Cache::put("handoff:{$nonce}", $user->id, now()->addMinutes(5));

        return response()->json([
            'url' => URL::temporarySignedRoute('app.handoff', now()->addMinutes(5), ['user' => $user->id, 'nonce' => $nonce, 'to' => $to]),
        ]);
    }

    /** GET /app-link/{user}: the link itself. Signed, single use, short lived. */
    public function open(Request $request, User $user)
    {
        $nonce = (string) $request->query('nonce');
        $to = (string) $request->query('to', '/checkout');

        // Single use: taking the nonce out of the cache spends it, so a copied link is worthless.
        abort_unless($nonce !== '' && Cache::pull("handoff:{$nonce}") === $user->id, 403, 'This link has already been used or has expired. Go back to the app and try again.');
        abort_if($user->canManageQuestions() || ! $user->is_active, 403);

        // Only ever lead to the shopping pages, whatever the link says.
        $path = parse_url($to, PHP_URL_PATH);
        abort_unless(in_array($path, self::PAGES, true), 403);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect($to);
    }
}
