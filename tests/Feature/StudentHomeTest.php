<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StudentStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

class StudentHomeTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    private function attempt(User $u, $question, ?string $selected, $when = null): void
    {
        DB::table('question_attempts')->insert([
            'user_id' => $u->id, 'client_uuid' => (string) Str::uuid(), 'question_id' => $question->id, 'mode' => 'practice',
            'selected' => $selected, 'is_correct' => $selected === $question->answer,
            'answered_at' => $when ?? now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_guests_see_the_welcome_page_and_signed_in_users_are_sent_home(): void
    {
        $this->get('/')->assertOk()->assertSee('Pass your exam')->assertSee('Get started');
        $this->get('/login')->assertOk()->assertSee('Welcome back');
        $this->get('/register')->assertOk()->assertSee('Create your account');

        $this->actingAs($this->makeUser())->get('/')->assertRedirect('/dashboard');
        $this->actingAs($this->makeUser())->get('/login')->assertRedirect('/dashboard');
    }

    public function test_logging_out_returns_to_the_welcome_page(): void
    {
        $this->actingAs($this->makeUser())->post('/logout')->assertRedirect(route('welcome'));
        $this->assertGuest();
    }

    public function test_a_new_student_sees_a_friendly_empty_home(): void
    {
        $student = $this->makeUser(4, ['name' => 'Ada Obi']);

        $this->actingAs($student)->get('/dashboard')
            ->assertOk()->assertSee('Ada')->assertSee('Nothing here yet')->assertSee('Start your first practice');
    }

    public function test_home_shows_subject_accuracy_and_the_exam_countdown(): void
    {
        [$exam, $physics] = $this->makeExamAndSubject('JAMB', 'Physics');
        $paper = $this->makePaper($exam, $physics);
        $q = $this->makeQuestion($paper, '<p>Q</p>', 'B');
        $student = $this->makeUser();
        $student->forceFill(['target_exam' => 'jamb', 'target_exam_date' => now()->addDays(30)->toDateString()])->save();

        $this->attempt($student, $q, 'B');
        $this->attempt($student, $q, 'A');

        $this->actingAs($student)->get('/dashboard')
            ->assertOk()->assertSee('Physics')->assertSee('50% of 2')
            ->assertSeeInOrder(['JAMB', '30', 'days left'], false);
    }

    public function test_staff_still_reach_the_legacy_dashboard(): void
    {
        $this->actingAs($this->makeUser(2))->get('/dashboard')->assertOk()->assertDontSee('Nothing here yet');
    }

    // ---- StudentStats ----

    public function test_streak_counts_consecutive_days_and_survives_until_the_end_of_today(): void
    {
        [$exam, $s] = $this->makeExamAndSubject();
        $q = $this->makeQuestion($this->makePaper($exam, $s));
        $u = $this->makeUser();

        $this->assertSame(0, (new StudentStats($u))->streak());

        // Studied yesterday and the day before, but not yet today: the streak is still alive.
        $this->attempt($u, $q, 'B', now()->subDay());
        $this->attempt($u, $q, 'B', now()->subDays(2));
        $this->assertSame(2, (new StudentStats($u))->streak());

        // Studying today extends it. Several answers on one day count once.
        $this->attempt($u, $q, 'B', now());
        $this->attempt($u, $q, 'A', now());
        $this->assertSame(3, (new StudentStats($u))->streak());

        // A gap breaks it.
        $this->attempt($u, $q, 'B', now()->subDays(4));
        $this->assertSame(3, (new StudentStats($u))->streak());
    }

    public function test_a_streak_ends_after_a_missed_day(): void
    {
        [$exam, $s] = $this->makeExamAndSubject();
        $q = $this->makeQuestion($this->makePaper($exam, $s));
        $u = $this->makeUser();

        $this->attempt($u, $q, 'B', now()->subDays(2));
        $this->attempt($u, $q, 'B', now()->subDays(3));

        $this->assertSame(0, (new StudentStats($u))->streak());
    }

    public function test_subjects_are_ranked_weakest_first_and_only_count_this_students_answers(): void
    {
        [$exam, $physics] = $this->makeExamAndSubject('WAEC', 'Physics');
        [, $maths] = $this->makeExamAndSubject('WAEC', 'Mathematics');
        $qp = $this->makeQuestion($this->makePaper($exam, $physics), '<p>P</p>', 'B');
        $qm = $this->makeQuestion($this->makePaper($exam, $maths), '<p>M</p>', 'B');
        $me = $this->makeUser();
        $other = $this->makeUser();

        $this->attempt($me, $qp, 'B');
        $this->attempt($me, $qm, 'A');
        $this->attempt($me, $qm, 'A');
        $this->attempt($other, $qm, 'B');   // someone else's answers must not leak in

        $subjects = (new StudentStats($me))->subjects();

        $this->assertSame(['Mathematics', 'Physics'], $subjects->pluck('name')->all());
        $this->assertSame([0, 100], $subjects->pluck('accuracy')->all());
        $this->assertSame(2, $subjects->first()->answered);
    }

    public function test_last_seven_days_fills_in_quiet_days(): void
    {
        [$exam, $s] = $this->makeExamAndSubject();
        $q = $this->makeQuestion($this->makePaper($exam, $s));
        $u = $this->makeUser();
        $this->attempt($u, $q, 'B', now());
        $this->attempt($u, $q, 'B', now());
        $this->attempt($u, $q, 'B', now()->subDays(2));

        $days = (new StudentStats($u))->lastDays(7);

        $this->assertCount(7, $days);
        $this->assertTrue($days[6]['today']);
        $this->assertSame([0, 0, 0, 0, 1, 0, 2], array_column($days, 'count'));
    }

    public function test_days_to_exam_is_null_for_past_or_missing_dates(): void
    {
        $u = $this->makeUser();
        $this->assertNull((new StudentStats($u))->daysToExam());

        $u->forceFill(['target_exam_date' => now()->subDay()->toDateString()])->save();
        $this->assertNull((new StudentStats($u->fresh()))->daysToExam());

        $u->forceFill(['target_exam_date' => now()->addDays(10)->toDateString()])->save();
        $this->assertSame(10, (new StudentStats($u->fresh()))->daysToExam());
    }
}
