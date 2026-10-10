<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

/**
 * The outgoing email account (SMTP), set by an admin in Site settings instead of in a file on the server, so it can be
 * changed when the provider or the password changes. The password is kept encrypted with the app key and is never sent
 * back to the browser. When nothing is set, mail keeps going where .env says (the log file, on a developer's machine).
 */
class MailSettings
{
    public const ENCRYPTIONS = ['tls' => 'TLS (STARTTLS, usually port 587)', 'ssl' => 'SSL (usually port 465)'];

    private const KEYS = ['mail.host', 'mail.port', 'mail.encryption', 'mail.username', 'mail.from_address', 'mail.from_name'];

    public function configured(): bool
    {
        return Setting::get('mail.host') !== null && Setting::get('mail.from_address') !== null;
    }

    /** What the form shows. The password is only ever reported as saved or not. */
    public function form(): array
    {
        $values = [];
        foreach (self::KEYS as $key) { $values[$key] = Setting::get($key, ''); }

        return $values + ['mail.has_password' => Setting::get('mail.password') !== null];
    }

    /**
     * @param  array{host:string,port:int|string,encryption:string,username?:?string,password?:?string,clear_password?:mixed,from_address:string,from_name?:?string}  $data
     *                A blank password keeps the saved one; clear_password removes it.
     */
    public function save(array $data): void
    {
        $values = [
            'mail.host' => strtolower(trim($data['host'])),
            'mail.port' => (string) (int) $data['port'],
            'mail.encryption' => $data['encryption'],
            'mail.username' => $data['username'] ?? null,
            'mail.from_address' => strtolower(trim($data['from_address'])),
            'mail.from_name' => $data['from_name'] ?? null,
        ];

        if (! empty($data['clear_password'])) {
            $values['mail.password'] = null;
        } elseif (isset($data['password']) && $data['password'] !== '') {
            $values['mail.password'] = Crypt::encryptString($data['password']);
        }

        Setting::put($values);
    }

    /** Forgets the account: mail goes back to wherever .env says. */
    public function clear(): void
    {
        Setting::put(array_fill_keys(array_merge(self::KEYS, ['mail.password']), null));
    }

    public function password(): ?string
    {
        $stored = Setting::get('mail.password');
        if ($stored === null) { return null; }

        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            return null;   // saved under another app key: treated as not set rather than breaking every email
        }
    }

    /** Points Laravel's mailer at the saved account. Does nothing when no account has been saved. */
    public function apply(): void
    {
        if (! $this->configured()) { return; }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => Setting::get('mail.host'),
            'mail.mailers.smtp.port' => Setting::int('mail.port', 587),
            'mail.mailers.smtp.scheme' => Setting::get('mail.encryption') === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.username' => Setting::get('mail.username'),
            'mail.mailers.smtp.password' => $this->password(),
            'mail.mailers.smtp.timeout' => 15,
            'mail.from.address' => Setting::get('mail.from_address'),
            'mail.from.name' => Setting::get('mail.from_name', config('app.name')),
        ]);

        // A mailer built earlier in this request would still hold the old account.
        app('mail.manager')->forgetMailers();
    }

    /**
     * Sends one real email through the saved account so the admin knows it works. Returns null on success, or a short
     * plain-language reason. The reason never contains the password, whatever the mail server said.
     */
    public function sendTest(string $to): ?string
    {
        $this->apply();

        try {
            Mail::to($to)->send(new \App\Mail\SettingsTestMail);
            return null;
        } catch (\Throwable $e) {
            return $this->explain($e->getMessage());
        }
    }

    public function explain(string $raw): string
    {
        $password = $this->password();
        if ($password !== null && $password !== '') { $raw = str_replace($password, '••••••', $raw); }

        $raw = preg_replace('/\s+/', ' ', $raw) ?? $raw;

        return match (true) {
            str_contains($raw, 'Connection could not be established') || str_contains($raw, 'Connection refused') || str_contains($raw, 'timed out')
                => 'Could not connect to the mail server. Check the host and port, and that the server allows connections from here.',
            str_contains($raw, 'Expected response code "235"') || str_contains($raw, 'Authentication') || str_contains($raw, 'authenticat')
                => 'The mail server refused the username or password.',
            str_contains($raw, 'STARTTLS') || str_contains($raw, 'SSL') || str_contains($raw, 'TLS')
                => 'The secure connection failed. Try the other encryption choice (TLS on port 587, SSL on port 465).',
            default => 'The mail server did not accept the email: ' . mb_substr($raw, 0, 160),
        };
    }
}
