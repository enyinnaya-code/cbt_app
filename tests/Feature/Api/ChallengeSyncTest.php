<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\DailyChallenge;
use App\Services\Engagement;
use App\Services\QuestionSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

/** A daily challenge finished on the phone pays the bonus once a day, whichever device it was done on. */
class ChallengeSyncTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    private User $user;
    private array $auth;
    private array $q = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->makeUser();
        \App\Services\Leaderboard::join($this->user, ['nickname' => 'Tester One', 'avatar' => 'owl']);
        $this->auth = ['Authorization' => 'Bearer ' . $this->user->createToken('t')->plainTextToken];
        [$exam, $subject] = $this->makeExamAndSubject();
        $paper = $this->makePaper($exam, $subject);
        for ($i = 0; $i < 10; $i++) {
            $this->q[] = $this->makeQuestion($paper, "<p>Q$i</p>", 'B');
        }
    }

    private function attempts(int $count, string $selected = 'B'): array
    {
        return array_map(fn ($q) => [
            'client_uuid' => (string) Str::uuid(), 'question_id' => $q->id, 'mode' => 'practice', 'selected' => $selected,
            'answered_at' => now()->subMinute()->toIso8601String(),
        ], array_slice($this->q, 0, $count));
    }

    private function ids(int $n = 10): array
    {
        return array_map(fn ($q) => $q->id, array_slice($this->q, 0, $n));
    }

    public function test_a_finished_challenge_pays_the_bonus_once(): void
    {
        $body = ['attempts' => $this->attempts(10), 'challenges' => [['date' => now()->toDateString(), 'question_ids' => $this->ids()]]];

        $this->postJson('/api/v1/sync/progress', $body, $this->auth)
            ->assertOk()->assertJsonPath('challenges.0.newly', true)->assertJsonPath('challenges.0.correct', 10)->assertJsonPath('challenges.0.xp', 100);

        // Sending it again pays nothing more.
        $this->postJson('/api/v1/sync/progress', ['challenges' => $body['challenges']], $this->auth)->assertJsonPath('challenges.0.newly', false);

        $this->assertSame(100, (int) DB::table('daily_challenges')->sum('xp_awarded'));
        $this->assertContains('first_challenge', (new Engagement($this->user))->award());
    }

    public function test_the_score_is_recounted_from_the_stored_answers_not_taken_from_the_phone(): void
    {
        $this->postJson('/api/v1/sync/progress', [
            'attempts' => $this->attempts(10, 'A'),   // all wrong
            'challenges' => [['date' => now()->toDateString(), 'question_ids' => $this->ids(), 'correct' => 10]],
        ], $this->auth)->assertOk()->assertJsonPath('challenges.0.correct', 0)->assertJsonPath('challenges.0.done', true);
    }

    public function test_an_unfinished_challenge_pays_nothing(): void
    {
        $this->postJson('/api/v1/sync/progress', [
            'attempts' => $this->attempts(6),
            'challenges' => [['date' => now()->toDateString(), 'question_ids' => $this->ids()]],
        ], $this->auth)->assertOk()->assertJsonPath('challenges.0.done', false)->assertJsonPath('challenges.0.xp', 0);

        $this->assertSame(0, DB::table('daily_challenges')->count());
    }

    public function test_a_challenge_already_paid_on_the_website_is_not_paid_again(): void
    {
        $web = (new DailyChallenge($this->user, app(QuestionSelector::class)))->today();
        foreach ($web->ids as $id) {
            DB::table('question_attempts')->insert(['user_id' => $this->user->id, 'client_uuid' => (string) Str::uuid(), 'question_id' => $id, 'mode' => 'practice', 'selected' => 'B', 'is_correct' => 1, 'answered_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
        (new DailyChallenge($this->user, app(QuestionSelector::class)))->complete();

        $this->postJson('/api/v1/sync/progress', ['challenges' => [['date' => now()->toDateString(), 'question_ids' => $this->ids()]]], $this->auth)
            ->assertJsonPath('challenges.0.newly', false);

        $this->assertSame(100, (int) DB::table('daily_challenges')->sum('xp_awarded'));
    }

    public function test_old_future_and_unopenable_claims_are_refused(): void
    {
        $this->postJson('/api/v1/sync/progress', ['attempts' => $this->attempts(10), 'challenges' => [
            ['date' => now()->subDays(10)->toDateString(), 'question_ids' => $this->ids()],
            ['date' => now()->addDays(3)->toDateString(), 'question_ids' => $this->ids()],
            ['date' => now()->toDateString(), 'question_ids' => [999991, 999992, 999993, 999994, 999995]],
        ]], $this->auth)->assertOk()->assertJsonCount(0, 'challenges');

        $this->assertSame(0, DB::table('daily_challenges')->count());
    }

    public function test_pull_returns_completed_challenges_so_a_new_phone_gets_the_bonus(): void
    {
        $this->postJson('/api/v1/sync/progress', ['attempts' => $this->attempts(10), 'challenges' => [['date' => now()->toDateString(), 'question_ids' => $this->ids()]]], $this->auth);

        $this->getJson('/api/v1/sync/progress', $this->auth)->assertOk()
            ->assertJsonPath('challenges.0.date', now()->toDateString())->assertJsonPath('challenges.0.xp', 100)->assertJsonPath('challenges.0.total', 10);
    }
}
