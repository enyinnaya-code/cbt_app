<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Response;

/** robots.txt, sitemap.xml and an RSS feed of the news, for search engines and feed readers. */
class SeoController extends Controller
{
    /** Search engines are told to stay out of the student area, the console and the API, and out of everything on a test server. */
    public function robots(): Response
    {
        $lines = app()->isProduction()
            ? [
                'User-agent: *',
                'Allow: /',
                'Disallow: /console/',
                'Disallow: /dashboard',
                'Disallow: /practice',
                'Disallow: /mock',
                'Disallow: /saved',
                'Disallow: /progress',
                'Disallow: /profile',
                'Disallow: /checkout',
                'Disallow: /orders',
                'Disallow: /app-link/',
                'Disallow: /api/',
                'Disallow: /login',
                'Disallow: /register',
                '',
                'Sitemap: ' . url('/sitemap.xml'),
            ]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines) . "\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $pages = [
            [url('/'), null, 'daily', '1.0'],
            [route('news'), Post::live()->whereIn('category', ['news', 'exam-news', 'results'])->max('updated_at'), 'daily', '0.9'],
            [route('scholarships'), Post::live()->where('category', 'scholarship')->max('updated_at'), 'daily', '0.8'],
            [route('blog'), Post::live()->where('category', 'blog')->max('updated_at'), 'weekly', '0.6'],
            [route('events'), null, 'weekly', '0.7'],
            [route('videos'), \App\Models\Video::live()->max('updated_at'), 'weekly', '0.6'],
            [route('pricing'), null, 'monthly', '0.7'],
        ];

        $urls = collect($pages)->map(fn ($p) => $this->entry($p[0], $p[1], $p[2], $p[3]));

        Post::live()->orderByDesc('published_at')->limit(5000)->get(['slug', 'updated_at', 'category'])
            ->each(fn ($post) => $urls->push($this->entry(route('articles.show', $post->slug), $post->updated_at, 'monthly', '0.6')));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
            . $urls->implode("\n") . "\n</urlset>\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** The latest news, exam news, results and scholarships as an RSS feed. */
    public function feed(): Response
    {
        $posts = Post::live()->orderByDesc('published_at')->limit(30)->get();

        $items = $posts->map(fn (Post $p) => '<item>'
            . '<title>' . e($p->title) . '</title>'
            . '<link>' . e(route('articles.show', $p->slug)) . '</link>'
            . '<guid isPermaLink="true">' . e(route('articles.show', $p->slug)) . '</guid>'
            . '<pubDate>' . $p->published_at->toRfc2822String() . '</pubDate>'
            . '<category>' . e($p->categoryLabel()) . '</category>'
            . '<description>' . e($p->summary(300)) . '</description>'
            . '</item>')->implode("\n");

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>'
            . '<title>TestaCBT news</title><link>' . e(url('/')) . '</link>'
            . '<description>Exam news, result releases, scholarships and study advice from TestaCBT.</description><language>en-ng</language>'
            . '<atom:link href="' . e(url('/feed.xml')) . '" rel="self" type="application/rss+xml"/>' . "\n"
            . $items . "\n</channel></rss>\n";

        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }

    private function entry(string $loc, $lastmod, string $freq, string $priority): string
    {
        $date = $lastmod ? '<lastmod>' . \Illuminate\Support\Carbon::parse($lastmod)->toAtomString() . '</lastmod>' : '';

        return '<url><loc>' . e($loc) . '</loc>' . $date . '<changefreq>' . $freq . '</changefreq><priority>' . $priority . '</priority></url>';
    }
}
