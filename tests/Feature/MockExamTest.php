<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\MockRun;
use App\Models\Topic;
use App\Services\MockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

class MockExamTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    private Exam $jamb;
    private array $subjects = [];

    /** A JAMB with English and three other subjects, each with $perSubject questions (answer B). */
    private function seedJamb(int $perSubject = 12): void
    {
        foreach (['English Language' => 'Use of English', 'Mathematics' => null, 'Physics' => null, 'Chemistry' => null, 'Biology' => null] as $name => $display) {
            [$exam, $subject] = $this->makeExamAndSubject('JAMB', $name, $display);
            $this->jamb = $exam;
            $this->subjects[$name] = $subject;
            $paper = $this->makePaper($exam, $subject, 2022);
            foreach (range(1, $perSubject) as $i) {
                $this->makeQuestion($paper, "<p>$name question $i</p>", 'B');
            }
        }
    }

    private function start(array $slugs = ['english-language', 'mathematics', 'physics', 'chemistry'], ?int $seed = 7): MockRun
    {
        return app(MockService::class)->start($this->user ??= $this->makeUser(), $this->jamb, $slugs, $seed);
    }

    private ?\App\Models\User $user = null;

    // ---- starting ----

    public function test_a_jamb_mock_has_english_first_then_the_chosen_subjects_in_order(): void
    {
        $this->seedJamb(50);   // plenty: full 60/40/40/40 minus what exists

        $run = $this->start(['physics', 'english-language', 'mathematics', 'chemistry']);

        $this->assertSame(['Use of English', 'Chemistry', 'Mathematics', 'Physics'], array_column($run->groups, 'name'));
        $this->assertSame([50, 40, 40, 40], array_column($run->groups, 'count'), 'English has only 50 here; the rest use the real 40');
        $this->assertCount(170, $run->question_ids);
        $this->assertCount(170, array_unique($run->question_ids));
    }

    public function test_the_clock_is_the_real_two_hours_when_there_are_enough_questions_and_shortened_when_not(): void
    {
        $this->seedJamb(80);
        $full = $this->start();
        $this->assertSame(120, $full->minutes);
        $this->assertSame(120, (int) $full->started_at->diffInMinutes($full->deadline_at));

        // Fewer questions than the real exam: the clock shrinks in proportion (here 40 of 180 questions).
        MockRun::query()->delete();
        DB::table('questions')->delete();
        $this->seedJambSmall();
        $short = $this->start();
        $this->assertSame((int) ceil(120 * 4 * 10 / 180), $short->minutes);
        $this->assertCount(40, $short->question_ids);
    }

    private function seedJambSmall(): void
    {
        foreach ($this->subjects as $subject) {
            $paper = \App\Models\Paper::where('subject_id', $subject->id)->first();
            foreach (range(1, 10) as $i) { $this->makeQuestion($paper, "<p>small $i</p>", 'B'); }
        }
    }

    public function test_the_format_rules_are_enforced(): void
    {
        $this->seedJamb();
        $svc = app(MockService::class);
        $user = $this->makeUser();

        $bad = [
            'too few' => ['english-language', 'mathematics'],
            'too many' => ['english-language', 'mathematics', 'physics', 'chemistry', 'biology'],
            'no english' => ['mathematics', 'physics', 'chemistry', 'biology'],
            'unknown subject' => ['english-language', 'mathematics', 'physics', 'basket-weaving'],
        ];

        foreach ($bad as $why => $slugs) {
            try {
                $svc->start($user, $this->jamb, $slugs);
                $this->fail("started with $why");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('subjects', $e->errors(), $why);
            }
        }
    }

    public function test_a_subject_without_enough_questions_is_not_offered(): void
    {
        $this->seedJamb();
        [, $art] = $this->makeExamAndSubject('JAMB', 'Government');
        $this->makeQuestion($this->makePaper($this->jamb, $art), '<p>only one</p>', 'B');

        $slugs = app(MockService::class)->subjectOptions($this->jamb)->pluck('slug')->all();

        $this->assertContains('mathematics', $slugs);
        $this->assertNotContains('government', $slugs);
    }

    public function test_waec_is_a_single_subject_mock_out_of_100(): void
    {
        [$waec, $physics] = $this->makeExamAndSubject('WAEC', 'Physics');
        $paper = $this->makePaper($waec, $physics);
        foreach (range(1, 20) as $i) { $this->makeQuestion($paper, "<p>W$i</p>", 'B'); }
        $user = $this->makeUser();

        $run = app(MockService::class)->start($user, $waec, ['physics']);
        $this->assertCount(20, $run->question_ids);
        $this->assertSame(24, $run->minutes, '60 minutes for 50 questions, scaled to 20');

        $answers = array_fill_keys(array_slice($run->question_ids, 0, 10), 'B');
        $done = app(MockService::class)->submit($run, $answers);

        $this->assertSame(50, $done->score);
        $this->assertSame(100, $done->total);
    }

    // ---- marking ----

    public function test_marking_scales_each_subject_to_100_and_adds_them_up(): void
    {
        $this->seedJamb(10);
        $run = $this->start();
        $svc = app(MockService::class);

        // 10 English (all right), 5 of 10 maths right, one physics wrong, chemistry untouched.
        $ids = $run->question_ids;
        $answers = [];
        foreach (array_slice($ids, 0, 10) as $id) { $answers[$id] = 'B'; }
        foreach (array_slice($ids, 10, 5) as $id) { $answers[$id] = 'B'; }
        $answers[$ids[20]] = 'A';

        $done = $svc->submit($run, $answers);

        $groups = collect($done->result['groups'])->keyBy('name');
        $this->assertSame(100, $groups['Use of English']['percent']);
        $this->assertSame(50, $groups['Chemistry']['percent'], '5 of 10 right');
        $this->assertSame(0, $groups['Mathematics']['percent'], 'the one answer given was wrong');
        $this->assertSame(0, $groups['Physics']['percent'], 'nothing answered');
        $this->assertSame(150, $done->score);
        $this->assertSame(400, $done->total);
        $this->assertSame(15, $done->result['correct']);
        $this->assertSame(16, $done->result['answered']);
        $this->assertSame(40, $done->result['questions']);
    }

    public function test_marking_uses_the_answer_key_and_ignores_answers_for_other_questions(): void
    {
        $this->seedJamb(10);
        $run = $this->start();
        $stranger = \App\Models\Question::whereNotIn('id', $run->question_ids)->first();
        [$first] = $run->question_ids;

        $done = app(MockService::class)->submit($run, [$first => 'b', $stranger?->id ?? 999999 => 'B', $run->question_ids[1] => 'Z']);

        $this->assertSame(['B'], array_values($done->answers), 'lowercase accepted, unknown letters and foreign questions dropped');
        $this->assertSame(1, $done->result['correct']);
    }

    public function test_submitting_records_attempts_a_mock_session_and_is_safe_to_repeat(): void
    {
        $this->seedJamb(10);
        $run = $this->start();
        $answers = [$run->question_ids[0] => 'B', $run->question_ids[1] => 'A'];
        $svc = app(MockService::class);

        $svc->submit($run, $answers);
        $again = $svc->submit($run->fresh(), [$run->question_ids[2] => 'B']);   // a late second submit changes nothing

        $this->assertSame(2, DB::table('question_attempts')->where('user_id', $run->user_id)->where('mode', 'mock')->count());
        $this->assertSame([1, 0], DB::table('question_attempts')->orderBy('id')->pluck('is_correct')->map(fn ($v) => (int) $v)->all());
        $this->assertSame(1, DB::table('mock_sessions')->where('user_id', $run->user_id)->count());
        $this->assertCount(2, $again->answers);
    }

    public function test_topics_are_tallied_for_the_weak_topics_list(): void
    {
        $this->seedJamb(6);
        $run = $this->start();
        $topic = Topic::create(['subject_id' => $this->subjects['Physics']->id, 'name' => 'Heat', 'slug' => 'heat']);
        \App\Models\Question::whereIn('id', $run->question_ids)->whereHas('paper', fn ($p) => $p->where('subject_id', $this->subjects['Physics']->id))->update(['topic_id' => $topic->id]);

        $done = app(MockService::class)->submit($run, []);

        $t = collect($done->result['topics'])->firstWhere('name', 'Heat');
        $this->assertSame([0, 6], [$t['correct'], $t['total']]);
    }

    public function test_change_since_the_previous_mock(): void
    {
        $this->seedJamb(10);
        $svc = app(MockService::class);

        $first = $this->start();
        $svc->submit($first, array_fill_keys(array_slice($first->question_ids, 0, 10), 'B'));   // English perfect: 100
        $this->assertNull($svc->changeSincePrevious($first->fresh()), 'first mock has nothing to compare with');

        $second = $this->start();
        $svc->submit($second, array_fill_keys(array_slice($second->question_ids, 0, 20), 'B'));   // English + maths: 200
        $this->assertSame(100, $svc->changeSincePrevious($second->fresh()));
    }

    // ---- saving and resuming ----

    public function test_autosave_keeps_answers_flags_and_position_and_stops_after_submission(): void
    {
        $this->seedJamb(10);
        $run = $this->start();
        $svc = app(MockService::class);
        [$a, $b] = $run->question_ids;

        $svc->save($run, [$a => 'C', 999999 => 'A'], [$b, 999999], 5);
        $run = $run->fresh();
        $this->assertSame([$a => 'C'], $run->answers);
        $this->assertSame([$b], $run->flagged);
        $this->assertSame(5, $run->position);

        $svc->submit($run);
        $svc->save($run->fresh(), [$a => 'A'], [], 0);
        $this->assertSame('C', $run->fresh()->answers[$a], 'no changes after submitting');
    }

    // ---- web ----

    public function test_setup_page_shows_the_format_and_the_compulsory_subject(): void
    {
        $this->seedJamb();
        $this->actingAs($this->makeUser())->get('/mock?exam=jamb')
            ->assertOk()->assertSee('JAMB UTME')->assertSee('180')->assertSee('Compulsory')->assertSee('Use of English');
    }

    public function test_starting_redirects_to_the_exam_and_a_second_start_resumes_the_first(): void
    {
        $this->seedJamb();
        $u = $this->makeUser();
        $body = ['exam' => 'jamb', 'subjects' => ['english-language', 'mathematics', 'physics', 'chemistry']];

        $r = $this->actingAs($u)->post('/mock', $body);
        $run = MockRun::firstOrFail();
        $r->assertRedirect(route('mock.show', $run));

        $this->actingAs($u)->post('/mock', $body)->assertRedirect(route('mock.show', $run))->assertSessionHas('error');
        $this->assertSame(1, MockRun::count());
    }

    public function test_a_bad_choice_is_reported_on_the_form(): void
    {
        $this->seedJamb();

        $this->actingAs($this->makeUser())->post('/mock', ['exam' => 'jamb', 'subjects' => ['mathematics']])->assertSessionHasErrors('subjects');
        $this->assertSame(0, MockRun::count());
    }

    public function test_the_exam_page_never_contains_the_answer_key(): void
    {
        $this->seedJamb(10);
        $u = $this->makeUser();
        $run = app(MockService::class)->start($u, $this->jamb, ['english-language', 'mathematics', 'physics', 'chemistry'], 3);
        $this->paperQuestion($run)->update(['explanation_en' => 'SECRET-EXPLANATION']);

        $r = $this->actingAs($u)->get("/mock/{$run->uuid}")->assertOk();
        preg_match('#id="session-data">(.*?)</script>#s', $r->getContent(), $m);
        $data = json_decode($m[1], true);

        $this->assertCount(40, $data['questions']);
        $this->assertArrayNotHasKey('answer', $data['questions'][0]);
        $this->assertArrayNotHasKey('explanation_en', $data['questions'][0]);
        $r->assertDontSee('SECRET-EXPLANATION');
        $this->assertSame($run->question_ids, array_column($data['questions'], 'id'), 'same paper, same order');
    }

    private function paperQuestion(MockRun $run): \App\Models\Question
    {
        return \App\Models\Question::find($run->question_ids[0]);
    }

    public function test_only_the_owner_can_open_save_submit_or_review_a_mock(): void
    {
        $this->seedJamb(10);
        $run = $this->start();
        $intruder = $this->makeUser();

        $this->actingAs($intruder)->get("/mock/{$run->uuid}")->assertNotFound();
        $this->actingAs($intruder)->postJson("/mock/{$run->uuid}/save", ['answers' => [], 'flagged' => [], 'position' => 0])->assertNotFound();
        $this->actingAs($intruder)->postJson("/mock/{$run->uuid}/submit", ['answers' => []])->assertNotFound();
        $this->actingAs($intruder)->get("/mock/{$run->uuid}/result")->assertNotFound();
        $this->assertFalse($run->fresh()->isSubmitted());
    }

    public function test_save_and_submit_endpoints_work_and_the_result_page_shows_the_score(): void
    {
        $this->seedJamb(10);
        $run = $this->start();
        $u = $run->user;
        $answers = array_fill_keys(array_slice($run->question_ids, 0, 10), 'B');

        $this->actingAs($u)->postJson("/mock/{$run->uuid}/save", ['answers' => $answers, 'flagged' => [], 'position' => 3])
            ->assertOk()->assertJson(['saved' => true, 'submitted' => false]);
        $this->assertCount(10, $run->fresh()->answers);

        $this->actingAs($u)->postJson("/mock/{$run->uuid}/submit", ['answers' => $answers])
            ->assertOk()->assertJson(['submitted' => true, 'result' => route('mock.result', $run)]);

        $this->actingAs($u)->get("/mock/{$run->uuid}/result")->assertOk()->assertSee('Your result')->assertSee('out of 400')->assertSee('Use of English');
        $this->actingAs($u)->get("/mock/{$run->uuid}")->assertRedirect(route('mock.result', $run));
    }

    public function test_review_is_only_available_after_submitting_and_shows_the_key(): void
    {
        $this->seedJamb(10);
        $run = $this->start();
        $u = $run->user;
        $this->paperQuestion($run)->update(['explanation_en' => 'Because B is right.']);

        $this->actingAs($u)->get("/mock/{$run->uuid}/review")->assertNotFound();

        app(MockService::class)->submit($run, [$run->question_ids[0] => 'A']);

        $this->actingAs($u)->get("/mock/{$run->uuid}/review")->assertOk()->assertSee('Review answers')->assertSee('Wrong')->assertSee('Because B is right.');
    }

    public function test_an_exam_left_open_past_its_deadline_is_marked_with_what_was_saved(): void
    {
        $this->seedJamb(10);
        $run = $this->start();
        $u = $run->user;
        app(MockService::class)->save($run, [$run->question_ids[0] => 'B'], [], 0);
        $run->update(['started_at' => now()->subHours(3), 'deadline_at' => now()->subHour()]);

        $this->actingAs($u)->get("/mock/{$run->uuid}")->assertRedirect(route('mock.result', $run));

        $done = $run->fresh();
        $this->assertTrue($done->isSubmitted());
        $this->assertSame(1, $done->result['correct']);
        $this->assertSame($done->minutes * 60, $done->result['duration_seconds'], 'time used is capped at the time allowed');
    }

    public function test_the_mock_index_marks_an_expired_exam_instead_of_offering_to_resume_it(): void
    {
        $this->seedJamb(10);
        $run = $this->start();
        $run->update(['deadline_at' => now()->subMinute()]);

        $this->actingAs($run->user)->get('/mock')->assertRedirect(route('mock.result', $run));
        $this->assertTrue($run->fresh()->isSubmitted());
    }

    public function test_guests_cannot_reach_mock_pages(): void
    {
        foreach (['/mock', '/mock/anything', '/mock/anything/result'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }
}
