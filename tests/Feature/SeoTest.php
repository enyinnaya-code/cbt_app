<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Exam;
use App\Models\Post;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    private function makePost(array $over = []): Post
    {
        static $n = 0;
        $n++;

        return Post::create($over + [
            'category' => 'news', 'title' => "SEO post $n", 'slug' => "seo-post-$n", 'body' => '<p>Body text of the article.</p>',
            'status' => Post::PUBLISHED, 'published_at' => now()->subHour(),
        ]);
    }

    /** @return array<int,array<string,mixed>> every JSON-LD block on the page, decoded */
    private function jsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

        return array_map(fn ($json) => json_decode($json, true, 512, JSON_THROW_ON_ERROR), $m[1]);
    }

    private function meta(string $html, string $attr, string $name): ?string
    {
        return preg_match('#<meta ' . $attr . '="' . preg_quote($name, '#') . '" content="([^"]*)"#', $html, $m) ? html_entity_decode($m[1]) : null;
    }

    public function test_the_landing_page_has_a_full_set_of_search_and_sharing_tags(): void
    {
        $this->makeExamAndSubject('WAEC', 'Physics');
        $this->makeExamAndSubject('IELTS', 'Reading');
        Exam::where('slug', 'ielts')->update(['sort_order' => 5]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Pass WAEC and IELTS | TestaCBT</title>', $html);
        $this->assertStringContainsString('Practise WAEC and IELTS past questions', $this->meta($html, 'name', 'description'));
        $this->assertSame('index, follow, max-image-preview:large, max-snippet:-1', $this->meta($html, 'name', 'robots'));
        $this->assertStringContainsString('<link rel="canonical" href="' . url('/') . '">', $html);
        $this->assertSame('website', $this->meta($html, 'property', 'og:type'));
        $this->assertSame('Pass WAEC and IELTS', $this->meta($html, 'property', 'og:title'));
        $this->assertSame(asset('og-default.png'), $this->meta($html, 'property', 'og:image'));
        $this->assertSame('summary_large_image', $this->meta($html, 'name', 'twitter:card'));
        $this->assertSame('#0A7A4A', $this->meta($html, 'name', 'theme-color'));
        $this->assertStringContainsString('rel="manifest"', $html);
        $this->assertStringContainsString('rel="apple-touch-icon"', $html);
        $this->assertStringContainsString(asset('favicon.ico'), $html);
        $this->assertStringContainsString('application/rss+xml', $html);
    }

    public function test_the_landing_page_describes_the_site_to_search_engines_as_structured_data(): void
    {
        $graph = $this->jsonLd($this->get('/')->getContent())[0]['@graph'];

        $this->assertSame(['Organization', 'WebSite'], array_column($graph, '@type'));
        $this->assertSame('TestaCBT', $graph[0]['name']);
        $this->assertSame(asset('icon-512.png'), $graph[0]['logo']);
    }

    public function test_the_exam_names_in_titles_and_descriptions_follow_the_admins_exams(): void
    {
        $this->assertStringContainsString('Practise exam past questions', $this->meta($this->get('/')->getContent(), 'name', 'description'));

        $this->makeExamAndSubject('GRE', 'Verbal');
        $this->makeExamAndSubject('IELTS', 'Reading');
        Exam::where('slug', 'gre')->update(['sort_order' => 1]);
        Exam::where('slug', 'ielts')->update(['sort_order' => 2, 'is_active' => false]);

        $this->app->forgetInstance(\App\Support\SiteInfo::class);   // one request per app in real life; tests make several
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('Practise GRE past questions', $this->meta($html, 'name', 'description'));
        $this->assertStringNotContainsString('IELTS', $this->meta($html, 'name', 'description'));
    }

    public function test_an_article_carries_article_tags_and_structured_data(): void
    {
        $post = $this->makePost(['title' => 'NECO results out', 'slug' => 'neco-results-out', 'excerpt' => 'How to check your NECO result.', 'category' => 'results', 'cover_path' => 'uploads/posts/cover.jpg']);

        $html = $this->get('/articles/neco-results-out')->assertOk()->getContent();

        $this->assertStringContainsString('<title>NECO results out | TestaCBT</title>', $html);
        $this->assertSame('How to check your NECO result.', $this->meta($html, 'name', 'description'));
        $this->assertSame('article', $this->meta($html, 'property', 'og:type'));
        $this->assertSame(asset('uploads/posts/cover.jpg'), $this->meta($html, 'property', 'og:image'));
        $this->assertSame(route('articles.show', 'neco-results-out'), $this->meta($html, 'property', 'og:url'));
        $this->assertSame('Results', $this->meta($html, 'property', 'article:section'));
        $this->assertNotNull($this->meta($html, 'property', 'article:published_time'));

        $graph = $this->jsonLd($html)[0]['@graph'];
        $this->assertSame('NewsArticle', $graph[0]['@type']);
        $this->assertSame('NECO results out', $graph[0]['headline']);
        $this->assertSame($post->published_at->toAtomString(), $graph[0]['datePublished']);
        $this->assertSame('TestaCBT', $graph[0]['publisher']['name']);
        $this->assertSame('BreadcrumbList', $graph[1]['@type']);
        $this->assertSame(['TestaCBT', 'News', 'NECO results out'], array_column($graph[1]['itemListElement'], 'name'));
    }

    public function test_a_blog_post_is_marked_up_as_a_blog_posting_and_a_scholarship_lists_under_scholarships(): void
    {
        $this->makePost(['slug' => 'blog-one', 'category' => 'blog']);
        $this->makePost(['slug' => 'award-one', 'category' => 'scholarship']);

        $this->assertSame('BlogPosting', $this->jsonLd($this->get('/articles/blog-one')->getContent())[0]['@graph'][0]['@type']);
        $this->assertSame('Scholarships', $this->jsonLd($this->get('/articles/award-one')->getContent())[0]['@graph'][1]['itemListElement'][1]['name']);
    }

    public function test_a_title_cannot_break_out_of_the_structured_data_script(): void
    {
        $this->makePost(['slug' => 'nasty', 'title' => 'Tricky </script><script>alert(1)</script> & "quotes"']);

        $html = $this->get('/articles/nasty')->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertSame('Tricky </script><script>alert(1)</script> & "quotes"', $this->jsonLd($html)[0]['@graph'][0]['headline']);
    }

    public function test_an_unpublished_preview_is_kept_out_of_search_engines(): void
    {
        $this->makePost(['slug' => 'draft-one', 'status' => Post::DRAFT]);

        $html = $this->actingAs($this->makeUser(2))->get('/articles/draft-one')->assertOk()->getContent();

        $this->assertSame('noindex, nofollow', $this->meta($html, 'name', 'robots'));
    }

    public function test_the_signed_in_areas_and_sign_in_pages_are_not_indexed(): void
    {
        $this->assertSame('noindex, follow', $this->meta($this->get('/login')->getContent(), 'name', 'robots'));
        $this->assertSame('noindex, follow', $this->meta($this->get('/register')->getContent(), 'name', 'robots'));

        $student = $this->makeUser(4);
        foreach (['/dashboard', '/practice', '/mock', '/orders', '/checkout'] as $url) {
            $this->assertSame('noindex, nofollow', $this->meta($this->actingAs($student)->get($url)->getContent(), 'name', 'robots'), $url);
        }
        $this->assertSame('noindex, nofollow', $this->meta($this->actingAs($this->makeUser(2))->get('/console/posts')->getContent(), 'name', 'robots'));
    }

    public function test_search_results_are_not_indexed_and_the_canonical_ignores_search_words(): void
    {
        $this->makePost();

        $search = $this->get('/news?q=seo')->getContent();
        $this->assertSame('noindex, follow', $this->meta($search, 'name', 'robots'));
        $this->assertStringContainsString('<link rel="canonical" href="' . route('news') . '">', $search);

        $this->assertSame('index, follow, max-image-preview:large, max-snippet:-1', $this->meta($this->get('/news')->getContent(), 'name', 'robots'));
    }

    public function test_the_canonical_keeps_the_category_and_the_page_number_only(): void
    {
        foreach (range(1, 14) as $i) { $this->makePost(['category' => 'results']); }

        $html = $this->get('/news?category=results&page=2&utm_source=whatsapp')->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="' . route('news') . '?category=results&amp;page=2">', $html);
    }

    public function test_search_engine_verification_tags_come_from_the_settings(): void
    {
        $this->assertNull($this->meta($this->get('/')->getContent(), 'name', 'google-site-verification'));

        Setting::put(['seo.google_verification' => 'abc123', 'seo.bing_verification' => 'bing456']);
        $html = $this->get('/')->getContent();

        $this->assertSame('abc123', $this->meta($html, 'name', 'google-site-verification'));
        $this->assertSame('bing456', $this->meta($html, 'name', 'msvalidate.01'));
    }

    public function test_the_events_page_lists_events_as_structured_data(): void
    {
        Event::create(['title' => 'JAMB registration closes', 'slug' => 'jamb-reg', 'kind' => 'registration', 'starts_on' => today()->addDays(4), 'status' => 'published', 'location' => 'Nationwide']);
        Event::create(['title' => 'Maths revision class', 'slug' => 'maths-class', 'kind' => 'webinar', 'starts_on' => today()->addDays(2), 'status' => 'published']);

        $graph = $this->jsonLd($this->get('/events')->assertOk()->getContent())[0]['@graph'];

        $this->assertSame(['Maths revision class', 'JAMB registration closes'], array_column($graph, 'name'));
        $this->assertSame('VirtualLocation', $graph[0]['location']['@type']);
        $this->assertSame('Place', $graph[1]['location']['@type']);
        $this->assertSame('Nationwide', $graph[1]['location']['name']);
    }

    // ------------------------------------------------------------------------------------ robots, sitemap, feed

    public function test_a_test_server_tells_search_engines_to_stay_away(): void
    {
        $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->assertSee('Disallow: /', false)->assertDontSee('Sitemap');
    }

    public function test_the_live_site_keeps_private_areas_out_and_points_to_the_sitemap(): void
    {
        $this->app['env'] = 'production';

        $body = $this->get('/robots.txt')->assertOk()->getContent();

        foreach (['Disallow: /console/', 'Disallow: /dashboard', 'Disallow: /api/', 'Disallow: /checkout', 'Disallow: /orders', 'Allow: /'] as $line) {
            $this->assertStringContainsString($line, $body);
        }
        $this->assertStringContainsString('Sitemap: ' . url('/sitemap.xml'), $body);
    }

    public function test_the_sitemap_lists_public_pages_and_live_articles_only(): void
    {
        $this->makePost(['slug' => 'live-one']);
        $this->makePost(['slug' => 'draft-one', 'status' => Post::DRAFT]);
        $this->makePost(['slug' => 'later-one', 'published_at' => now()->addWeek()]);

        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->assertNotFalse(simplexml_load_string($response->getContent()), 'the sitemap is valid XML');
        preg_match_all('#<loc>(.*?)</loc>#', $response->getContent(), $found);
        $locs = array_map('html_entity_decode', $found[1]);

        $this->assertContains(url('/'), $locs);
        $this->assertContains(route('news'), $locs);
        $this->assertContains(route('pricing'), $locs);
        $this->assertContains(route('articles.show', 'live-one'), $locs);
        $this->assertNotContains(route('articles.show', 'draft-one'), $locs);
        $this->assertNotContains(route('articles.show', 'later-one'), $locs);
        $this->assertNotContains(route('login'), $locs);
    }

    public function test_the_rss_feed_lists_the_latest_articles_and_escapes_titles(): void
    {
        $this->makePost(['slug' => 'one', 'title' => 'Tom & Jerry <results>']);
        $this->makePost(['slug' => 'draft', 'title' => 'Hidden', 'status' => Post::DRAFT]);

        $response = $this->get('/feed.xml')->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
        $xml = simplexml_load_string($response->getContent());

        $this->assertCount(1, $xml->channel->item);
        $this->assertSame('Tom & Jerry <results>', (string) $xml->channel->item[0]->title);
        $this->assertSame(route('articles.show', 'one'), (string) $xml->channel->item[0]->link);
    }

    // ------------------------------------------------------------------------------------ icons and manifest

    public function test_the_site_icons_and_manifest_exist_and_are_valid(): void
    {
        foreach (['favicon.ico', 'favicon-32.png', 'apple-touch-icon.png', 'icon-192.png', 'icon-512.png', 'og-default.png'] as $file) {
            $this->assertFileExists(public_path($file));
            $this->assertGreaterThan(100, filesize(public_path($file)), $file);
        }

        $this->assertSame([1200, 630], array_slice(getimagesize(public_path('og-default.png')), 0, 2));
        $this->assertSame([512, 512], array_slice(getimagesize(public_path('icon-512.png')), 0, 2));

        $manifest = json_decode(file_get_contents(public_path('site.webmanifest')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('TestaCBT', $manifest['name']);
        $this->assertSame('standalone', $manifest['display']);
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
    }
}
