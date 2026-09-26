<?php

namespace Tests\Feature;

use App\Models\Video;
use App\Services\VideoLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

class VideoTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    private function video(array $over = []): Video
    {
        static $n = 0;
        $n++;

        return Video::create($over + [
            'title' => "Video $n", 'platform' => 'youtube', 'external_id' => 'dQw4w9WgXcQ', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'embed_url' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0', 'thumbnail_url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', 'status' => 'published',
        ]);
    }

    // ----------------------------------------------------------------------------------- reading links

    /** @return array<string,array{string,string,?string,string}> link => [platform, id, ...] */
    public static function goodLinks(): array
    {
        return [
            'youtube watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s&utm_source=x', 'youtube', 'dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0'],
            'youtube short link' => ['https://youtu.be/dQw4w9WgXcQ?si=abc', 'youtube', 'dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0'],
            'youtube shorts' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0'],
            'youtube phone' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0'],
            'youtube embed' => ['https://www.youtube.com/embed/dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0'],
            'youtube live' => ['https://www.youtube.com/live/dQw4w9WgXcQ?feature=share', 'youtube', 'dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0'],
            'youtube playlist' => ['https://www.youtube.com/playlist?list=PLabcdefghijklmnop123', 'youtube', 'list:PLabcdefghijklmnop123', 'https://www.youtube-nocookie.com/embed/videoseries?list=PLabcdefghijklmnop123&rel=0'],
            'facebook video' => ['https://www.facebook.com/TestaCBT/videos/1234567890123/?ref=share', 'facebook', '1234567890123', null],
            'facebook watch' => ['https://www.facebook.com/watch/?v=1234567890123&mibextid=abc', 'facebook', '1234567890123', null],
            'facebook reel' => ['https://www.facebook.com/reel/1234567890123', 'facebook', '1234567890123', null],
            'facebook short link' => ['https://fb.watch/abc123XYZ/', 'facebook', null, null],
            'x post' => ['https://x.com/TestaCBT/status/1700000000000000000?s=20', 'x', '1700000000000000000', 'https://platform.twitter.com/embed/Tweet.html?dnt=true&id=1700000000000000000'],
            'twitter post' => ['https://twitter.com/TestaCBT/status/1700000000000000000', 'x', '1700000000000000000', 'https://platform.twitter.com/embed/Tweet.html?dnt=true&id=1700000000000000000'],
            'tiktok video' => ['https://www.tiktok.com/@testacbt/video/7300000000000000000?is_from_webapp=1', 'tiktok', '7300000000000000000', 'https://www.tiktok.com/embed/v2/7300000000000000000'],
        ];
    }

    /** @dataProvider goodLinks */
    public function test_links_from_the_four_sites_are_understood(string $link, string $platform, ?string $id, ?string $embed): void
    {
        $parsed = VideoLink::parse($link);

        $this->assertSame($platform, $parsed['platform']);
        $this->assertSame($id, $parsed['external_id']);
        if ($embed) { $this->assertSame($embed, $parsed['embed_url']); }
        $this->assertStringStartsWith('https://', $parsed['url']);
        $this->assertStringNotContainsString('utm_', $parsed['url'] . $parsed['embed_url'], 'tracking parameters are dropped');
    }

    public function test_youtube_gets_a_thumbnail_and_the_others_do_not(): void
    {
        $this->assertSame('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', VideoLink::parse('https://youtu.be/dQw4w9WgXcQ')['thumbnail_url']);
        $this->assertNull(VideoLink::parse('https://x.com/a/status/1700000000000000000')['thumbnail_url']);
    }

    public function test_the_facebook_player_gets_the_video_address_encoded(): void
    {
        $embed = VideoLink::parse('https://www.facebook.com/TestaCBT/videos/1234567890123/')['embed_url'];

        $this->assertStringStartsWith('https://www.facebook.com/plugins/video.php?', $embed);
        $this->assertStringContainsString('href=' . rawurlencode('https://www.facebook.com/TestaCBT/videos/1234567890123/'), $embed);
    }

    /** @return array<string,array{string}> */
    public static function badLinks(): array
    {
        return [
            'no scheme' => ['www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'javascript' => ['javascript:alert(1)'],
            'another site' => ['https://vimeo.com/123456'],
            'lookalike host' => ['https://www.youtube.com.evil.example/watch?v=dQw4w9WgXcQ'],
            'host in the path' => ['https://evil.example/https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'youtube channel' => ['https://www.youtube.com/@testacbt'],
            'youtube bad id' => ['https://www.youtube.com/watch?v=short'],
            'youtube script id' => ['https://www.youtube.com/watch?v="><script>alert(1)</script>'],
            'facebook page' => ['https://www.facebook.com/TestaCBT'],
            'facebook photo' => ['https://www.facebook.com/photo?fbid=123'],
            'x profile' => ['https://x.com/TestaCBT'],
            'x bad id' => ['https://x.com/a/status/abc'],
            'tiktok profile' => ['https://www.tiktok.com/@testacbt'],
            'empty' => [''],
        ];
    }

    /** @dataProvider badLinks */
    public function test_anything_else_is_refused_with_a_reason(string $link): void
    {
        $this->expectException(InvalidArgumentException::class);

        VideoLink::parse($link);
    }

    public function test_a_short_tiktok_link_is_followed_to_the_real_video(): void
    {
        Http::fake(['vm.tiktok.com/*' => Http::response('', 301, ['Location' => 'https://www.tiktok.com/@testacbt/video/7300000000000000000?_r=1'])]);

        $parsed = VideoLink::parse('https://vm.tiktok.com/ZMabc123/');

        $this->assertSame('7300000000000000000', $parsed['external_id']);
        $this->assertSame('https://www.tiktok.com/@testacbt/video/7300000000000000000', $parsed['url']);
    }

    public function test_a_short_tiktok_link_that_goes_somewhere_else_or_cannot_be_opened_is_refused(): void
    {
        Http::fake(['vm.tiktok.com/*' => Http::response('', 301, ['Location' => 'https://evil.example/video/7300000000000000000'])]);
        try { VideoLink::parse('https://vm.tiktok.com/ZMabc123/'); $this->fail('expected a refusal'); } catch (InvalidArgumentException $e) { $this->assertStringContainsString('full address', $e->getMessage()); }

        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('offline'));
        $this->expectException(InvalidArgumentException::class);
        VideoLink::parse('https://vt.tiktok.com/ZMabc123/');
    }

    // ----------------------------------------------------------------------------------- console

    public function test_an_admin_adds_edits_and_deletes_a_video(): void
    {
        $admin = $this->makeUser(2);

        $this->actingAs($admin)->post('/console/videos', ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => 'JAMB maths tips', 'status' => 'published'])->assertRedirect(route('console.videos.index'));
        $video = Video::firstOrFail();
        $this->assertSame('youtube', $video->platform);
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $video->url);
        $this->assertSame($admin->id, $video->added_by);

        $this->actingAs($admin)->put("/console/videos/{$video->id}", ['url' => 'https://www.tiktok.com/@testacbt/video/7300000000000000000', 'title' => 'Now on TikTok', 'status' => 'draft', 'is_featured' => 1]);
        $video->refresh();
        $this->assertSame(['tiktok', '7300000000000000000', 'draft', true], [$video->platform, $video->external_id, $video->status, $video->is_featured]);
        $this->assertNull($video->thumbnail_url, 'switching site clears the old thumbnail');

        $this->actingAs($admin)->delete("/console/videos/{$video->id}");
        $this->assertSame(0, Video::count());
    }

    public function test_a_bad_link_or_missing_title_saves_nothing_and_says_why(): void
    {
        $admin = $this->makeUser(2);

        $this->actingAs($admin)->post('/console/videos', ['url' => 'https://vimeo.com/1', 'title' => 'x', 'status' => 'published'])->assertSessionHasErrors(['url' => 'Only YouTube, Facebook, X and TikTok video links are supported.']);
        $this->actingAs($admin)->post('/console/videos', ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => '', 'status' => 'published'])->assertSessionHasErrors('title');
        $this->actingAs($admin)->post('/console/videos', ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => 'x', 'status' => 'live'])->assertSessionHasErrors('status');
        $this->assertSame(0, Video::count());
    }

    public function test_only_admins_manage_videos(): void
    {
        $body = ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => 'x', 'status' => 'published'];

        $this->get('/console/videos')->assertRedirect('/login');
        foreach ([$this->makeUser(3), $this->makeUser(4)] as $user) {
            $this->actingAs($user)->get('/console/videos')->assertForbidden();
            $this->actingAs($user)->post('/console/videos', $body)->assertForbidden();
        }
        $this->actingAs($this->makeUser(2))->get('/console/videos')->assertOk()->assertSee('No videos yet');
        $this->assertSame(0, Video::count());
    }

    public function test_the_console_lists_videos_with_serial_numbers(): void
    {
        $this->video(['title' => 'First one']);
        $this->video(['title' => 'Second one']);

        $html = $this->actingAs($this->makeUser(2))->get('/console/videos')->assertOk()->getContent();

        $this->assertStringContainsString('S/N', $html);
        preg_match_all('#<td class="muted">(\d+)</td>#', $html, $m);
        $this->assertSame([1, 2], array_map('intval', $m[1]));
    }

    // ----------------------------------------------------------------------------------- the website

    public function test_the_videos_page_shows_published_videos_only(): void
    {
        $this->video(['title' => 'Live lesson']);
        $this->video(['title' => 'Hidden draft', 'status' => 'draft']);

        $this->get('/videos')->assertOk()->assertSee('Live lesson')->assertDontSee('Hidden draft');
    }

    public function test_each_platform_gets_its_own_player_address_and_a_link_to_the_original(): void
    {
        foreach (['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.facebook.com/a/videos/1234567890123/', 'https://x.com/a/status/1700000000000000000', 'https://www.tiktok.com/@a/video/7300000000000000000'] as $i => $link) {
            $parsed = VideoLink::parse($link);
            $this->video(['title' => "Clip $i"] + $parsed);
        }

        $html = $this->get('/videos')->assertOk()->getContent();

        foreach (['youtube-nocookie.com/embed/dQw4w9WgXcQ', 'facebook.com/plugins/video.php', 'platform.twitter.com/embed/Tweet.html', 'tiktok.com/embed/v2/7300000000000000000'] as $embed) {
            $this->assertStringContainsString($embed, $html);
        }
        $this->assertStringContainsString('Watch on TikTok', $html);
        $this->assertStringContainsString('class="video-frame tall"', $html);
    }

    public function test_no_player_is_loaded_until_play_is_pressed(): void
    {
        $this->video();

        $html = $this->get('/videos')->getContent();

        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringContainsString('class="video-play"', $html);
    }

    public function test_a_video_title_cannot_inject_markup(): void
    {
        $this->video(['title' => '<script>alert(1)</script> "quoted"', 'description' => '<img src=x onerror=alert(1)>']);

        $html = $this->get('/videos')->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
    }

    public function test_filters_by_site_and_exam_and_ignores_unknown_values(): void
    {
        [$waec] = $this->makeExamAndSubject('WAEC', 'Physics');
        $this->video(['title' => 'YouTube one']);
        $this->video(['title' => 'TikTok one'] + VideoLink::parse('https://www.tiktok.com/@a/video/7300000000000000000'));
        $this->video(['title' => 'WAEC tips', 'exam_id' => $waec->id]);

        $this->get('/videos?platform=tiktok')->assertSee('TikTok one')->assertDontSee('YouTube one');
        $this->get('/videos?exam=waec')->assertSee('WAEC tips')->assertDontSee('TikTok one');
        $this->get('/videos?platform=nonsense&exam=nope')->assertOk()->assertSee('TikTok one')->assertSee('YouTube one');
    }

    public function test_featured_videos_come_first(): void
    {
        $this->video(['title' => 'Plain']);
        $this->video(['title' => 'Featured one', 'is_featured' => true]);

        $this->get('/videos')->assertSeeInOrder(['Featured one', 'Plain']);
    }

    public function test_the_landing_page_shows_videos_only_when_there_are_some(): void
    {
        $this->get('/')->assertOk()->assertDontSee('id="videos"', false);

        $this->video(['title' => 'Home page clip']);
        $this->get('/')->assertOk()->assertSee('Home page clip')->assertSee('All videos');
    }

    public function test_the_site_navigation_the_sitemap_and_search_data_know_about_videos(): void
    {
        $this->video(['title' => 'Indexed lesson']);

        $this->get('/news')->assertSee(route('videos'), false);
        $this->get('/sitemap.xml')->assertSee(route('videos'), false);

        $html = $this->get('/videos')->getContent();
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $graph = json_decode($m[1], true)['@graph'];
        $this->assertSame('VideoObject', $graph[0]['@type']);
        $this->assertSame('Indexed lesson', $graph[0]['name']);
        $this->assertSame('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', $graph[0]['thumbnailUrl'][0]);
    }
}
