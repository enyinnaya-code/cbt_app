<?php

namespace Tests\Feature;

use App\Http\Controllers\Console\UserController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

/** Admins can reset anyone's password to the default, and the person must then choose their own. */
class AdminPasswordResetTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    private function reset(User $admin, User $target, ?string $password = null)
    {
        return $this->actingAs($admin)->post(route('console.users.password', $target), $password === null ? [] : ['new_password' => $password]);
    }

    /** A student an admin has just reset: the flag is never mass-assignable, so it is set the way the app sets it. */
    private function flagged(array $extra = []): User
    {
        $user = $this->makeUser(4, $extra);
        $user->forceFill(['must_change_password' => true])->save();

        return $user->fresh();
    }

    public function test_the_default_password_is_123456(): void
    {
        $this->assertSame('123456', UserController::DEFAULT_PASSWORD);
    }

    public function test_an_admin_resets_a_student_an_examiner_and_another_admin_to_the_default(): void
    {
        $admin = $this->makeUser(1);

        foreach ([4, 3, 2] as $type) {
            $target = $this->makeUser($type, ['password' => 'OldPassword1']);
            $this->reset($admin, $target)->assertSessionHas('success');

            $fresh = $target->fresh();
            $this->assertTrue(Hash::check('123456', $fresh->password), "role type $type");
            $this->assertTrue($fresh->must_change_password);
        }
    }

    public function test_an_admin_may_type_a_different_password_which_must_still_be_a_real_one(): void
    {
        $admin = $this->makeUser(1);
        $student = $this->makeUser(4, ['password' => 'OldPassword1']);

        $this->reset($admin, $student, 'short')->assertSessionHasErrors('new_password', null, 'reset');
        $this->assertTrue(Hash::check('OldPassword1', $student->fresh()->password), 'a weak one changes nothing');

        $this->reset($admin, $student, 'Fresh4Password')->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Fresh4Password', $student->fresh()->password));
        $this->assertTrue($student->fresh()->must_change_password, 'still has to choose their own');
    }

    public function test_the_person_is_signed_out_everywhere(): void
    {
        $admin = $this->makeUser(1);
        $student = $this->makeUser(4);
        $student->createToken('phone');
        $student->createToken('tablet');

        $this->reset($admin, $student);

        $this->assertSame(0, $student->tokens()->count());
    }

    public function test_after_a_reset_the_default_only_opens_the_change_page(): void
    {
        $student = $this->flagged(['password' => '123456']);

        foreach ([route('dashboard'), route('practice.index'), route('progress'), route('revision')] as $url) {
            $this->actingAs($student)->get($url)->assertRedirect(route('profile'));
        }

        $this->actingAs($student)->get(route('profile'))->assertOk()->assertSee('An admin reset your password');
    }

    public function test_choosing_a_new_password_frees_the_account_and_does_not_ask_for_the_old_one(): void
    {
        $student = $this->flagged(['password' => '123456']);

        $this->actingAs($student)->put(route('profile.password'), ['password' => 'MyOwn4Password', 'password_confirmation' => 'MyOwn4Password'])->assertSessionHasNoErrors();

        $fresh = $student->fresh();
        $this->assertTrue(Hash::check('MyOwn4Password', $fresh->password));
        $this->assertFalse($fresh->must_change_password);
        $this->actingAs($fresh)->get(route('dashboard'))->assertOk();
    }

    public function test_the_shared_default_cannot_be_chosen_as_the_new_password(): void
    {
        $student = $this->flagged(['password' => '123456']);

        $this->actingAs($student)->put(route('profile.password'), ['password' => '123456', 'password_confirmation' => '123456'])->assertSessionHasErrors('password', null, 'password');

        $this->assertTrue($student->fresh()->must_change_password);
    }

    public function test_the_app_cannot_be_used_until_the_password_is_changed(): void
    {
        $student = $this->flagged(['email' => 'phone@example.com', 'password' => '123456']);

        $this->postJson('/api/v1/auth/login', ['email' => 'phone@example.com', 'password' => '123456', 'device_name' => 'p'])
            ->assertStatus(403)->assertJson(['must_change_password' => true])->assertJsonMissing(['token']);

        // A token that already existed stops working too.
        $token = $student->createToken('old')->plainTextToken;
        $this->getJson('/api/v1/catalog', ['Authorization' => "Bearer $token"])->assertStatus(403)->assertJson(['must_change_password' => true]);
    }

    public function test_examiners_and_students_cannot_reset_passwords(): void
    {
        $victim = $this->makeUser(1, ['password' => 'OldPassword1']);

        $this->reset($this->makeUser(3), $victim)->assertForbidden();
        $this->reset($this->makeUser(4), $victim)->assertForbidden();

        $this->assertTrue(Hash::check('OldPassword1', $victim->fresh()->password));
    }

    public function test_a_signed_out_visitor_cannot_reset_passwords(): void
    {
        $victim = $this->makeUser(1, ['password' => 'OldPassword1']);

        $this->post(route('console.users.password', $victim))->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('OldPassword1', $victim->fresh()->password));
    }

    public function test_the_users_page_has_a_reset_password_modal_for_every_account(): void
    {
        $admin = $this->makeUser(1);
        $this->makeUser(4);
        $this->makeUser(3);

        foreach (['admin', 'student', 'examiner'] as $tab) {
            $this->actingAs($admin)->get(route('console.users.index', ['tab' => $tab]))->assertOk()
                ->assertSee('data-reset-open', false)->assertSee('id="reset-dialog"', false)->assertSee('value="123456"', false);
        }
    }

    public function test_suspending_and_reactivating_both_ask_for_confirmation_first(): void
    {
        $admin = $this->makeUser(1);
        $this->makeUser(4, ['name' => 'Active Student']);
        $this->makeUser(4, ['name' => 'Suspended Student', 'is_active' => 0]);

        $page = $this->actingAs($admin)->get(route('console.users.index', ['tab' => 'student']))->assertOk();

        $page->assertSee('data-confirm-title="Suspend Active Student?"', false)->assertSee('data-confirm-label="Suspend"', false)
            ->assertSee('data-confirm-title="Reactivate Suspended Student?"', false)->assertSee('data-confirm-label="Reactivate"', false);

        preg_match_all('/<button[^>]*>(?:Suspend|Reactivate)<\/button>/', $page->getContent(), $buttons);
        $this->assertNotEmpty($buttons[0]);
        foreach ($buttons[0] as $button) {
            $this->assertStringContainsString('data-confirm=', $button);
        }
    }
}
