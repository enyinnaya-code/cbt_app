<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    private User $user;
    private array $auth;
    private $exam;
    private $subject;
    private $paper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->makeUser();
        $this->auth = ['Authorization' => 'Bearer ' . $this->user->createToken('t')->plainTextToken];
        [$this->exam, $this->subject] = $this->makeExamAndSubject();
        $this->paper = $this->makePaper($this->exam, $this->subject);
    }

    private function attempt(int $questionId, ?string $selected, array $extra = []): array
    {
        return $extra + [
            'client_uuid' => (string) Str::uuid(), 'question_id' => $questionId, 'mode' => 'practice',
            'selected' => $selected, 'answered_at' => now()->subMinute()->toIso8601String(),
        ];
    }

    public function test_the_server_decides_whether_an_answer_is_correct(): void
    {
        $q = $this->makeQuestion($this->paper, '<p>Q</p>', 'B');

        $this->postJson('/api/v1/sync/progress', ['attempts' => [
            $this->attempt($q->id, 'B'),
            $this->attempt($q->id, 'a'),
            $this->attempt($q->id, null),
            $this->attempt($q->id, 'B', ['is_correct' => false]),   // a phone cannot overrule the key
        ]], $this->auth)->assertOk()->assertJsonPath('accepted.attempts', 4);

        $this->assertSame([true, false, false, true], $this->user->id ? \DB::table('question_attempts')->orderBy('id')->pluck('is_correct')->map(fn ($v) => (bool) $v)->all() : []);
        $this->assertSame('A', \DB::table('question_attempts')->where('id', 2)->value('selected'), 'lowercase is normalised');
    }

    public function test_resending_the_same_batch_does_not_double_count(): void
    {
        $q = $this->makeQuestion($this->paper);
        $batch = ['attempts' => [$this->attempt($q->id, 'B'), $this->attempt($q->id, 'C')]];

        $this->postJson('/api/v1/sync/progress', $batch, $this->auth)->assertJsonPath('accepted.attempts', 2);
        $this->postJson('/api/v1/sync/progress', $batch, $this->auth)->assertJsonPath('accepted.attempts', 0);

        $this->assertSame(2, \DB::table('question_attempts')->count());
    }

    public function test_passages_and_unknown_questions_are_rejected_not_fatal(): void
    {
        $passage = $this->makeQuestion($this->paper, '<p>Passage</p>', '', ['not_question' => 1]);
        $real = $this->makeQuestion($this->paper);

        $this->postJson('/api/v1/sync/progress', ['attempts' => [
            $this->attempt($passage->id, 'A'),
            $this->attempt(999999, 'A'),
            $this->attempt($real->id, 'B'),
        ]], $this->auth)->assertOk()->assertJsonPath('accepted.attempts', 1)->assertJsonPath('rejected.attempts', 2);
    }

    public function test_dates_in_the_future_are_pulled_back_to_now(): void
    {
        $q = $this->makeQuestion($this->paper);

        $this->postJson('/api/v1/sync/progress', ['attempts' => [
            $this->attempt($q->id, 'B', ['answered_at' => now()->addYears(3)->toIso8601String()]),
        ]], $this->auth)->assertOk();

        $this->assertTrue(\DB::table('question_attempts')->value('answered_at') <= now()->addMinute()->toDateTimeString());
    }

    public function test_validation_rejects_bad_input(): void
    {
        $this->postJson('/api/v1/sync/progress', ['attempts' => [
            ['client_uuid' => 'not-a-uuid', 'question_id' => 1, 'mode' => 'exam', 'selected' => 'Z', 'answered_at' => 'nope'],
        ]], $this->auth)->assertStatus(422)->assertJsonValidationErrors(['attempts.0.client_uuid', 'attempts.0.mode', 'attempts.0.selected', 'attempts.0.answered_at']);

        $tooMany = array_fill(0, 501, $this->attempt(1, 'A'));
        $this->postJson('/api/v1/sync/progress', ['attempts' => $tooMany], $this->auth)->assertStatus(422);
    }

    public function test_bookmarks_newest_change_wins(): void
    {
        $q = $this->makeQuestion($this->paper);
        $t1 = now()->subHours(3)->toIso8601String();
        $t2 = now()->subHours(2)->toIso8601String();
        $t3 = now()->subHours(1)->toIso8601String();

        $this->postJson('/api/v1/sync/progress', ['bookmarks' => [['question_id' => $q->id, 'bookmarked' => true, 'changed_at' => $t2]]], $this->auth)
            ->assertJsonPath('accepted.bookmarks', 1);

        // An older change arriving late (say, from a second phone) must not undo the newer one.
        $this->postJson('/api/v1/sync/progress', ['bookmarks' => [['question_id' => $q->id, 'bookmarked' => false, 'changed_at' => $t1]]], $this->auth)
            ->assertJsonPath('accepted.bookmarks', 0);
        $this->assertTrue((bool) \DB::table('bookmarks')->value('is_bookmarked'));

        $this->postJson('/api/v1/sync/progress', ['bookmarks' => [['question_id' => $q->id, 'bookmarked' => false, 'changed_at' => $t3]]], $this->auth)
            ->assertJsonPath('accepted.bookmarks', 1);
        $this->assertFalse((bool) \DB::table('bookmarks')->value('is_bookmarked'));
        $this->assertSame(1, \DB::table('bookmarks')->count());
    }

    public function test_times_sent_in_utc_are_handled_correctly_when_the_server_is_not_in_utc(): void
    {
        $this->assertNotSame('UTC', config('app.timezone'), 'this test only means something on a non-UTC server');

        $q = $this->makeQuestion($this->paper);
        $utc = now()->subMinutes(5)->utc()->format('Y-m-d\TH:i:s\Z');   // what a phone sends
        $batch = [
            'attempts' => [$this->attempt($q->id, 'B', ['answered_at' => $utc])],
            'bookmarks' => [['question_id' => $q->id, 'bookmarked' => true, 'changed_at' => $utc]],
        ];

        $this->postJson('/api/v1/sync/progress', $batch, $this->auth)->assertJsonPath('accepted.bookmarks', 1);
        // The same batch again (a retry) must not count as a newer change, however far the server is from UTC.
        $this->postJson('/api/v1/sync/progress', $batch, $this->auth)
            ->assertJsonPath('accepted.bookmarks', 0)->assertJsonPath('accepted.attempts', 0);

        $r = $this->getJson('/api/v1/sync/progress', $this->auth)->assertOk();
        foreach ([$r->json('attempts.0.answered_at'), $r->json('bookmarks.0.changed_at')] as $returned) {
            $this->assertMatchesRegularExpression('/(Z|[+-]\d{2}:\d{2})$/', $returned, 'timestamps carry their offset');
            $this->assertSame(strtotime($utc), strtotime($returned), 'and name the same instant the phone sent');
        }

        // since= in UTC returns nothing that was already delivered.
        $later = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $this->getJson('/api/v1/sync/progress?since=' . urlencode($later) . '&cursor=1', $this->auth)
            ->assertJsonPath('bookmarks', [])->assertJsonPath('attempts', []);
    }

    public function test_mock_sessions_are_stored_once(): void
    {
        $mock = ['client_uuid' => (string) Str::uuid(), 'exam_id' => $this->exam->id, 'score' => 268, 'total' => 400,
            'duration_seconds' => 6480, 'taken_at' => now()->subHour()->toIso8601String(),
            'subject_scores' => [['subject_id' => $this->subject->id, 'correct' => 40, 'total' => 60]]];

        $this->postJson('/api/v1/sync/progress', ['mock_sessions' => [$mock]], $this->auth)->assertJsonPath('accepted.mock_sessions', 1);
        $this->postJson('/api/v1/sync/progress', ['mock_sessions' => [$mock]], $this->auth)->assertJsonPath('accepted.mock_sessions', 0);

        $r = $this->getJson('/api/v1/sync/progress', $this->auth)->assertOk();
        $this->assertSame(268, $r->json('mock_sessions.0.score'));
        $this->assertSame(40, $r->json('mock_sessions.0.subject_scores.0.correct'));
    }

    public function test_pull_restores_progress_and_is_private_to_each_student(): void
    {
        $q = $this->makeQuestion($this->paper);
        $this->postJson('/api/v1/sync/progress', [
            'attempts' => [$this->attempt($q->id, 'B')],
            'bookmarks' => [['question_id' => $q->id, 'bookmarked' => true, 'changed_at' => now()->subMinute()->toIso8601String()]],
        ], $this->auth)->assertOk();

        $r = $this->getJson('/api/v1/sync/progress', $this->auth)->assertOk();
        $this->assertCount(1, $r->json('attempts'));
        $this->assertTrue($r->json('attempts.0.is_correct'));
        $this->assertSame($q->id, $r->json('bookmarks.0.question_id'));
        $this->assertTrue($r->json('bookmarks.0.bookmarked'));

        // Where each record came from, so a new phone can show progress before downloading any pack.
        $this->assertSame([$this->exam->id, $this->subject->id, 2019], [$r->json('attempts.0.exam_id'), $r->json('attempts.0.subject_id'), $r->json('attempts.0.year')]);
        $this->assertNull($r->json('attempts.0.topic_id'));
        $this->assertSame([$this->exam->id, $this->subject->id], [$r->json('bookmarks.0.exam_id'), $r->json('bookmarks.0.subject_id')]);

        // Within one test Laravel keeps the first request's user cached; a real second request starts fresh.
        \Illuminate\Support\Facades\Auth::forgetGuards();

        $other = ['Authorization' => 'Bearer ' . $this->makeUser()->createToken('t')->plainTextToken];
        $r2 =$this->getJson('/api/v1/sync/progress', $other)->assertOk();
        $this->assertSame([], $r2->json('attempts'));
        $this->assertSame([], $r2->json('bookmarks'));
    }

    public function test_pull_pages_through_attempts_with_a_cursor(): void
    {
        $q = $this->makeQuestion($this->paper);
        foreach (array_chunk(range(1, 1200), 400) as $chunk) {
            $this->postJson('/api/v1/sync/progress', ['attempts' => array_map(fn () => $this->attempt($q->id, 'B'), $chunk)], $this->auth)->assertOk();
        }

        $page1 = $this->getJson('/api/v1/sync/progress', $this->auth)->assertOk();
        $this->assertCount(1000, $page1->json('attempts'));
        $this->assertTrue($page1->json('has_more'));

        $page2 = $this->getJson('/api/v1/sync/progress?cursor=' . $page1->json('next_cursor'), $this->auth)->assertOk();
        $this->assertCount(200, $page2->json('attempts'));
        $this->assertFalse($page2->json('has_more'));
    }

    public function test_sync_needs_a_token(): void
    {
        $this->postJson('/api/v1/sync/progress', [])->assertStatus(401);
        $this->getJson('/api/v1/sync/progress')->assertStatus(401);
    }
}
