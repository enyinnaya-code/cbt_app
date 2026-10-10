<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

/** "Forgot my password": a link to the registered email, and nothing else can reset it. */
class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    private function link(User $user): array
    {
        $token = Password::createToken($user);

        return [$token, route('password.reset', ['token' => $token, 'email' => $user->email])];
    }

    public function test_the_sign_in_page_links_to_forgot_my_password(): void
    {
        $this->get('/login')->assertOk()->assertSee('Forgot my password')->assertSee(route('password.request'), false);
        $this->get(route('password.request'))->assertOk()->assertSee('Send me the link');
    }

    public function test_a_link_is_emailed_to_the_registered_address(): void
    {
        Notification::fake();
        $student = $this->makeUser(4, ['email' => 'real@example.com']);

        $this->post(route('password.email'), ['email' => 'real@example.com'])->assertSessionHas('status');

        Notification::assertSentTo($student, ResetPasswordNotification::class);
    }

    public function test_the_email_has_a_link_to_our_own_reset_page(): void
    {
        $student = $this->makeUser(4, ['email' => 'real@example.com', 'name' => 'Ada Obi']);

        $mail = (new ResetPasswordNotification('tok123'))->toMail($student);

        $this->assertSame('Reset your TestaCBT password', $mail->subject);
        $this->assertStringStartsWith(url('/password/reset/tok123'), $mail->actionUrl);
        $this->assertStringContainsString('email=real%40example.com', $mail->actionUrl);
        $this->assertStringContainsString('Ada', $mail->greeting);
    }

    public function test_the_answer_is_the_same_whether_or_not_the_email_has_an_account(): void
    {
        Notification::fake();
        $this->makeUser(4, ['email' => 'real@example.com']);

        $known = $this->post(route('password.email'), ['email' => 'real@example.com'])->getSession()->get('status');
        $unknown = $this->post(route('password.email'), ['email' => 'nobody@example.com'])->getSession()->get('status');

        $this->assertNotNull($known);
        $this->assertSame($known, $unknown);
        Notification::assertCount(1);
    }

    public function test_a_suspended_account_is_not_sent_a_link(): void
    {
        Notification::fake();
        $this->makeUser(4, ['email' => 'gone@example.com', 'is_active' => 0]);

        $this->post(route('password.email'), ['email' => 'gone@example.com'])->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_a_failing_mail_server_does_not_give_anything_away(): void
    {
        $this->makeUser(4, ['email' => 'real@example.com']);
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('SMTP down'));

        $this->post(route('password.email'), ['email' => 'real@example.com'])->assertSessionHas('status')->assertSessionHasNoErrors();
    }

    public function test_requests_are_limited(): void
    {
        Notification::fake();
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.email'), ['email' => "a{$i}@example.com"])->assertSessionHas('status');
        }

        $this->post(route('password.email'), ['email' => 'a9@example.com'])->assertStatus(429);
    }

    public function test_the_link_opens_the_reset_page_and_sets_a_new_password(): void
    {
        $student = $this->makeUser(4, ['email' => 'real@example.com', 'password' => 'OldPassword1']);
        [$token, $url] = $this->link($student);

        $this->get($url)->assertOk()->assertSee('Choose a new password')->assertSee('value="real@example.com"', false);

        $this->post(route('password.update'), ['token' => $token, 'email' => 'real@example.com', 'password' => 'NewSecret42', 'password_confirmation' => 'NewSecret42'])
            ->assertRedirect(route('login'))->assertSessionHas('success');

        $this->assertTrue(Hash::check('NewSecret42', $student->fresh()->password));
        $this->post('/login', ['email' => 'real@example.com', 'password' => 'NewSecret42'])->assertRedirect(route('dashboard'));
    }

    public function test_a_link_works_only_once(): void
    {
        $student = $this->makeUser(4, ['email' => 'real@example.com']);
        [$token] = $this->link($student);
        $body = ['token' => $token, 'email' => 'real@example.com', 'password' => 'NewSecret42', 'password_confirmation' => 'NewSecret42'];

        $this->post(route('password.update'), $body)->assertRedirect(route('login'));
        $this->post(route('password.update'), ['password' => 'Another4Secret', 'password_confirmation' => 'Another4Secret'] + $body)->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('NewSecret42', $student->fresh()->password));
    }

    public function test_a_wrong_token_or_someone_elses_email_changes_nothing(): void
    {
        $student = $this->makeUser(4, ['email' => 'real@example.com', 'password' => 'OldPassword1']);
        $other = $this->makeUser(4, ['email' => 'other@example.com', 'password' => 'OtherPassword1']);
        [$token] = $this->link($student);

        $this->post(route('password.update'), ['token' => 'not-a-token', 'email' => 'real@example.com', 'password' => 'NewSecret42', 'password_confirmation' => 'NewSecret42'])->assertSessionHasErrors('email');
        // A real token for one account cannot reset another account.
        $this->post(route('password.update'), ['token' => $token, 'email' => 'other@example.com', 'password' => 'NewSecret42', 'password_confirmation' => 'NewSecret42'])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('OldPassword1', $student->fresh()->password));
        $this->assertTrue(Hash::check('OtherPassword1', $other->fresh()->password));
    }

    public function test_an_expired_link_is_refused(): void
    {
        $student = $this->makeUser(4, ['email' => 'real@example.com', 'password' => 'OldPassword1']);
        [$token] = $this->link($student);

        $this->travel(61)->minutes();

        $this->post(route('password.update'), ['token' => $token, 'email' => 'real@example.com', 'password' => 'NewSecret42', 'password_confirmation' => 'NewSecret42'])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('OldPassword1', $student->fresh()->password));
    }

    public function test_the_new_password_must_be_a_real_one_and_match(): void
    {
        $student = $this->makeUser(4, ['email' => 'real@example.com', 'password' => 'OldPassword1']);
        [$token] = $this->link($student);

        foreach ([['short1', 'short1'], ['onlyletterspassword', 'onlyletterspassword'], ['NewSecret42', 'Different42']] as [$p, $c]) {
            $this->post(route('password.update'), ['token' => $token, 'email' => 'real@example.com', 'password' => $p, 'password_confirmation' => $c])->assertSessionHasErrors('password');
        }

        $this->assertTrue(Hash::check('OldPassword1', $student->fresh()->password), 'and the link still works afterwards');
        $this->post(route('password.update'), ['token' => $token, 'email' => 'real@example.com', 'password' => 'NewSecret42', 'password_confirmation' => 'NewSecret42'])->assertRedirect(route('login'));
    }

    public function test_every_phone_is_signed_out_and_a_forced_change_is_cleared(): void
    {
        $student = $this->makeUser(4, ['email' => 'real@example.com', 'password' => '123456', 'must_change_password' => true]);
        $student->createToken('phone');
        [$token] = $this->link($student);

        $this->post(route('password.update'), ['token' => $token, 'email' => 'real@example.com', 'password' => 'NewSecret42', 'password_confirmation' => 'NewSecret42']);

        $this->assertSame(0, $student->tokens()->count());
        $this->assertFalse($student->fresh()->must_change_password);
    }

    public function test_an_account_suspended_after_the_link_was_sent_stays_shut(): void
    {
        $student = $this->makeUser(4, ['email' => 'real@example.com', 'password' => 'OldPassword1']);
        [$token] = $this->link($student);
        $student->update(['is_active' => 0]);

        $this->post(route('password.update'), ['token' => $token, 'email' => 'real@example.com', 'password' => 'NewSecret42', 'password_confirmation' => 'NewSecret42'])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('OldPassword1', $student->fresh()->password));
    }

    public function test_signed_in_people_are_sent_away_from_the_forgot_page(): void
    {
        $this->actingAs($this->makeUser(4))->get(route('password.request'))->assertRedirect();
    }
}
