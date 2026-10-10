<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

/** Announcements: the admin writes and switches them on, students see a closable card on the website and in the app. */
class NoticeTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    private function notice(array $over = []): Notice
    {
        return Notice::create($over + ['title' => 'WAEC timetable is out', 'body' => 'Check your dates.', 'is_active' => true]);
    }

    public function test_an_active_announcement_shows_on_the_student_home_with_a_close_button(): void
    {
        $this->notice();

        $this->actingAs($this->makeUser(4))->get('/dashboard')->assertOk()
            ->assertSee('WAEC timetable is out')->assertSee('Check your dates.')->assertSee('Close this announcement');
    }

    public function test_a_switched_off_expired_or_not_yet_started_announcement_is_hidden(): void
    {
        $this->notice(['title' => 'Off one', 'is_active' => false]);
        $this->notice(['title' => 'Old one', 'ends_at' => now()->subHour()]);
        $this->notice(['title' => 'Later one', 'starts_at' => now()->addDay()]);

        $this->actingAs($this->makeUser(4))->get('/dashboard')->assertOk()
            ->assertDontSee('Off one')->assertDontSee('Old one')->assertDontSee('Later one');
    }

    public function test_the_audience_decides_where_it_shows(): void
    {
        $this->notice(['title' => 'Web only', 'audience' => 'web']);
        $this->notice(['title' => 'App only', 'audience' => 'app']);
        $student = $this->makeUser(4, ['is_active' => 1]);

        $this->actingAs($student)->get('/dashboard')->assertSee('Web only')->assertDontSee('App only');

        $titles = $this->actingAs($student, 'sanctum')->getJson('/api/v1/notices')->assertOk()->json('notices.*.title');
        $this->assertSame(['App only'], $titles);
    }

    public function test_the_app_gets_the_newest_few_and_needs_a_sign_in(): void
    {
        $this->getJson('/api/v1/notices')->assertUnauthorized();

        foreach (range(1, 5) as $n) { $this->notice(['title' => "Note {$n}"]); }

        $titles = $this->actingAs($this->makeUser(4, ['is_active' => 1]), 'sanctum')->getJson('/api/v1/notices')->json('notices.*.title');
        $this->assertSame(['Note 5', 'Note 4', 'Note 3'], $titles);
    }

    public function test_an_admin_creates_edits_switches_off_and_deletes_one(): void
    {
        $admin = $this->makeUser(2);

        $this->actingAs($admin)->post('/console/notices', [
            'title' => 'Maintenance tonight', 'body' => 'Back by 6am.', 'tone' => 'warning', 'audience' => 'all', 'is_active' => 1,
            'link_url' => 'https://testacbt.com/news', 'link_label' => 'Details',
        ])->assertRedirect('/console/notices');
        $notice = Notice::firstOrFail();
        $this->assertTrue($notice->isShowing());

        $this->actingAs($admin)->put("/console/notices/{$notice->id}", ['title' => 'Maintenance moved', 'tone' => 'warning', 'audience' => 'all', 'is_active' => 1])->assertRedirect();
        $this->assertSame('Maintenance moved', $notice->fresh()->title);
        $this->assertNull($notice->fresh()->link_label, 'a label without a link is dropped');

        $this->actingAs($admin)->patch("/console/notices/{$notice->id}/toggle")->assertRedirect();
        $this->assertFalse($notice->fresh()->is_active);

        $this->actingAs($admin)->delete("/console/notices/{$notice->id}")->assertRedirect('/console/notices');
        $this->assertSame(0, Notice::count());
    }

    public function test_only_admins_manage_announcements_and_bad_links_are_refused(): void
    {
        $student = $this->makeUser(4, ['is_active' => 1]);
        $examiner = $this->makeUser(3);
        $notice = $this->notice();

        $this->actingAs($student)->get('/console/notices')->assertForbidden();
        $this->actingAs($examiner)->get('/console/notices')->assertForbidden();
        $this->actingAs($examiner)->delete("/console/notices/{$notice->id}")->assertForbidden();

        $bad = $this->actingAs($this->makeUser(2))->post('/console/notices', [
            'title' => 'Hi', 'tone' => 'info', 'audience' => 'all', 'link_url' => 'javascript:alert(1)',
        ]);
        $bad->assertSessionHasErrors('link_url');
        $this->assertSame(1, Notice::count());
    }

    public function test_the_text_is_escaped_so_an_announcement_cannot_inject_script(): void
    {
        $this->notice(['title' => '<script>alert(1)</script>', 'body' => '<img src=x onerror=alert(1)>']);

        $html = $this->actingAs($this->makeUser(4))->get('/dashboard')->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
    }
}
