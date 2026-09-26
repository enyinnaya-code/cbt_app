<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Exam;
use App\Models\Post;
use App\Models\Setting;
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
            'stores' => $this->stores(),
            'fromPrice' => \App\Services\Pricing::defaultPrice(),
            'freeQuestions' => \App\Services\Pricing::defaultFreeQuestions(),
        ]);
    }

    /** A short link for QR codes and text messages: sends each phone to its own store, or to the download section. */
    public function download(Request $request)
    {
        $stores = $this->stores();
        $agent = strtolower((string) $request->userAgent());

        if (str_contains($agent, 'android') && ($stores['android'] ?: $stores['apk'])) {
            return redirect()->away($stores['android'] ?: $stores['apk']);
        }
        if ((str_contains($agent, 'iphone') || str_contains($agent, 'ipad')) && $stores['ios']) {
            return redirect()->away($stores['ios']);
        }

        return redirect(route('welcome') . '#download');
    }

    /** @return array{android:?string,ios:?string,apk:?string} */
    public static function stores(): array
    {
        return [
            'android' => Setting::get('app.play_store_url'),
            'ios' => Setting::get('app.app_store_url'),
            'apk' => Setting::get('app.apk_url'),
        ];
    }
}
