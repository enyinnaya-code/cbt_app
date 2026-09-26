<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Verifies a Google ID token that the mobile app obtained from Google Sign-In.
 * Uses Google's tokeninfo endpoint, which checks the signature and expiry for us.
 */
class GoogleIdTokenVerifier
{
    /**
     * @return array{sub:string,email:string,name:string,picture:?string}|null  null when the token is not acceptable
     */
    public function verify(string $idToken): ?array
    {
        $clientIds = config('services.google.client_ids', []);
        if (! $clientIds) {
            return null;   // not configured: never accept anything
        }

        $response = Http::timeout(8)->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);
        if (! $response->ok()) {
            return null;
        }

        $t = $response->json();

        $valid = in_array($t['aud'] ?? null, $clientIds, true)
            && in_array($t['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)
            && filter_var($t['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && (int) ($t['exp'] ?? 0) > time()
            && ! empty($t['sub']) && ! empty($t['email']);

        if (! $valid) {
            return null;
        }

        return [
            'sub' => $t['sub'],
            'email' => strtolower($t['email']),
            'name' => $t['name'] ?? strtok($t['email'], '@'),
            'picture' => $t['picture'] ?? null,
        ];
    }

    public function isConfigured(): bool
    {
        return (bool) config('services.google.client_ids');
    }
}
