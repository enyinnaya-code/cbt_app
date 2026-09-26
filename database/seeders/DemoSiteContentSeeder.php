<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Exam;
use App\Models\Post;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Made-up news, scholarships, blog articles and events so a developer can see the landing page and console
 * with something on them. Everything is labelled [Demo]. Refuses to run in production.
 */
class DemoSiteContentSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoSiteContentSeeder must never run in production.');
            return;
        }

        $exam = fn (string $slug) => Exam::where('slug', $slug)->value('id');
        $body = '<p>This is a demo article so the page has something to show. Replace it with real content from the admin console.</p>'
            . '<h2>What to do next</h2><ul><li>Check the date carefully.</li><li>Prepare your documents early.</li><li>Practise past questions every day.</li></ul>';

        $posts = [
            ['news', '[Demo] JAMB releases the UTME timetable', $exam('jamb'), 1, true],
            ['exam-news', '[Demo] WAEC announces changes to the SSCE syllabus', $exam('waec'), 3, false],
            ['results', '[Demo] NECO results are out: how to check yours', $exam('neco'), 5, false],
            ['news', '[Demo] New past questions added for Chemistry', null, 7, false],
            ['blog', '[Demo] Five habits of students who score 300 and above', null, 9, false],
            ['blog', '[Demo] How to manage your time in a CBT exam', $exam('jamb'), 12, false],
        ];

        foreach ($posts as [$category, $title, $examId, $daysAgo, $featured]) {
            Post::firstOrCreate(['slug' => Str::slug($title)], [
                'category' => $category, 'title' => $title, 'body' => $body, 'exam_id' => $examId, 'is_featured' => $featured,
                'status' => Post::PUBLISHED, 'published_at' => now()->subDays($daysAgo), 'excerpt' => 'A short summary of the article so cards look right.',
            ]);
        }

        foreach ([['[Demo] Federal Government undergraduate scholarship', 'Federal Government', 21], ['[Demo] Oil company merit award', 'A Nigerian oil company', 45], ['[Demo] Closed foundation grant', 'A foundation', -4]] as [$title, $source, $days]) {
            Post::firstOrCreate(['slug' => Str::slug($title)], [
                'category' => 'scholarship', 'title' => $title, 'source' => $source, 'deadline' => today()->addDays($days), 'body' => $body,
                'status' => Post::PUBLISHED, 'published_at' => now()->subDays(2), 'link_url' => 'https://example.com/apply',
            ]);
        }

        foreach ([
            ['[Demo] JAMB UTME registration closes', 'registration', 12, null, $exam('jamb')],
            ['[Demo] WAEC SSCE begins', 'exam', 40, 62, $exam('waec')],
            ['[Demo] NECO results release', 'results', 75, null, $exam('neco')],
            ['[Demo] Live revision class: Mathematics', 'webinar', 6, null, null],
        ] as [$title, $kind, $in, $length, $examId]) {
            Event::firstOrCreate(['slug' => Str::slug($title)], [
                'title' => $title, 'kind' => $kind, 'starts_on' => today()->addDays($in), 'ends_on' => $length ? today()->addDays($length) : null,
                'exam_id' => $examId, 'status' => 'published', 'location' => $kind === 'webinar' ? 'Online' : 'Nationwide',
            ]);
        }
    }
}
