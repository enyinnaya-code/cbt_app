<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Leaderboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

/** The daily challenge is optional; joining shows a nickname and picture, never the real name. */
class RankingTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    private function player(string $nickname, ?string $school = null, array $extra = []): User
    {
        $user = $this->makeUser(4, $extra + ['name' => "Real Name {$nickname}", 'email' => strtolower(str_replace(' ', '', $nickname)) . '@private.example']);
        Leaderboard::join($user, ['nickname' => $nickname, 'avatar' => 'owl', 'school' => $school]);

        return $user->fresh();
    }

    private function play(User $user, int $correct, ?string $date = null, int $bonus = 100): void
    {
        DB::table('daily_challenges')->insert([
            'user_id' => $user->id, 'challenge_date' => $date ?? now()->toDateString(), 'question_ids' => '[]', 'started_at' => now(),
            'completed_at' => now(), 'correct' => $correct, 'total' => 10, 'xp_awarded' => $bonus, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function form(array $over = []): array
    {
        return $over + ['nickname' => 'SharpOwl22', 'avatar' => 'owl', 'school' => '', 'accept_privacy' => '1'];
    }

    // ------------------------------------------------------------------ the opt-in

    public function test_a_student_who_has_not_joined_cannot_open_the_challenge_and_is_invited_instead(): void
    {
        $student = $this->makeUser(4);

        $this->actingAs($student)->get(route('challenge.show'))->assertRedirect(route('rankings.join'));
        $this->actingAs($student)->postJson(route('challenge.complete'))->assertForbidden()->assertJson(['opt_in_required' => true]);
        $this->actingAs($student)->get(route('dashboard'))->assertOk()->assertSee('Join the daily challenge');
    }

    public function test_joining_needs_the_privacy_policy_accepted(): void
    {
        $student = $this->makeUser(4);

        $this->actingAs($student)->post(route('rankings.join.store'), $this->form(['accept_privacy' => null]))->assertSessionHasErrors('accept_privacy');

        $this->assertFalse(Leaderboard::isParticipant($student->fresh()));
        $this->assertNull($student->fresh()->nickname);
    }

    public function test_joining_saves_the_choices_and_the_consent_and_opens_the_challenge(): void
    {
        $student = $this->makeUser(4);

        $this->actingAs($student)->post(route('rankings.join.store'), $this->form(['school' => '  Corona   Secondary School ']))->assertRedirect(route('challenge.show'));

        $u = $student->fresh();
        $this->assertTrue(Leaderboard::isParticipant($u));
        $this->assertSame('SharpOwl22', $u->nickname);
        $this->assertSame('owl', $u->avatar);
        $this->assertSame('Corona Secondary School', $u->school);
        $this->assertSame(config('testacbt.privacy_version'), $u->privacy_version);
        $this->assertNotNull($u->privacy_accepted_at);
    }

    public function test_school_is_optional(): void
    {
        $student = $this->makeUser(4);

        $this->actingAs($student)->post(route('rankings.join.store'), $this->form())->assertSessionHasNoErrors();

        $this->assertNull($student->fresh()->school);
    }

    public function test_nicknames_are_checked(): void
    {
        $student = $this->makeUser(4);
        $accepted = [];
        $this->player('TakenName');

        foreach ([
            'ab' => 'too short',
            str_repeat('x', 21) => 'too long',
            'bad<script>' => 'symbols',
            'me@email.com' => 'an email',
            'TAKENNAME' => 'taken, whatever the capitals',
            'Admin Support' => 'impersonation',
            'Official_TestaCBT' => 'brand',
            'f.u.c.k' => 'rude, with dots between the letters',
        ] as $nickname => $why) {
            $errors = $this->actingAs($student)->post(route('rankings.join.store'), $this->form(['nickname' => (string) $nickname]))->getSession()->get('errors');
            if (! $errors || ! $errors->has('nickname')) { $accepted[] = "{$nickname} ({$why})"; }
        }

        $this->assertSame([], $accepted);
        $this->assertFalse(Leaderboard::isParticipant($student->fresh()), 'none of those was accepted');
    }

    public function test_a_picture_must_be_one_of_ours_and_a_school_cannot_carry_markup(): void
    {
        $student = $this->makeUser(4);

        $this->actingAs($student)->post(route('rankings.join.store'), $this->form(['avatar' => 'https://evil.example/x.png']))->assertSessionHasErrors('avatar');
        $this->actingAs($student)->post(route('rankings.join.store'), $this->form(['school' => '<img src=x onerror=alert(1)>']))->assertSessionHasErrors('school');
    }

    public function test_leaving_deletes_what_they_chose_and_removes_them_from_every_board(): void
    {
        $student = $this->player('Leaver', 'Some School');
        $this->play($student, 8);
        $this->assertCount(1, (new Leaderboard)->board('week')['rows']);

        $this->actingAs($student)->post(route('rankings.leave'))->assertRedirect(route('dashboard'));

        $u = $student->fresh();
        $this->assertFalse(Leaderboard::isParticipant($u));
        $this->assertNull($u->nickname);
        $this->assertNull($u->avatar);
        $this->assertNull($u->school);
        $this->assertNull($u->privacy_accepted_at);
        $this->assertCount(0, (new Leaderboard)->board('week')['rows']);
        $this->assertCount(0, (new Leaderboard)->board('all')['rows']);
    }

    public function test_a_new_policy_version_asks_everyone_again(): void
    {
        $student = $this->player('Oldtimer');
        $this->play($student, 9);
        $this->assertCount(1, (new Leaderboard)->board('week')['rows']);

        config(['testacbt.privacy_version' => '2099-01']);

        $this->assertFalse(Leaderboard::isParticipant($student->fresh()));
        $this->assertCount(0, (new Leaderboard)->board('week')['rows'], 'hidden until they accept the new policy');
        $this->actingAs($student)->get(route('challenge.show'))->assertRedirect(route('rankings.join'));
    }

    // ------------------------------------------------------------------ the boards

    public function test_the_boards_never_show_real_names_or_emails(): void
    {
        $a = $this->player('SharpOwl', 'Corona School');
        $this->play($a, 9);
        $me = $this->player('Viewer');

        $page = $this->actingAs($me)->get(route('rankings'))->assertOk()->assertSee('SharpOwl')->assertSee('Corona School');
        $page->assertDontSee('Real Name SharpOwl')->assertDontSee('sharpowl@private.example');

        $json = $this->actingAs($me)->getJson('/api/v1/leaderboard')->getContent();
        $this->assertStringNotContainsString('Real Name', $json);
        $this->assertStringNotContainsString('private.example', $json);
    }

    public function test_ranking_is_by_points_with_ties_sharing_a_place(): void
    {
        $this->play($this->player('First'), 10);                // 100 + 100 = 200
        $this->play($b = $this->player('Second'), 7);           // 70 + 100 = 170
        $this->play($c = $this->player('Third'), 7);            // 170: tied with Second
        $this->play($this->player('Fourth'), 3);                // 130

        $rows = (new Leaderboard)->board('week')['rows'];

        $this->assertSame(['First', 'Second', 'Third', 'Fourth'], array_column($rows, 'nickname'));
        $this->assertSame([1, 2, 2, 4], array_column($rows, 'rank'));
        $this->assertSame([200, 170, 170, 130], array_column($rows, 'points'));
    }

    public function test_this_week_leaves_out_older_challenges_but_all_time_keeps_them(): void
    {
        $old = $this->player('Veteran');
        $this->play($old, 10, now()->subWeeks(3)->toDateString());
        $new = $this->player('Newcomer');
        $this->play($new, 2);

        $week = array_column((new Leaderboard)->board('week')['rows'], 'nickname');
        $all = array_column((new Leaderboard)->board('all')['rows'], 'nickname');

        $this->assertSame(['Newcomer'], $week);
        $this->assertSame(['Veteran', 'Newcomer'], $all);
    }

    public function test_students_who_never_joined_are_not_on_the_board_even_with_challenges(): void
    {
        $quiet = $this->makeUser(4);
        $this->play($quiet, 10);

        $this->assertSame([], (new Leaderboard)->board('week')['rows']);
    }

    public function test_the_student_always_sees_their_own_place_even_outside_the_top_list(): void
    {
        config(['testacbt.leaderboard_size' => 2]);
        $this->play($this->player('A1'), 10);
        $this->play($this->player('B2'), 9);
        $me = $this->player('Low');
        $this->play($me, 1);

        $board = (new Leaderboard)->board('week', $me);

        $this->assertCount(2, $board['rows']);
        $this->assertSame(3, $board['me']['rank']);
        $this->assertSame(110, $board['me']['points']);
    }

    public function test_a_school_appears_only_with_enough_players_and_ranks_by_average(): void
    {
        foreach (['S1', 'S2', 'S3'] as $n) { $this->play($this->player($n, 'Big School'), 5); }    // 150 each
        foreach (['T1', 'T2'] as $n) { $this->play($this->player($n, 'Tiny School'), 10); }         // 200 each, but only two players
        foreach (['U1', 'U2', 'U3'] as $n) { $this->play($this->player($n, 'big  school'), 9); }    // same school, spelled differently: 190 each

        $rows = (new Leaderboard)->board('schools')['rows'];

        $this->assertCount(1, $rows, 'Tiny School has fewer than 3 players');
        $this->assertSame(6, $rows[0]['members']);
        $this->assertSame(170, $rows[0]['points']);   // (3*150 + 3*190) / 6
        $this->assertStringContainsString('chool', $rows[0]['school']);
    }

    // ------------------------------------------------------------------ the app

    public function test_the_app_can_read_join_and_leave(): void
    {
        $student = $this->makeUser(4);
        $auth = ['Authorization' => 'Bearer ' . $student->createToken('t')->plainTextToken];

        $this->getJson('/api/v1/leaderboard/profile', $auth)->assertOk()->assertJsonPath('opted_in', false)->assertJsonCount(12, 'avatars');

        $this->putJson('/api/v1/leaderboard/profile', ['nickname' => 'PhoneUser', 'avatar' => 'rocket', 'school' => 'Phone High', 'accept_privacy' => false], $auth)->assertStatus(422);
        $this->putJson('/api/v1/leaderboard/profile', ['nickname' => 'PhoneUser', 'avatar' => 'rocket', 'school' => 'Phone High', 'accept_privacy' => true], $auth)
            ->assertOk()->assertJsonPath('opted_in', true)->assertJsonPath('nickname', 'PhoneUser');

        $this->getJson('/api/v1/leaderboard?scope=all', $auth)->assertOk()->assertJsonStructure(['scope', 'period', 'rows', 'me']);
        $this->getJson('/api/v1/leaderboard?scope=bogus', $auth)->assertStatus(422);

        $this->deleteJson('/api/v1/leaderboard/profile', [], $auth)->assertOk()->assertJsonPath('opted_in', false)->assertJsonPath('nickname', null);
    }

    public function test_the_app_cannot_claim_a_challenge_without_joining(): void
    {
        $student = $this->makeUser(4);
        [$exam, $subject] = $this->makeExamAndSubject();
        $paper = $this->makePaper($exam, $subject);
        $ids = [];
        for ($i = 0; $i < 6; $i++) { $ids[] = $this->makeQuestion($paper)->id; }
        $auth = ['Authorization' => 'Bearer ' . $student->createToken('t')->plainTextToken];

        $attempts = array_map(fn ($id) => ['client_uuid' => (string) \Illuminate\Support\Str::uuid(), 'question_id' => $id, 'mode' => 'practice', 'selected' => 'B', 'answered_at' => now()->toIso8601String()], $ids);
        $this->postJson('/api/v1/sync/progress', ['attempts' => $attempts, 'challenges' => [['date' => now()->toDateString(), 'question_ids' => $ids]]], $auth)
            ->assertOk()->assertJsonCount(0, 'challenges');

        $this->assertSame(0, DB::table('daily_challenges')->count());
    }

    public function test_the_privacy_policy_is_public_and_says_what_is_shown(): void
    {
        $this->get('/privacy')->assertOk()->assertSee('Privacy policy')->assertSee('nickname')->assertSee('never show your real name');
    }

    public function test_the_join_page_links_the_policy_and_lists_the_pictures(): void
    {
        $this->actingAs($this->makeUser(4))->get(route('rankings.join'))->assertOk()
            ->assertSee(route('privacy'), false)->assertSee('accept_privacy', false)->assertSee('name="avatar"', false);
    }

    public function test_rankings_page_loads_for_a_student_who_has_not_joined(): void
    {
        $this->actingAs($this->makeUser(4))->get(route('rankings'))->assertOk()->assertSee('Join the daily challenge');
    }
}
