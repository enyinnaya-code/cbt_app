<?php

namespace Tests\Feature;

use App\Models\Entitlement;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Services\Access;
use App\Services\ProgressRecorder;
use App\Services\QuestionSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

/** What is free, what is locked, and what a purchase opens. */
class AccessTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    private Exam $exam;
    private Subject $subject;
    private User $student;
    /** @var array<int,Question> 2019 paper first, then the newer 2021 paper */
    private array $old = [];
    private array $new = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Priced at 1,500 with 3 free questions.
        [$this->exam, $this->subject] = $this->makeExamAndSubject('JAMB', 'Physics', null, 1500);
        $this->exam->subjects()->updateExistingPivot($this->subject->id, ['free_questions' => 3]);

        $older = $this->makePaper($this->exam, $this->subject, 2019);
        $newer = $this->makePaper($this->exam, $this->subject, 2021);
        foreach (range(1, 5) as $i) { $this->old[] = $this->makeQuestion($older, "<p>Old $i</p>"); }
        foreach (range(1, 5) as $i) { $this->new[] = $this->makeQuestion($newer, "<p>New $i</p>"); }

        $this->student = $this->makeUser(4);
    }

    private function buy(?User $user = null, string $expires = '+30 days'): void
    {
        Entitlement::create(['user_id' => ($user ?? $this->student)->id, 'exam_id' => $this->exam->id, 'subject_id' => $this->subject->id, 'expires_at' => now()->modify($expires)]);
    }

    private function sessionIds(User $user, array $query = []): array
    {
        $response = $this->actingAs($user)->get('/practice/session?' . http_build_query($query + [
            'exam' => $this->exam->slug, 'subject' => $this->subject->slug, 'mode' => 'instant', 'count' => 50,
        ]));

        $response->assertOk();

        return collect($response->viewData('session')['questions'])->pluck('id')->all();
    }

    public function test_the_free_sample_is_the_newest_years_first_questions(): void
    {
        $free = app(QuestionSelector::class)->freeIds($this->exam->id, $this->subject->id, 3);

        $this->assertSame([$this->new[0]->id, $this->new[1]->id, $this->new[2]->id], $free);
    }

    public function test_the_free_sample_skips_questions_that_cannot_be_used_and_passages(): void
    {
        $paper = $this->makePaper($this->exam, $this->subject, 2023);
        $this->makeQuestion($paper, '<p>Read this passage</p>', 'A', ['not_question' => 1]);
        $this->makeQuestion($paper, '<p>No answer key</p>', '');
        $usable = $this->makeQuestion($paper, '<p>Good</p>');

        $free = app(QuestionSelector::class)->freeIds($this->exam->id, $this->subject->id, 1);

        $this->assertSame([$usable->id], $free);
        $this->assertSame([], app(QuestionSelector::class)->freeIds($this->exam->id, $this->subject->id, 0));
    }

    public function test_a_student_who_has_not_paid_practises_only_the_free_sample(): void
    {
        $ids = $this->sessionIds($this->student);

        $this->assertEqualsCanonicalizing([$this->new[0]->id, $this->new[1]->id, $this->new[2]->id], $ids);
    }

    public function test_choosing_a_year_with_no_free_questions_gives_nothing(): void
    {
        $this->actingAs($this->student)->get('/practice/session?' . http_build_query([
            'exam' => $this->exam->slug, 'subject' => $this->subject->slug, 'year' => 2019, 'mode' => 'instant', 'count' => 20,
        ]))->assertRedirect()->assertSessionHas('error');
    }

    public function test_a_purchase_opens_every_question(): void
    {
        $this->buy();

        $this->assertCount(10, $this->sessionIds($this->student));
    }

    public function test_an_expired_purchase_locks_the_subject_again(): void
    {
        $this->buy(expires: '-1 minute');

        $this->assertCount(3, $this->sessionIds($this->student));
    }

    public function test_a_purchase_for_another_exam_does_not_open_this_one(): void
    {
        [$waec, $physics] = $this->makeExamAndSubject('WAEC', 'Physics', null, 1500);
        Entitlement::create(['user_id' => $this->student->id, 'exam_id' => $waec->id, 'subject_id' => $physics->id, 'expires_at' => now()->addYear()]);

        $this->assertCount(3, $this->sessionIds($this->student));
    }

    public function test_one_students_purchase_does_not_open_it_for_another(): void
    {
        $this->buy($this->makeUser(4));

        $this->assertCount(3, $this->sessionIds($this->student));
    }

    public function test_a_free_priced_subject_is_open_to_everyone(): void
    {
        $this->exam->subjects()->updateExistingPivot($this->subject->id, ['price' => 0]);

        $this->assertCount(10, $this->sessionIds($this->student));
    }

    public function test_examiners_and_admins_see_everything(): void
    {
        $this->assertCount(10, $this->sessionIds($this->makeUser(3)));
        $this->assertCount(10, $this->sessionIds($this->makeUser(2)));
    }

    public function test_the_practice_page_offers_the_unlock_and_only_years_from_the_sample(): void
    {
        $response = $this->actingAs($this->student)->get('/practice?exam=jamb&subject=physics');

        $response->assertOk()->assertSee('free sample')->assertSee('Unlock Physics')->assertSee('₦1,500');
        $this->assertEquals([2021], $response->viewData('years')->all());

        $this->buy();
        $this->actingAs($this->student)->get('/practice?exam=jamb&subject=physics')->assertOk()->assertDontSee('Unlock Physics')->assertSee('Unlocked until');
    }

    public function test_answers_to_locked_questions_are_refused_so_the_key_cannot_be_probed(): void
    {
        $locked = $this->old[0];
        $free = $this->new[0];

        $result = (new ProgressRecorder($this->student))->attempts([
            ['client_uuid' => (string) Str::uuid(), 'question_id' => $locked->id, 'mode' => 'practice', 'selected' => 'B', 'answered_at' => now()->toIso8601String()],
            ['client_uuid' => (string) Str::uuid(), 'question_id' => $free->id, 'mode' => 'practice', 'selected' => 'B', 'answered_at' => now()->toIso8601String()],
        ]);

        $this->assertSame(['accepted' => 1, 'rejected' => 1], $result);
        $this->assertSame(0, DB::table('question_attempts')->where('question_id', $locked->id)->count());
    }

    public function test_the_web_attempts_endpoint_applies_the_same_rule(): void
    {
        $this->actingAs($this->student)->postJson('/practice/attempts', ['attempts' => [
            ['client_uuid' => (string) Str::uuid(), 'question_id' => $this->old[0]->id, 'mode' => 'practice', 'selected' => 'A', 'answered_at' => now()->toIso8601String()],
        ]])->assertOk()->assertJson(['accepted' => 0, 'rejected' => 1]);
    }

    public function test_the_mobile_sync_applies_the_same_rule(): void
    {
        $token = $this->student->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/sync/progress', ['attempts' => [
            ['client_uuid' => (string) Str::uuid(), 'question_id' => $this->old[0]->id, 'mode' => 'practice', 'selected' => 'A', 'answered_at' => now()->toIso8601String()],
        ]])->assertOk()->assertJsonPath('rejected.attempts', 1);
    }

    public function test_only_questions_the_student_can_open_can_be_saved(): void
    {
        $this->actingAs($this->student)->postJson('/saved/toggle', ['question_id' => $this->old[0]->id, 'bookmarked' => true])->assertOk()->assertJson(['accepted' => 0, 'rejected' => 1]);
        $this->actingAs($this->student)->postJson('/saved/toggle', ['question_id' => $this->new[0]->id, 'bookmarked' => true])->assertOk()->assertJson(['accepted' => 1]);
    }

    public function test_a_saved_question_that_becomes_locked_is_hidden_until_renewed(): void
    {
        $this->buy();
        $this->actingAs($this->student)->postJson('/saved/toggle', ['question_id' => $this->old[0]->id, 'bookmarked' => true])->assertJson(['accepted' => 1]);
        $this->actingAs($this->student)->get('/saved')->assertOk()->assertSee('Old 1');

        Entitlement::query()->update(['expires_at' => now()->subDay()]);

        $this->actingAs($this->student)->get('/saved')->assertOk()->assertDontSee('Old 1')->assertSee('hidden because');
        // Removing a saved question is always allowed, even while it is locked.
        $this->actingAs($this->student)->postJson('/saved/toggle', ['question_id' => $this->old[0]->id, 'bookmarked' => false])->assertJson(['accepted' => 1]);
    }

    public function test_a_mock_cannot_start_on_a_locked_subject_but_can_once_unlocked(): void
    {
        $this->actingAs($this->student)->post('/mock', ['exam' => 'jamb', 'subjects' => ['physics']])->assertSessionHasErrors('subjects');
        $this->assertSame(0, DB::table('mock_runs')->count());

        $this->buy();
        $this->config(['testacbt.mock.jamb' => ['label' => 'JAMB', 'subject_count' => 1, 'questions' => ['default' => 5], 'minutes' => 10, 'score_max' => 100]]);
        $this->actingAs($this->student)->post('/mock', ['exam' => 'jamb', 'subjects' => ['physics']])->assertRedirect();
        $this->assertSame(1, DB::table('mock_runs')->count());
    }

    public function test_the_mock_page_shows_locked_subjects_with_a_way_to_unlock(): void
    {
        $this->config(['testacbt.mock.jamb' => ['label' => 'JAMB', 'subject_count' => 1, 'questions' => ['default' => 5], 'minutes' => 10, 'score_max' => 100]]);

        $this->actingAs($this->student)->get('/mock?exam=jamb')->assertOk()->assertSee('Locked')->assertSee('Unlock your subjects');
    }

    public function test_marking_a_mock_still_records_answers_if_the_purchase_ran_out_mid_exam(): void
    {
        $this->buy();
        $this->config(['testacbt.mock.jamb' => ['label' => 'JAMB', 'subject_count' => 1, 'questions' => ['default' => 5], 'minutes' => 10, 'score_max' => 100]]);
        $run = app(\App\Services\MockService::class)->start($this->student, $this->exam, ['physics']);

        Entitlement::query()->update(['expires_at' => now()->subMinute()]);
        $answers = collect($run->question_ids)->mapWithKeys(fn ($id) => [$id => 'B'])->all();
        app(\App\Services\MockService::class)->submit($run, $answers);

        $this->assertSame(5, DB::table('question_attempts')->where('user_id', $this->student->id)->count());
    }

    public function test_access_reports_when_a_purchase_ends(): void
    {
        $this->buy(expires: '+10 days');

        $access = Access::for($this->student);
        $this->assertTrue($access->full($this->exam->id, $this->subject->id));
        $this->assertEqualsWithDelta(10, now()->diffInDays($access->expiresAt($this->exam->id, $this->subject->id)), 1);
        $this->assertFalse(Access::for($this->makeUser(4))->full($this->exam->id, $this->subject->id));
    }

    private function config(array $values): void
    {
        config($values);
    }
}
