<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Exam;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Video;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /** The public landing page: what TestaCBT is, the latest news, coming events, scholarships, and the app downloads. */
    public function index()
    {
        $news = Post::live()->whereIn('category', ['news', 'exam-news', 'results'])
            ->orderByDesc('is_featured')->orderByDesc('published_at')->limit(6)->get();

        $scholarships = Post::live()->where('category', 'scholarship')
            ->where(fn ($q) => $q->whereNull('deadline')->orWhere('deadline', '>=', today()))
            ->orderByRaw('deadline IS NULL')->orderBy('deadline')->limit(3)->get();

        return view('welcome', [
            'exams' => Exam::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug']),
            'news' => $news,
            'events' => Event::live()->upcoming()->orderBy('starts_on')->limit(5)->get(),
            'scholarships' => $scholarships,
            'videos' => Video::live()->with('exam:id,name')->orderByDesc('is_featured')->orderByDesc('id')->limit(3)->get(),
            'stores' => $this->stores(),
            'fromPrice' => \App\Services\Pricing::defaultPrice(),
            'freeQuestions' => \App\Services\Pricing::defaultFreeQuestions(),
        ]);
    }

    /** A short link for QR codes and text messages: sends Android phones to the app, and everyone else to the download section. */
    public function download(Request $request)
    {
        $stores = $this->stores();
        $agent = strtolower((string) $request->userAgent());

        if (str_contains($agent, 'android') && ($stores['android'] ?: $stores['apk'])) {
            return redirect()->away($stores['android'] ?: $stores['apk']);
        }

        return redirect(route('welcome') . '#download');
    }

    /**
     * Where each phone can get the app. The Android app can come from Google Play, from an app file (one uploaded in the
     * console, or the release build that ships with the code), or from a link to a file hosted elsewhere. The page falls
     * back to "use it in your browser" when there is nothing.
     *
     * @return array{android:?string,apk:?string,apk_version:?string,apk_size:int}
     */
    public static function stores(): array
    {
        $apk = self::apkFiles()['chosen'];

        return [
            'android' => Setting::get('app.play_store_url'),
            'apk' => $apk['url'] ?? Setting::get('app.apk_url'),
            'apk_version' => $apk ? $apk['version'] : Setting::get('app.apk_version'),
            'apk_size' => $apk['size'] ?? 0,
        ];
    }

    /** Where an app file uploaded in the console is kept (kept out of git, so pulling new code never clashes with it). */
    public const UPLOAD_PATH = 'downloads/TestaCBT-upload.apk';

    /** The release build that is committed with the code (see mobile/scripts/build-apk.mjs), with its details in a small .json next to it. */
    public const RELEASE_PATH = 'downloads/TestaCBT.apk';

    /**
     * The app files on this server, and which one visitors get: the newest, so a fresh release pulled from git replaces an
     * older upload, and a newer upload replaces an older release.
     *
     * @return array{upload:?array,release:?array,chosen:?array}
     */
    public static function apkFiles(): array
    {
        $describe = function (string $relative, ?string $version, ?string $fallbackDate): ?array {
            $file = public_path($relative);
            if (! is_file($file)) { return null; }

            return [
                'url' => url('/' . $relative), 'size' => (int) filesize($file), 'modified' => (int) filemtime($file),
                'version' => $version, 'updated_at' => $fallbackDate,
            ];
        };

        $meta = is_file(public_path('downloads/TestaCBT.json')) ? (json_decode((string) file_get_contents(public_path('downloads/TestaCBT.json')), true) ?: []) : [];

        $upload = $describe(self::UPLOAD_PATH, Setting::get('app.apk_version'), Setting::get('app.apk_updated_at'));
        $release = $describe(self::RELEASE_PATH, $meta['version'] ?? null, $meta['built_at'] ?? null);

        $chosen = collect([$upload, $release])->filter()->sortByDesc('modified')->first();

        return ['upload' => $upload, 'release' => $release, 'chosen' => $chosen];
    }
}