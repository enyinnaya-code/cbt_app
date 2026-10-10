<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * The Paystack secret key, set by an admin in Site settings instead of in a file on the server, so it can be changed when
 * the key does (or swapped between test and live). It is kept encrypted with the app key and is never shown again, only
 * its type (test or live) and its last four characters. A key saved here wins over PAYSTACK_SECRET_KEY in .env.
 */
class PaystackSettings
{
    public const PATTERN = '/^sk_(test|live)_[A-Za-z0-9]{10,}$/';

    public function saved(): ?string
    {
        $stored = Setting::get('paystack.secret');
        if ($stored === null) { return null; }

        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            return null;   // saved under another app key: treated as not set, so .env still works
        }
    }

    public function save(string $key): void
    {
        Setting::put(['paystack.secret' => Crypt::encryptString(trim($key))]);
        $this->apply();
    }

    public function clear(): void
    {
        Setting::put(['paystack.secret' => null]);
    }

    /** Makes the saved key the one the payment code uses. Does nothing when none is saved (then .env is used). */
    public function apply(): void
    {
        if (($key = $this->saved()) !== null) {
            config(['services.paystack.secret' => $key]);
        }
    }

    /** What the form may show: never the key itself. */
    public function form(): array
    {
        $key = (string) config('services.paystack.secret');

        return [
            'on' => $key !== '',
            'mode' => $key === '' ? null : (str_starts_with($key, 'sk_live_') ? 'live' : (str_starts_with($key, 'sk_test_') ? 'test' : 'unknown')),
            'tail' => $key === '' ? null : substr($key, -4),
            'source' => $this->saved() !== null ? 'settings' : ($key !== '' ? 'env' : null),
        ];
    }

    /**
     * Asks Paystack about a payment that does not exist. Nothing is charged or changed: a real key is answered with "not
     * found", a wrong one is refused. Returns null when the key works, or a short reason.
     */
    public function check(): ?string
    {
        $key = (string) config('services.paystack.secret');
        if ($key === '') { return 'No Paystack key is set yet.'; }

        try {
            $response = Http::withToken($key)->baseUrl(rtrim((string) config('services.paystack.base_url'), '/'))
                ->acceptJson()->timeout(15)->get('/transaction/verify/' . 'testacbt-key-check');
        } catch (ConnectionException) {
            return 'Could not reach Paystack from this server. Check the server\'s internet connection and firewall.';
        }

        return match (true) {
            $response->status() === 401 => 'Paystack refused this key. Copy the secret key again from Settings, API Keys & Webhooks.',
            $response->status() === 404 || $response->successful() => null,
            default => 'Paystack answered unexpectedly (' . $response->status() . '). Try again in a moment.',
        };
    }
}
