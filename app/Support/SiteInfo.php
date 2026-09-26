<?php

namespace App\Support;

use App\Models\Exam;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Facts about the public site that several pages need: which exams it offers (admins can add more), the default
 * description shown in search results, and the address of a page without tracking or filter noise.
 * Registered as a singleton, so it looks the exams up once per request.
 */
class SiteInfo
{
    /** @var array<int,string>|null */
    private ?array $exams = null;

    /** @return array<int,string> names of the exams students can practise, in the order admins set */
    public function examList(): array
    {
        return $this->exams ??= Exam::where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name')->all();
    }

    /** "WAEC, NECO, JAMB, Post-UTME and IGCSE" (or just the one name, or a generic phrase when there are none). */
    public function examNames(): string
    {
        $names = $this->examList();

        return match (count($names)) {
            0 => 'exam',
            1 => $names[0],
            default => implode(', ', array_slice($names, 0, -1)) . ' and ' . end($names),
        };
    }

    public function description(): string
    {
        return "Practise {$this->examNames()} past questions free, with instant answers and simple explanations. Take timed mock exams on the web or with the offline mobile app.";
    }

    /** The page's own address. Only the parameters that change what the page shows are kept (page number and category). */
    public function canonical(Request $request): string
    {
        $keep = array_filter([
            'category' => $request->query('category'),
            'page' => ($request->query('page') ?? 1) > 1 ? $request->query('page') : null,
        ], fn ($v) => is_scalar($v) && $v !== '');

        $url = url($request->path() === '/' ? '/' : $request->path());

        return $keep ? $url . '?' . http_build_query($keep) : $url;
    }

    /** Verification tags for Google Search Console and Bing Webmaster Tools, set by an admin. */
    public function verification(): array
    {
        return array_filter([
            'google-site-verification' => Setting::get('seo.google_verification'),
            'msvalidate.01' => Setting::get('seo.bing_verification'),
        ]);
    }

    /** Schema.org "Organization" data, shared by the home page and articles. */
    public function organization(): array
    {
        $sameAs = array_values(array_filter([Setting::get('social.facebook'), Setting::get('social.x'), Setting::get('social.instagram'), Setting::get('social.youtube')]));

        return array_filter([
            '@type' => 'Organization',
            'name' => 'TestaCBT',
            'url' => url('/'),
            'logo' => asset('icon-512.png'),
            'sameAs' => $sameAs ?: null,
        ]);
    }

    /** Prints JSON-LD safely: "<" and "&" are escaped, so nothing in a title can close the script tag early. */
    public static function jsonLd(array $data): string
    {
        return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR) . '</script>';
    }
}
