<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

class SiteContentTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    private function makePost(array $over = []): Post
    {
        static $n = 0;
        $n++;

        return Post::create($over + [
            'category' => 'news', 'title' => "Post $n", 'slug' => "post-$n", 'body' => '<p>Body text here.</p>',
            'status' => Post::PUBLISHED, 'published_at' => now()->subHour(),
        ]);
    }

    private function makeEvent(array $over = []): Event
    {
        static $n = 0;
        $n++;

        return Event::create($over + ['title' => "Event $n", 'slug' => "event-$n", 'kind' => 'exam', 'starts_on' => today()->addDays(10), 'status' => 'published']);
    }

    // ------------------------------------------------------------------------------------- public pages

    public function test_landing_shows_live_news_and_hides_drafts_and_scheduled_posts(): void
    {
        $this->makePost(['title' => 'WAEC timetable released']);
        $this->makePost(['title' => 'Secret draft', 'status' => Post::DRAFT]);
        $this->makePost(['title' => 'Coming next week', 'published_at' => now()->addWeek()]);

        $this->get('/')->assertOk()
            ->assertSee('WAEC timetable released')
            ->assertDontSee('Secret draft')
            ->assertDontSee('Coming next week');
    }

    public function test_landing_lists_upcoming_events_but_not_past_ones(): void
    {
        $this->makeEvent(['title' => 'JAMB registration closes']);
        $this->makeEvent(['title' => 'Old exam date', 'starts_on' => today()->subDays(5)]);
        $this->makeEvent(['title' => 'Hidden draft event', 'status' => 'draft']);
        $this->makeEvent(['title' => 'Multi-day exam running now', 'starts_on' => today()->subDay(), 'ends_on' => today()->addDays(3)]);

        $this->get('/')->assertOk()
            ->assertSee('JAMB registration closes')
            ->assertSee('Multi-day exam running now')
            ->assertDontSee('Old exam date')
            ->assertDontSee('Hidden draft event');
    }

    public function test_landing_shows_only_open_scholarships_soonest_deadline_first(): void
    {
        $this->makePost(['category' => 'scholarship', 'title' => 'Later award', 'deadline' => today()->addDays(60)]);
        $this->makePost(['category' => 'scholarship', 'title' => 'Sooner award', 'deadline' => today()->addDays(5)]);
        $this->makePost(['category' => 'scholarship', 'title' => 'Expired award', 'deadline' => today()->subDay()]);

        $this->get('/')->assertOk()
            ->assertSeeInOrder(['Sooner award', 'Later award'])
            ->assertDontSee('Expired award');
    }

    public function test_landing_says_coming_soon_until_store_links_are_set_then_links_to_them(): void
    {
        $this->get('/')->assertOk()->assertSee('Coming soon');

        Setting::put(['app.play_store_url' => 'https://play.google.com/store/apps/details?id=com.testacbt.app']);

        $this->get('/')->assertOk()
            ->assertSee('https://play.google.com/store/apps/details?id=com.testacbt.app', false)
            ->assertSee('Google Play');
    }

    public function test_download_link_sends_each_phone_to_its_own_store(): void
    {
        Setting::put(['app.play_store_url' => 'https://play.google.com/x', 'app.app_store_url' => 'https://apps.apple.com/y']);

        $this->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 13; Tecno)')->get('/download')->assertRedirect('https://play.google.com/x');
        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)')->get('/download')->assertRedirect('https://apps.apple.com/y');
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0)')->get('/download')->assertRedirect(route('welcome') . '#download');
    }

    public function test_download_link_falls_back_to_the_apk_and_then_to_the_landing_page(): void
    {
        $this->withHeader('User-Agent', 'Android')->get('/download')->assertRedirect(route('welcome') . '#download');

        Setting::put(['app.apk_url' => 'https://testacbt.com/testacbt.apk']);
        $this->withHeader('User-Agent', 'Android')->get('/download')->assertRedirect('https://testacbt.com/testacbt.apk');
    }

    public function test_news_page_filters_by_category_and_search(): void
    {
        $this->makePost(['category' => 'news', 'title' => 'General announcement']);
        $this->makePost(['category' => 'results', 'title' => 'NECO results out']);
        $this->makePost(['category' => 'blog', 'title' => 'Study tips']);

        $this->get('/news')->assertOk()->assertSee('General announcement')->assertSee('NECO results out')->assertDontSee('Study tips');
        $this->get('/news?category=results')->assertOk()->assertSee('NECO results out')->assertDontSee('General announcement');
        $this->get('/news?q=announcement')->assertOk()->assertSee('General announcement')->assertDontSee('NECO results out');
        $this->get('/blog')->assertOk()->assertSee('Study tips')->assertDontSee('General announcement');
    }

    public function test_an_unknown_category_filter_is_ignored(): void
    {
        $this->makePost(['title' => 'Visible news']);

        $this->get('/news?category=blog')->assertOk()->assertSee('Visible news');
    }

    public function test_scholarship_page_puts_open_ones_before_closed_ones(): void
    {
        $this->makePost(['category' => 'scholarship', 'title' => 'Closed one', 'deadline' => today()->subDays(3), 'published_at' => now()->subMinute()]);
        $this->makePost(['category' => 'scholarship', 'title' => 'Open one', 'deadline' => today()->addDays(9), 'published_at' => now()->subDays(2)]);

        $this->get('/scholarships')->assertOk()->assertSeeInOrder(['Open one', 'Closed one']);
    }

    public function test_an_article_page_shows_and_a_draft_is_hidden_from_the_public(): void
    {
        $live = $this->makePost(['title' => 'Live article', 'slug' => 'live-article']);
        $draft = $this->makePost(['title' => 'Draft article', 'slug' => 'draft-article', 'status' => Post::DRAFT]);
        $later = $this->makePost(['title' => 'Scheduled article', 'slug' => 'scheduled-article', 'published_at' => now()->addDay()]);

        $this->get('/articles/live-article')->assertOk()->assertSee('Live article')->assertSee('Body text here.');
        $this->get('/articles/draft-article')->assertNotFound();
        $this->get('/articles/scheduled-article')->assertNotFound();
        $this->get('/articles/missing')->assertNotFound();

        $student = $this->makeUser(4);
        $this->actingAs($student)->get('/articles/draft-article')->assertNotFound();

        $admin = $this->makeUser(2);
        $this->actingAs($admin)->get('/articles/draft-article')->assertOk()->assertSee('not live yet');
    }

    public function test_events_page_separates_upcoming_from_recently_passed(): void
    {
        $this->makeEvent(['title' => 'Next exam']);
        $this->makeEvent(['title' => 'Last month result', 'starts_on' => today()->subDays(20)]);

        $this->get('/events')->assertOk()->assertSee('Next exam')->assertSee('Recently passed')->assertSee('Last month result');
    }

    public function test_event_dates_read_naturally(): void
    {
        $same = new Event(['starts_on' => '2027-01-12', 'ends_on' => '2027-01-16']);
        $across = new Event(['starts_on' => '2027-01-28', 'ends_on' => '2027-02-03']);
        $single = new Event(['starts_on' => '2027-01-12']);

        $this->assertSame('12 to 16 Jan 2027', $same->dateLabel());
        $this->assertSame('28 Jan to 3 Feb 2027', $across->dateLabel());
        $this->assertSame('12 Jan 2027', $single->dateLabel());
    }

    public function test_public_pages_work_for_a_signed_in_student_too(): void
    {
        $student = $this->makeUser(4);

        foreach (['/news', '/scholarships', '/blog', '/events'] as $url) {
            $this->actingAs($student)->get($url)->assertOk()->assertSee('Open my account');
        }
    }

    // ------------------------------------------------------------------------------------- console

    private function form(array $over = []): array
    {
        return $over + ['category' => 'news', 'title' => 'New exam timetable', 'body' => '<p>Read this.</p>', 'status' => 'published'];
    }

    public function test_only_admins_can_reach_the_content_screens(): void
    {
        $examiner = $this->makeUser(3);
        $student = $this->makeUser(4);
        $admin = $this->makeUser(2);

        $urls = ['/console/posts', '/console/posts/create', '/console/events', '/console/settings'];

        foreach ($urls as $url) {
            $this->get($url)->assertRedirect('/login');
        }

        foreach ($urls as $url) {
            $this->actingAs($student)->get($url)->assertForbidden();
            $this->actingAs($examiner)->get($url)->assertForbidden();
            $this->actingAs($admin)->get($url)->assertOk();
        }

        $this->actingAs($examiner)->post('/console/posts', $this->form())->assertForbidden();
        $this->assertSame(0, Post::count());
    }

    public function test_an_admin_publishes_an_article_which_then_appears_on_the_site(): void
    {
        $admin = $this->makeUser(2);

        $this->actingAs($admin)->post('/console/posts', $this->form())->assertRedirect();

        $post = Post::firstOrFail();
        $this->assertSame('new-exam-timetable', $post->slug);
        $this->assertSame($admin->id, $post->author_id);
        $this->assertNotNull($post->published_at);
        $this->get('/news')->assertSee('New exam timetable');
    }

    public function test_a_draft_stays_hidden_and_a_future_date_schedules_it(): void
    {
        $admin = $this->makeUser(2);

        $this->actingAs($admin)->post('/console/posts', $this->form(['title' => 'Draft one', 'status' => 'draft']));
        $this->actingAs($admin)->post('/console/posts', $this->form(['title' => 'Scheduled one', 'published_at' => now()->addDays(2)->format('Y-m-d\TH:i')]));

        $this->get('/news')->assertDontSee('Draft one')->assertDontSee('Scheduled one');
        $this->assertSame('draft', Post::where('title', 'Draft one')->value('status'));
    }

    public function test_the_article_body_is_sanitised_when_saved(): void
    {
        $admin = $this->makeUser(2);

        $this->actingAs($admin)->post('/console/posts', $this->form([
            'body' => '<p>Safe</p><script>alert(1)</script><img src="x" onerror="alert(2)"><a href="javascript:alert(3)">bad</a>',
        ]));

        $body = Post::firstOrFail()->body;
        $this->assertStringContainsString('Safe', $body);
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('onerror', $body);
        $this->assertStringNotContainsString('javascript:', $body);
    }

    public function test_titles_that_collide_get_their_own_addresses(): void
    {
        $admin = $this->makeUser(2);

        $this->actingAs($admin)->post('/console/posts', $this->form());
        $this->actingAs($admin)->post('/console/posts', $this->form());

        $this->assertEqualsCanonicalizing(['new-exam-timetable', 'new-exam-timetable-2'], Post::pluck('slug')->all());
    }

    public function test_a_published_article_keeps_its_address_when_the_title_changes(): void
    {
        $admin = $this->makeUser(2);
        $post = $this->makePost(['title' => 'Original', 'slug' => 'original']);

        $this->actingAs($admin)->put("/console/posts/{$post->id}", $this->form(['title' => 'Completely new title']))->assertRedirect();

        $this->assertSame('original', $post->fresh()->slug);
        $this->assertSame('Completely new title', $post->fresh()->title);
    }

    public function test_scholarship_details_are_kept_only_for_scholarships(): void
    {
        $admin = $this->makeUser(2);

        $this->actingAs($admin)->post('/console/posts', $this->form(['category' => 'scholarship', 'title' => 'Award', 'deadline' => '2027-03-01', 'source' => 'A Trust']));
        $this->actingAs($admin)->post('/console/posts', $this->form(['category' => 'news', 'title' => 'Not one', 'deadline' => '2027-03-01', 'source' => 'A Trust']));

        $this->assertSame('2027-03-01', Post::where('title', 'Award')->firstOrFail()->deadline->format('Y-m-d'));
        $this->assertNull(Post::where('title', 'Not one')->firstOrFail()->deadline);
        $this->assertNull(Post::where('title', 'Not one')->firstOrFail()->source);
    }

    public function test_article_validation_rejects_bad_input(): void
    {
        $admin = $this->makeUser(2);

        $this->actingAs($admin)->post('/console/posts', $this->form(['category' => 'nonsense']))->assertSessionHasErrors('category');
        $this->actingAs($admin)->post('/console/posts', $this->form(['title' => '']))->assertSessionHasErrors('title');
        $this->actingAs($admin)->post('/console/posts', $this->form(['link_url' => 'javascript:alert(1)']))->assertSessionHasErrors('link_url');
        $this->actingAs($admin)->post('/console/posts', $this->form(['cover' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')]))->assertSessionHasErrors('cover');
        $this->assertSame(0, Post::count());
    }

    public function test_a_cover_picture_can_be_added_replaced_and_removed(): void
    {
        $admin = $this->makeUser(2);

        $this->actingAs($admin)->post('/console/posts', $this->form(['cover' => UploadedFile::fake()->image('a.jpg', 800, 450)]));
        $post = Post::firstOrFail();
        $first = public_path($post->cover_path);
        $this->assertFileExists($first);

        $this->actingAs($admin)->put("/console/posts/{$post->id}", $this->form(['cover' => UploadedFile::fake()->image('b.png', 800, 450)]));
        $second = public_path($post->fresh()->cover_path);
        $this->assertFileDoesNotExist($first);
        $this->assertFileExists($second);

        $this->actingAs($admin)->put("/console/posts/{$post->id}", $this->form(['remove_cover' => 1]));
        $this->assertNull($post->fresh()->cover_path);
        $this->assertFileDoesNotExist($second);
    }

    public function test_an_unwritable_upload_folder_gives_a_clear_message_not_a_server_error(): void
    {
        $admin = $this->makeUser(2);
        $folder = public_path('uploads/posts');
        @mkdir(dirname($folder), 0777, true);
        if (is_dir($folder) && ! @rmdir($folder)) { $this->markTestSkipped('public/uploads/posts has files in it.'); }
        file_put_contents($folder, 'not a folder');   // a file where the folder should be, so it cannot be created

        try {
            $this->actingAs($admin)->post('/console/posts', $this->form(['cover' => UploadedFile::fake()->image('a.jpg', 800, 450)]))
                ->assertSessionHasErrors('cover');
            $this->assertSame(0, Post::count());
        } finally {
            unlink($folder);
        }
    }

    public function test_deleting_an_article_removes_it_and_its_picture(): void
    {
        $admin = $this->makeUser(2);
        $this->actingAs($admin)->post('/console/posts', $this->form(['cover' => UploadedFile::fake()->image('a.jpg', 800, 450)]));
        $post = Post::firstOrFail();
        $file = public_path($post->cover_path);

        $this->actingAs($admin)->delete("/console/posts/{$post->id}")->assertRedirect(route('console.posts.index'));

        $this->assertSame(0, Post::count());
        $this->assertFileDoesNotExist($file);
    }

    public function test_an_admin_manages_events(): void
    {
        $admin = $this->makeUser(2);

        $this->actingAs($admin)->post('/console/events', [
            'title' => 'UTME registration closes', 'kind' => 'registration', 'starts_on' => today()->addDays(3)->toDateString(), 'status' => 'published',
        ])->assertRedirect(route('console.events.index'));

        $event = Event::firstOrFail();
        $this->assertSame('utme-registration-closes', $event->slug);
        $this->get('/events')->assertSee('UTME registration closes');

        $this->actingAs($admin)->put("/console/events/{$event->id}", [
            'title' => 'UTME registration extended', 'kind' => 'registration', 'starts_on' => today()->addDays(6)->toDateString(), 'status' => 'published',
        ]);
        $this->assertSame('UTME registration extended', $event->fresh()->title);

        $this->actingAs($admin)->delete("/console/events/{$event->id}");
        $this->assertSame(0, Event::count());
    }

    public function test_an_event_cannot_end_before_it_starts(): void
    {
        $admin = $this->makeUser(2);

        $this->actingAs($admin)->post('/console/events', [
            'title' => 'Backwards', 'kind' => 'exam', 'starts_on' => '2027-02-10', 'ends_on' => '2027-02-01', 'status' => 'published',
        ])->assertSessionHasErrors('ends_on');
    }

    public function test_settings_are_saved_and_validated(): void
    {
        $admin = $this->makeUser(2);

        $this->actingAs($admin)->put('/console/settings', [
            'app_play_store_url' => 'https://play.google.com/store/apps/details?id=com.testacbt.app',
            'app_app_store_url' => '', 'app_apk_url' => '', 'support_email' => 'help@testacbt.com', 'support_whatsapp' => '',
        ])->assertRedirect();

        $this->assertSame('help@testacbt.com', Setting::get('support.email'));
        $this->assertNull(Setting::get('app.app_store_url'));

        $this->actingAs($admin)->put('/console/settings', ['app_play_store_url' => 'http://not-secure.example', 'support_email' => 'nope'])
            ->assertSessionHasErrors(['app_play_store_url', 'support_email']);
    }
}
