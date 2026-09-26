<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

/**
 * Turns a video link an admin pasted (YouTube, Facebook, X or TikTok) into something safe to show on the website.
 * Only the web address of the player is ever put on a page, and only for these four sites, built here from the
 * video's id. The pasted text itself is never placed inside a page as a player.
 */
class VideoLink
{
    public const YOUTUBE = 'youtube';
    public const FACEBOOK = 'facebook';
    public const X = 'x';
    public const TIKTOK = 'tiktok';

    public const LABELS = [self::YOUTUBE => 'YouTube', self::FACEBOOK => 'Facebook', self::X => 'X', self::TIKTOK => 'TikTok'];

    /**
     * @return array{platform:string, external_id:?string, url:string, embed_url:string, thumbnail_url:?string}
     * @throws InvalidArgumentException with a message an admin can act on
     */
    public static function parse(string $input): array
    {
        $input = trim($input);
        if (! preg_match('#^https?://#i', $input)) {
            throw new InvalidArgumentException('Paste the full link, starting with https://');
        }

        $parts = parse_url($input);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = preg_replace('/^(www|m|mobile|web|music)\./', '', $host);
        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);

        return match (true) {
            in_array($host, ['youtube.com', 'youtu.be', 'youtube-nocookie.com'], true) => self::youtube($host, $path, $query),
            in_array($host, ['facebook.com', 'fb.watch'], true) => self::facebook($host, $path, $input),
            in_array($host, ['x.com', 'twitter.com'], true) => self::x($path),
            in_array($host, ['tiktok.com', 'vm.tiktok.com', 'vt.tiktok.com'], true) => self::tiktok($host, $path, $input),
            default => throw new InvalidArgumentException('Only YouTube, Facebook, X and TikTok video links are supported.'),
        };
    }

    private static function youtube(string $host, string $path, array $query): array
    {
        $id = null;

        if ($host === 'youtu.be') {
            $id = trim($path, '/');
        } elseif (preg_match('#^/(?:shorts|embed|live|v)/([^/?]+)#', $path, $m) && $m[1] !== 'videoseries') {
            $id = $m[1];
        } elseif ($path === '/watch' || $path === '/watch/') {
            $id = (string) ($query['v'] ?? '');
        }

        if ($id !== null && preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) {
            return [
                'platform' => self::YOUTUBE, 'external_id' => $id, 'url' => "https://www.youtube.com/watch?v={$id}",
                'embed_url' => "https://www.youtube-nocookie.com/embed/{$id}?rel=0", 'thumbnail_url' => "https://i.ytimg.com/vi/{$id}/hqdefault.jpg",
            ];
        }

        // A whole playlist.
        $list = (string) ($query['list'] ?? '');
        if ($id === null && preg_match('/^[A-Za-z0-9_-]{10,60}$/', $list)) {
            return [
                'platform' => self::YOUTUBE, 'external_id' => 'list:' . $list, 'url' => "https://www.youtube.com/playlist?list={$list}",
                'embed_url' => "https://www.youtube-nocookie.com/embed/videoseries?list={$list}&rel=0", 'thumbnail_url' => null,
            ];
        }

        throw new InvalidArgumentException('That YouTube link has no video in it. Open the video and copy the address from the browser or the Share button.');
    }

    private static function facebook(string $host, string $path, string $original): array
    {
        $isVideo = $host === 'fb.watch'
            || preg_match('#/(videos|reel|watch|share/v|share/r)(/|$|\?)#', $path . '/')
            || str_contains(parse_url($original, PHP_URL_QUERY) ?? '', 'v=');

        if (! $isVideo) {
            throw new InvalidArgumentException('That does not look like a Facebook video link. Open the video, tap Share, then Copy link.');
        }

        // Keep only the address itself and the video id (never other tracking parameters).
        parse_str(parse_url($original, PHP_URL_QUERY) ?? '', $q);
        $canonical = 'https://' . ($host === 'fb.watch' ? 'fb.watch' : 'www.facebook.com') . $path . (isset($q['v']) && preg_match('/^\d+$/', (string) $q['v']) ? '?v=' . $q['v'] : '');
        $id = preg_match('#/(?:videos|reel)/(\d+)#', $path, $m) ? $m[1] : (isset($q['v']) && preg_match('/^\d+$/', (string) $q['v']) ? $q['v'] : null);

        return [
            'platform' => self::FACEBOOK, 'external_id' => $id, 'url' => $canonical,
            'embed_url' => 'https://www.facebook.com/plugins/video.php?show_text=false&t=0&href=' . rawurlencode($canonical),
            'thumbnail_url' => null,
        ];
    }

    private static function x(string $path): array
    {
        if (! preg_match('#^/(?:[A-Za-z0-9_]{1,15}|i)/status(?:es)?/(\d{5,25})#', $path, $m)) {
            throw new InvalidArgumentException('That does not look like a link to a post on X. Open the post, tap Share, then Copy link.');
        }

        return [
            'platform' => self::X, 'external_id' => $m[1], 'url' => 'https://x.com/i/status/' . $m[1],
            'embed_url' => 'https://platform.twitter.com/embed/Tweet.html?dnt=true&id=' . $m[1], 'thumbnail_url' => null,
        ];
    }

    private static function tiktok(string $host, string $path, string $original): array
    {
        // Share links (vm.tiktok.com/xyz) only say which video they mean after a redirect, so follow it once.
        if (in_array($host, ['vm.tiktok.com', 'vt.tiktok.com'], true)) {
            try {
                $location = Http::withoutRedirecting()->timeout(5)->head($original)->header('Location');
                $resolved = parse_url((string) $location);
            } catch (Throwable) {
                $resolved = null;
            }

            if (! $resolved || ! preg_match('/(^|\.)tiktok\.com$/i', (string) ($resolved['host'] ?? ''))) {
                throw new InvalidArgumentException('We could not open that short TikTok link. Open the video in your browser and paste the full address, which looks like tiktok.com/@name/video/123...');
            }
            $path = $resolved['path'] ?? '';
        }

        if (! preg_match('#/video/(\d{8,25})#', $path, $m)) {
            throw new InvalidArgumentException('That does not look like a TikTok video link. It should look like tiktok.com/@name/video/123...');
        }

        return [
            'platform' => self::TIKTOK, 'external_id' => $m[1], 'url' => 'https://www.tiktok.com' . preg_replace('#^(.*?/video/\d+).*$#', '$1', $path),
            'embed_url' => 'https://www.tiktok.com/embed/v2/' . $m[1], 'thumbnail_url' => null,
        ];
    }
}
