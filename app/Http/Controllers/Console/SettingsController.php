<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\ContentPack;
use App\Models\Exam;
use App\Models\Setting;
use App\Models\Subject;
use App\Services\MailSettings;
use App\Services\PackBuilder;
use Illuminate\Http\Request;

/** Site-wide settings an admin can change without a developer. */
class SettingsController extends Controller
{
    /** Where an uploaded Android app is kept, inside the public folder so the web server can send it directly. */
    public const APK_PATH = \App\Http\Controllers\Site\HomeController::UPLOAD_PATH;

    /** Setting key => validation rule. Only these keys can be saved from the form. */
    private const FIELDS = [
        'app.play_store_url' => ['nullable', 'url:https', 'max:500'],
        'app.apk_url' => ['nullable', 'url:http,https', 'max:500'],
        'app.apk_version' => ['nullable', 'string', 'max:20'],
        'seo.google_verification' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9_\-]*$/'],
        'seo.bing_verification' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9_\-]*$/'],
        'social.facebook' => ['nullable', 'url:https', 'max:300'],
        'social.x' => ['nullable', 'url:https', 'max:300'],
        'social.instagram' => ['nullable', 'url:https', 'max:300'],
        'social.youtube' => ['nullable', 'url:https', 'max:300'],
        'support.email' => ['nullable', 'email', 'max:160'],
        'support.whatsapp' => ['nullable', 'string', 'max:40'],
        'bank.name' => ['nullable', 'string', 'max:80'],
        'bank.account_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9 ]*$/'],
        'bank.account_name' => ['nullable', 'string', 'max:120'],
        'bank.note' => ['nullable', 'string', 'max:300'],
        'pricing.default_price' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        'pricing.free_questions' => ['nullable', 'integer', 'min:0', 'max:1000'],
        'pricing.access_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
    ];

    public function edit()
    {
        return view('console.settings', [
            'values' => collect(array_keys(self::FIELDS))->mapWithKeys(fn ($key) => [$key => Setting::get($key, '')])->all(),
            'paystack' => \App\Services\Payments\Paystack::configured(),
            'apk' => \App\Http\Controllers\Site\HomeController::apkFiles(),
            'uploadLimit' => self::uploadLimitBytes(),
            'mail' => app(MailSettings::class)->form(),
            'mailEncryptions' => MailSettings::ENCRYPTIONS,
        ]);
    }

    public function update(Request $request, PackBuilder $packs)
    {
        // Form names use underscores (dots are awkward in HTML); map them back to the setting keys.
        $rules = [];
        foreach (self::FIELDS as $key => $rule) { $rules[str_replace('.', '_', $key)] = $rule; }

        $rules['apk_file'] = ['nullable', 'file', 'extensions:apk', 'max:307200'];
        $rules['remove_apk'] = ['nullable', 'boolean'];

        $data = $request->validate($rules, [
            'apk_file.max' => 'The app file must be 300 MB or smaller.',
            'apk_file.extensions' => 'Choose the .apk file of the Android app.',
            'apk_file.uploaded' => 'The file did not upload. Your server accepts files up to ' . self::formatBytes(self::uploadLimitBytes()) . '. Ask your host to raise upload_max_filesize and post_max_size, or link to the file instead.',
            'seo_google_verification.regex' => 'Paste only the verification code, not the whole tag.',
            'seo_bing_verification.regex' => 'Paste only the verification code, not the whole tag.',
            '*.url' => 'That link must start with https://',
            'bank_account_number.regex' => 'The account number can only contain digits.',
        ]);

        $this->handleApk($request);

        $pricingBefore = [Setting::get('pricing.default_price'), Setting::get('pricing.free_questions')];

        Setting::put(collect(self::FIELDS)->mapWithKeys(fn ($rule, $key) => [$key => $data[str_replace('.', '_', $key)] ?? null])->all());

        // The default price and free-question count decide what goes into the free offline packs.
        if ($pricingBefore !== [Setting::get('pricing.default_price'), Setting::get('pricing.free_questions')]) {
            foreach (ContentPack::current()->tier(ContentPack::FULL)->get(['exam_id', 'subject_id']) as $pair) {
                $exam = Exam::find($pair->exam_id);
                $subject = Subject::find($pair->subject_id);
                if ($exam && $subject) { $packs->buildFree($exam, $subject); }
            }
        }

        return back()->with('success', 'Settings saved.');
    }

    /** The outgoing email account. A blank password keeps the saved one; the password is never shown again. */
    public function mail(Request $request, MailSettings $mail)
    {
        if ($request->boolean('forget_mail')) {
            $mail->clear();

            return redirect()->to(route('console.settings.edit') . '#email')->with('success', 'The email account was removed. Emails go back to the server\'s own setting.');
        }

        $data = $request->validate([
            'mail_host' => ['required', 'string', 'max:190', 'regex:/^[A-Za-z0-9]([A-Za-z0-9.\-]*[A-Za-z0-9])?$/'],
            'mail_port' => ['required', 'integer', 'between:1,65535'],
            'mail_encryption' => ['required', 'in:' . implode(',', array_keys(MailSettings::ENCRYPTIONS))],
            'mail_username' => ['nullable', 'string', 'max:190'],
            'mail_password' => ['nullable', 'string', 'max:190'],
            'clear_mail_password' => ['nullable', 'boolean'],
            'mail_from_address' => ['required', 'email', 'max:190'],
            'mail_from_name' => ['nullable', 'string', 'max:80'],
        ], [
            'mail_host.regex' => 'Type only the server name, for example smtp.gmail.com, without http:// or a port.',
        ]);

        $mail->save([
            'host' => $data['mail_host'], 'port' => $data['mail_port'], 'encryption' => $data['mail_encryption'],
            'username' => $data['mail_username'] ?? null, 'password' => $data['mail_password'] ?? null,
            'clear_password' => $data['clear_mail_password'] ?? false,
            'from_address' => $data['mail_from_address'], 'from_name' => $data['mail_from_name'] ?? null,
        ]);

        return redirect()->to(route('console.settings.edit') . '#email')->with('success', 'Email settings saved. Send a test email to check them.');
    }

    /** Sends a real email to the admin's own address through the saved account. */
    public function testMail(Request $request, MailSettings $mail)
    {
        if (! $mail->configured()) {
            return redirect()->to(route('console.settings.edit') . '#email')->with('error', 'Save the email settings first.');
        }

        $problem = $mail->sendTest($request->user()->email);

        return redirect()->to(route('console.settings.edit') . '#email')->with(
            $problem ? 'error' : 'success',
            $problem ?? "A test email was sent to {$request->user()->email}. Check the inbox (and spam).",
        );
    }

    /** Puts a new Android app file in place (or removes the current one) and remembers its size and date. */
    private function handleApk(Request $request): void
    {
        $target = public_path(self::APK_PATH);

        if ($request->boolean('remove_apk') && ! $request->hasFile('apk_file')) {
            if (is_file($target)) { @unlink($target); }
            Setting::put(['app.apk_size' => null, 'app.apk_updated_at' => null]);
            return;
        }

        if (! $request->hasFile('apk_file')) { return; }

        $file = $request->file('apk_file');

        // An .apk is a zip file, so it starts with "PK". This stops a renamed picture or script from being offered as the app.
        $handle = fopen($file->getRealPath(), 'rb');
        $magic = $handle ? fread($handle, 4) : '';
        if ($handle) { fclose($handle); }
        if ($magic !== "PK\x03\x04") {
            throw \Illuminate\Validation\ValidationException::withMessages(['apk_file' => 'That does not look like an Android app file. Choose the .apk you built.']);
        }

        try {
            $file->move(dirname($target), basename($target));
        } catch (\Symfony\Component\HttpFoundation\File\Exception\FileException) {
            throw \Illuminate\Validation\ValidationException::withMessages(['apk_file' => 'The file could not be saved because public/downloads is not writable by the server. Ask whoever runs the server to fix its permissions.']);
        }

        Setting::put(['app.apk_size' => filesize($target), 'app.apk_updated_at' => now()->toIso8601String()]);
    }

    /** The biggest file this server will accept in one upload: the smaller of upload_max_filesize and post_max_size. */
    public static function uploadLimitBytes(): int
    {
        $bytes = fn (string $v) => (int) $v * match (strtolower(substr(trim($v), -1))) { 'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1 };
        $limits = array_filter([$bytes((string) ini_get('upload_max_filesize')), $bytes((string) ini_get('post_max_size'))]);

        return $limits ? min($limits) : 0;
    }

    public static function formatBytes(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : round($bytes / 1024) . ' KB';
    }
}
