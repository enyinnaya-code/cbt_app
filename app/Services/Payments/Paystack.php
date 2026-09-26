<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for Paystack's hosted checkout: start a payment, then ask Paystack whether it really happened.
 * Amounts are whole naira in our database and kobo (x100) at Paystack.
 */
class Paystack
{
    public static function configured(): bool
    {
        return filled(config('services.paystack.secret'));
    }

    /**
     * Starts a payment and returns the Paystack page to send the student to.
     *
     * @throws RuntimeException when Paystack cannot be reached or refuses
     */
    public function initialize(Order $order, string $email, string $callbackUrl): string
    {
        $response = $this->request()->post('/transaction/initialize', [
            'email' => $email,
            'amount' => $order->amount * 100,
            'currency' => $order->currency,
            'reference' => $order->reference,
            'callback_url' => $callbackUrl,
            'metadata' => ['order_id' => $order->id, 'user_id' => $order->user_id],
        ]);

        $url = $response->json('data.authorization_url');

        if (! $response->successful() || ! $response->json('status') || ! $url) {
            throw new RuntimeException($response->json('message') ?: 'Paystack did not accept the payment.');
        }

        return $url;
    }

    /**
     * Asks Paystack what happened to a payment. Trust this, never the browser's redirect.
     *
     * @return array<string,mixed>|null the transaction data, or null when Paystack could not be reached
     */
    public function verify(string $reference): ?array
    {
        try {
            $response = $this->request()->get('/transaction/verify/' . rawurlencode($reference));
        } catch (ConnectionException) {
            return null;
        }

        return $response->successful() && $response->json('status') ? (array) $response->json('data') : null;
    }

    /** True when the body really came from Paystack: HMAC-SHA512 of the raw body with our secret key. */
    public static function validSignature(string $rawBody, ?string $signature): bool
    {
        $secret = (string) config('services.paystack.secret');

        return $secret !== '' && $signature !== null && hash_equals(hash_hmac('sha512', $rawBody, $secret), $signature);
    }

    private function request()
    {
        return Http::withToken((string) config('services.paystack.secret'))
            ->baseUrl(rtrim((string) config('services.paystack.base_url'), '/'))
            ->acceptJson()->timeout(20)->retry(2, 300, throw: false);
    }
}
