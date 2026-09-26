<?php

namespace Tests\Feature;

use App\Models\Paper;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

class StudentAreaTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    private $exam;
    private $subject;
    private $paper;
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->exam, $this->subject] = $this->makeExamAndSubject('JAMB', 'English Language', 'Use of English');
        $this->paper = $this->makePaper($this->exam, $this->subject, 2020);
        foreach (range(1, 6) as $i) {
            $this->makeQuestion($this->paper, "<p>Question number $i</p>", 'B');
        }
        $this->student = $this->makeUser();
    }

    private function attemptPayload(int $questionId, string $selected = 'B'): array
    {
        return ['attempts' => [[
            'client_uuid' => (string) Str::uuid(), 'question_id' => $questionId, 'mode' => 'practice',
            'selected' => $selected, 'answered_at' => now()->toIso8601String(), 'time_ms' => 4000,
        ]]];
    }

    // ---- setup page ----

    public function test_setup_lists_subjects_with_their_exam_specific_name_and_counts(): void
    {
        $this->actingAs($this->student)->get('/practice?exam=jamb')
            ->assertOk()->assertSee('Use of English')->assertSee('6 questions');
    }

    public function test_setup_shows_years_for_the_chosen_subject_only(): void
    {
        $this->makePaper($this->exam, $this->subject, 2018, Paper::DRAFT);

        $this->actingAs($this->student)->get('/practice?exam=jamb&subject=english-language')
            ->assertOk()->assertSee('2020')->assertDontSee('2018');
    }

    public function test_setup_says_so_when_an_exam_has_no_questions_yet(): void
    {
        $this->makeExamAndSubject('NECO', 'Physics');

        $this->actingAs($this->student)->get('/practice?exam=neco')->assertOk()->assertSee('No NECO questions yet');
    }

    public function test_setup_defaults_to_the_students_preferred_exam(): void
    {
        $this->student->update(['preferred_exams' => ['jamb']]);
        $this->makeExamAndSubject('WAEC', 'Physics');

        $this->actingAs($this->student)->get('/practice')->assertOk()->assertSee('Use of English');
    }

    // ---- session ----

    public function test_session_embeds_the_questions_without_leaking_markup_into_the_page(): void
    {
        $this->makeQuestion($this->paper, '<p>Bad</p><script>alert(1)</script>', 'B');

        $r = $this->actingAs($this->student)->get('/practice/session?exam=jamb&subject=english-language&year=2020&mode=instant&count=20')->assertOk();

        $r->assertSee('id="session-data"', false)->assertSee('Question number 1', false);
        $r->assertDontSee('<script>alert(1)</script>', false);
        // Cannot break out of the embedded JSON with a closing script tag.
        $this->assertSame(1, substr_count($r->getContent(), '<script type="application/json"'));
    }

    public function test_session_keeps_paper_order_for_a_single_year_and_reports_the_exam_label(): void
    {
        $r = $this->actingAs($this->student)->get('/practice/session?exam=jamb&subject=english-language&year=2020&mode=end&count=5')->assertOk();

        preg_match('#<script type="application/json" id="session-data">(.*?)</script>#s', $r->getContent(), $m);
        $data = json_decode($m[1], true);

        $this->assertSame('Use of English', $data['title']);
        $this->assertSame('JAMB 2020', $data['subtitle']);
        $this->assertSame('end', $data['mode']);
        $this->assertCount(5, $data['questions']);
        $this->assertSame(['Question number 1', 'Question number 2'], [strip_tags($data['questions'][0]['html']), strip_tags($data['questions'][1]['html'])]);
        $this->assertSame('B', $data['questions'][0]['answer']);
        $this->assertFalse($data['questions'][0]['bookmarked']);
    }

    public function test_session_marks_questions_the_student_already_saved(): void
    {
        $first = $this->paper->questions()->orderBy('id')->first();
        DB::table('bookmarks')->insert(['user_id' => $this->student->id, 'question_id' => $first->id, 'is_bookmarked' => true, 'changed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $r = $this->actingAs($this->student)->get('/practice/session?exam=jamb&subject=english-language&year=2020&mode=instant&count=5');
        preg_match('#id="session-data">(.*?)</script>#s', $r->getContent(), $m);

        $this->assertTrue(json_decode($m[1], true)['questions'][0]['bookmarked']);
    }

    public function test_session_with_no_matching_questions_goes_back_with_a_message(): void
    {
        $this->actingAs($this->student)->get('/practice/session?exam=jamb&subject=english-language&year=1999&mode=instant&count=10')
            ->assertRedirect()->assertSessionHas('error');
    }

    public function test_session_rejects_bad_input(): void
    {
        $base = '/practice/session?exam=jamb&subject=english-language&mode=instant&count=10';

        $this->actingAs($this->student)->get('/practice/session?exam=nope&subject=english-language&mode=instant&count=10')->assertSessionHasErrors('exam');
        $this->actingAs($this->student)->get('/practice/session?exam=jamb&subject=english-language&mode=cheat&count=10')->assertSessionHasErrors('mode');
        $this->actingAs($this->student)->get('/practice/session?exam=jamb&subject=english-language&mode=instant&count=9999')->assertSessionHasErrors('count');
        $this->actingAs($this->student)->get($base . '&year=abc')->assertSessionHasErrors('year');
    }

    public function test_a_topic_can_be_practised_across_exams_and_a_foreign_topic_is_refused(): void
    {
        $topic = Topic::create(['subject_id' => $this->subject->id, 'name' => 'Concord', 'slug' => 'concord']);
        $this->paper->questions()->first()->update(['topic_id' => $topic->id]);
        [, $physics] = $this->makeExamAndSubject('WAEC', 'Physics');
        $otherTopic = Topic::create(['subject_id' => $physics->id, 'name' => 'Heat', 'slug' => 'heat']);

        $this->actingAs($this->student)->get("/practice/session?subject=english-language&topic={$topic->id}&mode=instant&count=10")
            ->assertOk()->assertSee('Topic: Concord');

        // A topic that belongs to a different subject must not be reachable through this one.
        $this->actingAs($this->student)->get("/practice/session?subject=english-language&topic={$otherTopic->id}&mode=instant&count=10")->assertNotFound();
    }

    public function test_saved_session_uses_only_this_students_bookmarks(): void
    {
        $q = $this->paper->questions()->orderBy('id')->first();
        DB::table('bookmarks')->insert(['user_id' => $this->student->id, 'question_id' => $q->id, 'is_bookmarked' => true, 'changed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $r = $this->actingAs($this->student)->get('/practice/session?saved=1&mode=instant&count=10')->assertOk();
        preg_match('#id="session-data">(.*?)</script>#s', $r->getContent(), $m);
        $this->assertSame([$q->id], array_column(json_decode($m[1], true)['questions'], 'id'));

        // Someone with nothing saved is sent back, not shown anyone else's questions.
        $this->actingAs($this->makeUser())->get('/practice/session?saved=1&mode=instant&count=10')->assertRedirect()->assertSessionHas('error');
    }

    // ---- recording answers ----

    public function test_answers_are_recorded_by_the_server_using_the_answer_key(): void
    {
        $q = $this->paper->questions()->orderBy('id')->first();   // answer is B

        $this->actingAs($this->student)->postJson('/practice/attempts', $this->attemptPayload($q->id, 'B'))->assertOk()->assertJson(['accepted' => 1, 'rejected' => 0]);
        $this->actingAs($this->student)->postJson('/practice/attempts', $this->attemptPayload($q->id, 'C'))->assertOk();

        $rows = DB::table('question_attempts')->where('user_id', $this->student->id)->orderBy('id')->get();
        $this->assertSame([1, 0], $rows->pluck('is_correct')->map(fn ($v) => (int) $v)->all());
    }

    public function test_resending_the_same_answers_does_not_double_count(): void
    {
        $q = $this->paper->questions()->first();
        $payload = $this->attemptPayload($q->id);

        $this->actingAs($this->student)->postJson('/practice/attempts', $payload)->assertJson(['accepted' => 1]);
        $this->actingAs($this->student)->postJson('/practice/attempts', $payload)->assertJson(['accepted' => 0]);

        $this->assertSame(1, DB::table('question_attempts')->count());
    }

    public function test_attempts_require_sign_in_and_valid_data(): void
    {
        $this->postJson('/practice/attempts', ['attempts' => []])->assertStatus(401);
        $this->actingAs($this->student)->postJson('/practice/attempts', ['attempts' => [['question_id' => 1]]])->assertStatus(422);
    }

    // ---- saved ----

    public function test_toggle_saves_and_removes_a_question_and_the_saved_page_reflects_it(): void
    {
        $q = $this->paper->questions()->orderBy('id')->first();

        $this->actingAs($this->student)->postJson('/saved/toggle', ['question_id' => $q->id, 'bookmarked' => true])->assertOk()->assertJson(['bookmarked' => true]);
        $this->actingAs($this->student)->get('/saved')->assertOk()->assertSee('Question number 1')->assertSee('Use of English');

        $this->actingAs($this->student)->post('/saved/toggle', ['question_id' => $q->id, 'bookmarked' => 0])->assertRedirect();
        $this->actingAs($this->student)->get('/saved')->assertSee('Nothing saved yet');
    }

    public function test_saved_page_never_shows_another_students_questions(): void
    {
        $q = $this->paper->questions()->first();
        $other = $this->makeUser();
        $this->actingAs($other)->postJson('/saved/toggle', ['question_id' => $q->id, 'bookmarked' => true])->assertOk();

        $this->actingAs($this->student)->get('/saved')->assertOk()->assertSee('Nothing saved yet');
    }

    public function test_saving_an_unknown_question_is_ignored_safely(): void
    {
        $this->actingAs($this->student)->postJson('/saved/toggle', ['question_id' => 987654, 'bookmarked' => true])->assertOk()->assertJson(['accepted' => 0, 'rejected' => 1]);
    }

    // ---- progress ----

    public function test_progress_page_shows_totals_and_weak_topics(): void
    {
        $topic = Topic::create(['subject_id' => $this->subject->id, 'name' => 'Concord', 'slug' => 'concord']);
        $qs = $this->paper->questions()->get();
        $qs->each->update(['topic_id' => $topic->id]);
        foreach ($qs as $q) {
            DB::table('question_attempts')->insert(['user_id' => $this->student->id, 'client_uuid' => (string) Str::uuid(), 'question_id' => $q->id, 'mode' => 'practice',
                'selected' => 'A', 'is_correct' => false, 'answered_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->actingAs($this->student)->get('/progress')->assertOk()
            ->assertSee('Your progress')->assertSee('English Language')->assertSee('Topics to work on')->assertSee('Concord')->assertSee('0%');
    }

    public function test_progress_page_is_friendly_before_any_practice(): void
    {
        $this->actingAs($this->student)->get('/progress')->assertOk()->assertSee('No progress yet');
    }

    // ---- profile ----

    public function test_profile_settings_are_saved(): void
    {
        $r = $this->actingAs($this->student)->put('/profile', [
            'name' => 'Ada Obi', 'preferred_exams' => ['jamb'], 'target_exam' => 'jamb',
            'target_exam_date' => now()->addDays(40)->toDateString(), 'explanation_language' => 'pcm',
        ]);

        $r->assertRedirect()->assertSessionHas('success');
        $u = $this->student->fresh();
        $this->assertSame('Ada Obi', $u->name);
        $this->assertSame(['jamb'], $u->preferred_exams);
        $this->assertSame('pcm', $u->explanation_language);
        $this->assertSame(40, (int) now()->startOfDay()->diffInDays($u->target_exam_date));
    }

    public function test_profile_validation(): void
    {
        $this->actingAs($this->student)->put('/profile', [
            'name' => '', 'explanation_language' => 'fr', 'target_exam' => 'nope', 'target_exam_date' => now()->subDay()->toDateString(),
        ])->assertSessionHasErrors(['name', 'explanation_language', 'target_exam', 'target_exam_date']);
    }

    public function test_profile_cannot_change_role_email_or_activation(): void
    {
        $this->actingAs($this->student)->put('/profile', [
            'name' => 'X', 'explanation_language' => 'en', 'role' => 'admin', 'user_type' => 1, 'email' => 'x@evil.test', 'is_active' => 0,
        ]);

        $u = $this->student->fresh();
        $this->assertSame('student', $u->role);
        $this->assertNotSame('x@evil.test', $u->email);
        $this->assertSame(1, (int) $u->is_active);
    }

    public function test_password_change_needs_the_current_password_and_signs_out_the_app(): void
    {
        $this->student->createToken('phone');

        $this->actingAs($this->student)->put('/profile/password', ['current_password' => 'wrong-one', 'password' => 'brandnew123', 'password_confirmation' => 'brandnew123'])
            ->assertSessionHasErrors('current_password', null, 'password');
        $this->assertSame(1, $this->student->tokens()->count());

        $this->actingAs($this->student)->put('/profile/password', ['current_password' => 'password123', 'password' => 'brandnew123', 'password_confirmation' => 'brandnew123'])
            ->assertSessionHas('success');
        $this->assertTrue(\Hash::check('brandnew123', $this->student->fresh()->password));
        $this->assertSame(0, $this->student->tokens()->count(), 'the phone app is signed out');
    }

    public function test_a_google_only_account_can_set_a_first_password(): void
    {
        $g = $this->makeUser(4, ['password' => null, 'email' => 'g@example.com']);

        $this->actingAs($g)->put('/profile/password', ['password' => 'firstpass123', 'password_confirmation' => 'firstpass123'])->assertSessionHas('success');

        $this->assertTrue(\Hash::check('firstpass123', $g->fresh()->password));
    }

    // ---- who can see what ----

    public function test_guests_are_sent_to_sign_in(): void
    {
        foreach (['/practice', '/practice/session', '/saved', '/progress', '/profile'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }
}
