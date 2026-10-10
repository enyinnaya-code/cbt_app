<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DailyChallenge;
use App\Services\Engagement;
use App\Services\QuestionSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

/** XP, levels, badges and the daily challenge. */
class EngagementTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    private User $student;
    private array $questions = [];

    protected function setUp(): void
    {
        parent::setUp();

        [$exam, $subject] = $this->makeExamAndSubject('WAEC', 'Physics');
        $paper = $this->makePaper($exam, $subject, 2020);
        for ($i = 0; $i < 12; $i++) {
            $this->questions[] = $this->makeQuestion($paper, "<p>Q$i</p>", 'B');
        }
        $this->student = $this->makeUser();
        \App\Services\Leaderboard::join($this->student, ['nickname' => 'Student One', 'avatar' => 'fox']);   // the challenge is opt-in
    }

    private function attempt(int $questionId, bool $correct, ?string $when = null, ?User $user = null): void
    {
        DB::table('question_attempts')->insert([
            'user_id' => ($user ?? $this->student)->id, 'client_uuid' => (string) Str::uuid(), 'question_id' => $questionId,
            'mode' => 'practice', 'selected' => $correct ? 'B' : 'A', 'is_correct' => $correct,
            'answered_at' => $when ?? now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_xp_rewards_correct_answers_and_effort(): void
    {
        $this->attempt($this->questions[0]->id, true);
        $this->attempt($this->questions[1]->id, false);

        $this->assertSame(12, (new Engagement($this->student))->xp());
    }

    public function test_answer_xp_is_capped_per_day_so_one_question_cannot_farm_levels(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->attempt($this->questions[0]->id, true);
        }

        $this->assertSame(config('testacbt.xp.daily_answer_cap'), (new Engagement($this->student))->xp());

        // Another day starts a fresh cap.
        $this->attempt($this->questions[0]->id, true, now()->subDay());
        $this->assertSame(config('testacbt.xp.daily_answer_cap') + 10, (new Engagement($this->student))->xp());
    }

    public function test_level_comes_from_xp(): void
    {
        $e = new Engagement($this->student);

        $this->assertSame(1, $e->level(0)['level']);
        $this->assertSame('Learner', $e->level(100)['title']);
        $this->assertSame(50, $e->level(250)['pct']);          // halfway from 100 to 400
        $this->assertNull($e->level(99999)['next']);
        $this->assertSame(100, $e->level(99999)['pct']);
    }

    public function test_badges_are_awarded_once(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->attempt($this->questions[$i]->id, true);
        }
        $e = new Engagement($this->student);

        $this->assertSame(['first_steps'], $e->award());
        $this->assertSame([], $e->award());
        $this->assertSame(1, DB::table('user_badges')->where('user_id', $this->student->id)->count());
    }

    public function test_seven_day_streak_badge(): void
    {
        foreach (range(0, 6) as $d) {
            $this->attempt($this->questions[0]->id, true, now()->subDays($d));
        }

        $e = new Engagement($this->student);
        $this->assertSame(7, $e->longestStreak());
        $this->assertContains('streak_7', $e->award());
    }

    public function test_challenge_is_stable_for_the_day_and_pays_once(): void
    {
        $challenge = new DailyChallenge($this->student, app(QuestionSelector::class));
        $today = $challenge->today();

        $this->assertCount(10, $today->ids);
        $this->assertSame($today->ids, $challenge->today()->ids);
        $this->assertSame(1, DB::table('daily_challenges')->count());

        // Not complete until every question has an answer.
        $this->attempt($today->ids[0], true);
        $this->assertFalse($challenge->complete()['done']);

        foreach (array_slice($today->ids, 1) as $id) {
            $this->attempt($id, true);
        }
        $first = $challenge->complete();
        $second = $challenge->complete();

        $this->assertTrue($first['newly']);
        $this->assertSame(10, $first['correct']);
        $this->assertFalse($second['newly']);
        $this->assertSame(100, (int) DB::table('daily_challenges')->value('xp_awarded'));

        // 10 correct answers (100) plus the bonus (100).
        $this->assertSame(200, (new Engagement($this->student))->xp());
        $this->assertContains('first_challenge', (new Engagement($this->student))->award());
    }

    public function test_challenge_only_uses_questions_the_student_may_open(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject('JAMB', 'Chemistry', null, 1500);
        $exam->subjects()->updateExistingPivot($subject->id, ['free_questions' => 5]);
        $paper = $this->makePaper($exam, $subject, 2021);
        foreach (range(1, 20) as $i) {
            $this->makeQuestion($paper, "<p>C$i</p>");
        }

        $locked = $this->makeUser(4, ['target_exam' => 'jamb']);
        $c = (new DailyChallenge($locked, app(QuestionSelector::class)))->today();

        // Chemistry is locked and has 5 free questions, which is exactly the minimum for a challenge.
        $this->assertLessThanOrEqual(5, count($c->ids));
    }

    public function test_home_and_challenge_pages_work_end_to_end(): void
    {
        $this->actingAs($this->student)->get(route('dashboard'))
            ->assertOk()->assertSee('Level 1')->assertSee('challenge')->assertSee('Start challenge');

        $this->actingAs($this->student)->get(route('challenge.show'))->assertOk()->assertSee('Daily challenge');

        $ids = (new DailyChallenge($this->student, app(QuestionSelector::class)))->today()->ids;
        foreach ($ids as $id) {
            $this->actingAs($this->student)->postJson(route('practice.attempts'), ['attempts' => [[
                'client_uuid' => (string) Str::uuid(), 'question_id' => $id, 'mode' => 'practice', 'selected' => 'B', 'answered_at' => now()->toIso8601String(),
            ]]])->assertOk();
        }

        $this->actingAs($this->student)->postJson(route('challenge.complete'))
            ->assertOk()->assertJson(['done' => true, 'newly' => true, 'correct' => 10, 'xp' => 100]);

        $this->actingAs($this->student)->get(route('dashboard'))->assertOk()->assertSee('Done');
    }
}
