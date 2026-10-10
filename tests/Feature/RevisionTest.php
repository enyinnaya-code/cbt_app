<?php

namespace Tests\Feature;

use App\Models\Topic;
use App\Models\User;
use App\Services\Engagement;
use App\Services\QuestionSelector;
use App\Services\Revision;
use App\Services\StudentStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

/** Revision sets, the 7-day plan, mastery, improvement and personal bests. */
class RevisionTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    private User $student;
    private array $q = [];
    private Topic $algebra;

    protected function setUp(): void
    {
        parent::setUp();

        [$exam, $subject] = $this->makeExamAndSubject('WAEC', 'Mathematics');
        $this->algebra = Topic::create(['subject_id' => $subject->id, 'name' => 'Algebra', 'slug' => 'algebra']);
        $paper = $this->makePaper($exam, $subject, 2020);
        for ($i = 0; $i < 12; $i++) {
            $this->q[] = $this->makeQuestion($paper, "<p>Q$i</p>", 'B', ['topic_id' => $this->algebra->id]);
        }
        $this->student = $this->makeUser();
    }

    private function attempt(int $i, bool $correct, $when = null): void
    {
        DB::table('question_attempts')->insert([
            'user_id' => $this->student->id, 'client_uuid' => (string) Str::uuid(), 'question_id' => $this->q[$i]->id, 'mode' => 'practice',
            'selected' => $correct ? 'B' : 'A', 'is_correct' => $correct, 'answered_at' => $when ?? now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function revision(): Revision
    {
        return new Revision($this->student, app(QuestionSelector::class));
    }

    public function test_wrong_means_the_latest_answer_was_wrong(): void
    {
        $this->attempt(0, false, now()->subMinutes(5));   // wrong, then fixed
        $this->attempt(0, true);
        $this->attempt(1, true, now()->subMinutes(5));     // right, then slipped
        $this->attempt(1, false);
        $this->attempt(2, false);

        $ids = $this->revision()->ids('wrong');

        $this->assertEqualsCanonicalizing([$this->q[1]->id, $this->q[2]->id], $ids);
    }

    public function test_frequently_missed_needs_two_wrong_answers(): void
    {
        $this->attempt(0, false);
        $this->attempt(0, false);
        $this->attempt(1, false);

        $this->assertSame([$this->q[0]->id], $this->revision()->ids('missed'));
    }

    public function test_recent_only_covers_the_last_seven_days(): void
    {
        $this->attempt(0, true);
        $this->attempt(1, true, now()->subDays(10));

        $this->assertSame([$this->q[0]->id], $this->revision()->ids('recent'));
    }

    public function test_other_students_answers_are_never_included(): void
    {
        $other = $this->makeUser();
        DB::table('question_attempts')->insert([
            'user_id' => $other->id, 'client_uuid' => (string) Str::uuid(), 'question_id' => $this->q[0]->id, 'mode' => 'practice',
            'selected' => 'A', 'is_correct' => 0, 'answered_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame([], $this->revision()->ids('wrong'));
    }

    public function test_a_revision_practice_session_uses_only_those_questions(): void
    {
        foreach (range(0, 5) as $i) {
            $this->attempt($i, false);
        }
        $this->attempt(6, true);

        $response = $this->actingAs($this->student)->get(route('practice.session', ['revision' => 'wrong', 'mode' => 'instant', 'count' => 10]))->assertOk();

        preg_match('#id="session-data">(.*?)</script>#s', $response->getContent(), $m);
        $data = json_decode($m[1], true);
        $this->assertCount(6, $data['questions']);
        $this->assertSame('Questions I got wrong', $data['title']);
        $this->assertNotContains($this->q[6]->id, array_column($data['questions'], 'id'));
    }

    public function test_revision_page_shows_counts_and_a_seven_day_plan(): void
    {
        $this->attempt(0, false);

        $this->actingAs($this->student)->get(route('revision'))
            ->assertOk()->assertSee('Questions I got wrong')->assertSee('Your next 7 days')->assertSee('Mock exam');

        $plan = $this->revision()->plan(new StudentStats($this->student));
        $this->assertCount(7, $plan);
        $this->assertTrue($plan[0]['today']);
        $this->assertSame('Mathematics', $plan[0]['title']);
    }

    public function test_mastery_groups_topics_by_subject(): void
    {
        $this->attempt(0, true);
        $this->attempt(1, false);

        $m = (new StudentStats($this->student))->topicMastery();

        $this->assertSame('Mathematics', $m[0]->subject);
        $this->assertSame(50, $m[0]->topics[0]->accuracy);
    }

    public function test_improvement_compares_the_last_30_days_with_the_30_before(): void
    {
        foreach (range(0, 5) as $i) { $this->attempt($i, $i < 2, now()->subDays(40)); }   // 2 of 6 = 33%
        foreach (range(0, 5) as $i) { $this->attempt($i, $i < 5, now()->subDays(3)); }    // 5 of 6 = 83%

        $imp = (new StudentStats($this->student))->improvement();

        $this->assertSame(33, $imp[0]->before);
        $this->assertSame(83, $imp[0]->now);
        $this->assertSame(50, $imp[0]->change);
    }

    public function test_improvement_needs_enough_answers_in_both_periods(): void
    {
        $this->attempt(0, true, now()->subDays(40));
        $this->attempt(1, true);

        $this->assertCount(0, (new StudentStats($this->student))->improvement());
    }

    public function test_progress_page_shows_the_new_sections(): void
    {
        foreach (range(0, 11) as $i) { $this->attempt($i, $i % 3 !== 0, now()->subDays(2)); }

        $this->actingAs($this->student)->get(route('progress'))
            ->assertOk()->assertSee('Personal bests')->assertSee('Topic mastery')->assertSee('Algebra')->assertSee('Most answered in one day');
    }

    public function test_bests_are_empty_for_a_brand_new_student(): void
    {
        $this->assertSame([], (new StudentStats($this->student))->bests(new Engagement($this->student)));
    }
}
