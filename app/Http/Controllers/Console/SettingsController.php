<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\ContentPack;
use App\Models\Exam;
use App\Models\Setting;
use App\Models\Subject;
use App\Services\MailSettings;
use App\Services\PackBuilder;
use App\Services\PaystackSettings;
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
            'paystackKey' => app(PaystackSettings::class)->form(),
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

    /** The Paystack secret key. Kept encrypted; a blank box changes nothing; "remove" goes back to the one in .env, if any. */
    public function paystack(Request $request, PaystackSettings $paystack)
    {
        if ($request->boolean('forget_paystack')) {
            $paystack->clear();

            return redirect()->to(route('console.settings.edit') . '#paystack')->with('success', 'The saved Paystack key was removed.');
        }

        $data = $request->validate(['paystack_secret' => ['required', 'string', 'max:200', 'regex:' . PaystackSettings::PATTERN]], [
            'paystack_secret.required' => 'Paste your Paystack secret key.',
            'paystack_secret.regex' => 'That does not look like a Paystack secret key. It starts with sk_live_ or sk_test_ (not pk_, which is the public key).',
        ]);

        $paystack->save($data['paystack_secret']);

        return redirect()->to(route('console.settings.edit') . '#paystack')->with('success', 'Paystack key saved. Press "Check the key" to make sure Paystack accepts it.');
    }

    /** Asks Paystack whether the key works, without charging anything. */
    public function paystackCheck(PaystackSettings $paystack)
    {
        $problem = $paystack->check();

        return redirect()->to(route('console.settings.edit') . '#paystack')->with($problem ? 'error' : 'success', $problem ?? 'Paystack accepted the key. Online payment is ready.');
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

    /** The biggest app file accepted, and the biggest single piece of one. */
    private const APK_MAX_BYTES = 300 * 1048576;
    private const APK_PIECE_MAX = 2 * 1048576;

    /**
     * Receives the app file in small pieces (the browser cuts it up), so it works on a server whose upload limit is
     * smaller than the app. When the last piece arrives the pieces are joined, checked to be an Android app, and put in place.
     */
    public function apkPiece(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'upload_id' => ['required', 'regex:/^[a-f0-9]{32}$/'],
            'index' => ['required', 'integer', 'min:0', 'max:1000'],
            'total' => ['required', 'integer', 'min:1', 'max:1000'],
            'piece' => ['required', 'file', 'max:' . intdiv(self::APK_PIECE_MAX, 1024)],
            'version' => ['nullable', 'string', 'max:20'],
        ]);
        abort_if($data['index'] >= $data['total'], 422, 'That piece does not belong to this upload.');

        $root = storage_path('app/apk-pieces');
        $dir = "{$root}/{$data['upload_id']}";
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return response()->json(['message' => 'The server could not store the upload (storage/app is not writable).'], 500);
        }
        foreach (glob("{$root}/*", GLOB_ONLYDIR) ?: [] as $old) {
            if ($old !== $dir && filemtime($old) < time() - 86400) { array_map('unlink', glob("{$old}/*") ?: []); @rmdir($old); }
        }

        $request->file('piece')->move($dir, sprintf('%04d', $data['index']));
        $have = glob("{$dir}/[0-9][0-9][0-9][0-9]") ?: [];
        if (count($have) < $data['total']) {
            return response()->json(['done' => false, 'received' => count($have)]);
        }

        // Every piece is here: join them in order.
        $joined = "{$dir}/joined.apk";
        $out = fopen($joined, 'wb');
        $size = 0;
        for ($i = 0; $i < $data['total']; $i++) {
            $part = sprintf('%s/%04d', $dir, $i);
            if (! is_file($part)) { fclose($out); return response()->json(['message' => 'A piece went missing. Please start the upload again.'], 422); }
            $size += filesize($part);
            fwrite($out, (string) file_get_contents($part));
        }
        fclose($out);

        $cleanup = function () use ($dir) { array_map('unlink', glob("{$dir}/*") ?: []); @rmdir($dir); };

        $handle = fopen($joined, 'rb');
        $magic = $handle ? fread($handle, 4) : '';
        if ($handle) { fclose($handle); }
        if ($size > self::APK_MAX_BYTES || $magic !== "PK\x03\x04") {
            $cleanup();
            return response()->json(['message' => $size > self::APK_MAX_BYTES ? 'The app file must be 300 MB or smaller.' : 'That does not look like an Android app file. Choose the .apk you built.'], 422);
        }

        $target = public_path(self::APK_PATH);
        if (! is_dir(dirname($target)) || ! @rename($joined, $target)) {
            if (! (is_dir(dirname($target)) && @copy($joined, $target))) {
                $cleanup();
                return response()->json(['message' => 'The file could not be saved because public/downloads is not writable by the server.'], 500);
            }
        }
        $cleanup();

        Setting::put(['app.apk_size' => filesize($target), 'app.apk_updated_at' => now()->toIso8601String()]);
        if (filled($data['version'] ?? null)) { Setting::put(['app.apk_version' => $data['version']]); }

        return response()->json(['done' => true, 'size' => self::formatBytes((int) filesize($target))]);
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
