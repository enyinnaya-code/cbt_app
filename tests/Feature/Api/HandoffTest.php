<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

/** The app opens the website already signed in, so Google sign-in students can pay. */
class HandoffTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->student = $this->makeUser(4, ['is_active' => 1]);
        $this->makeExamAndSubject('JAMB', 'Physics');
    }

    /** A request made the way the app makes it: with a token, and no website session. */
    private function asApp(User $user): static
    {
        $this->app['auth']->forgetGuards();   // otherwise the guard remembers the previous request's user

        return $this->withToken($user->createToken('test')->plainTextToken);
    }

    private function link(array $body = []): string
    {
        return $this->asApp($this->student)->postJson('/api/v1/web-link', $body)->assertOk()->json('url');
    }

    public function test_the_link_signs_the_student_in_and_lands_on_the_unlock_page(): void
    {
        $url = $this->link(['exam' => 'jamb', 'subjects' => ['physics']]);

        $this->assertGuest('web');
        $this->get($url)->assertRedirect('/checkout?exam=jamb&subjects%5B0%5D=physics');
        $this->assertAuthenticatedAs($this->student, 'web');
    }

    public function test_a_link_works_only_once(): void
    {
        $url = $this->link();

        $this->get($url)->assertRedirect();
        auth('web')->logout();
        $this->get($url)->assertForbidden();
        $this->assertGuest('web');
    }

    public function test_a_link_expires_after_five_minutes(): void
    {
        $url = $this->link();

        $this->travel(6)->minutes();

        $this->get($url)->assertForbidden();
        $this->assertGuest('web');
    }

    public function test_a_tampered_link_is_refused(): void
    {
        $url = $this->link();
        $other = $this->makeUser(4, ['is_active' => 1]);

        $this->get(str_replace('/app-link/' . $this->student->id, '/app-link/' . $other->id, $url))->assertForbidden();
        $this->get($url . '&to=/dashboard')->assertForbidden();
        $this->assertGuest('web');
    }

    public function test_a_link_cannot_lead_anywhere_but_the_shopping_pages(): void
    {
        Cache::put('handoff:abc', $this->student->id, 300);
        $url = URL::temporarySignedRoute('app.handoff', now()->addMinutes(5), ['user' => $this->student->id, 'nonce' => 'abc', 'to' => '/console/users']);

        $this->get($url)->assertForbidden();
        $this->assertGuest('web');
    }

    public function test_the_app_can_only_ask_for_shopping_pages(): void
    {
        $this->asApp($this->student)->postJson('/api/v1/web-link', ['page' => '/console/users'])->assertStatus(422);
        $this->asApp($this->student)->postJson('/api/v1/web-link', ['page' => '/orders'])->assertOk();
    }

    public function test_staff_cannot_use_a_link_and_a_suspended_student_cannot_either(): void
    {
        $admin = $this->makeUser(2, ['is_active' => 1]);
        $this->asApp($admin)->postJson('/api/v1/web-link')->assertForbidden();

        $url = $this->link();
        $this->student->update(['is_active' => 0]);
        $this->get($url)->assertForbidden();
        $this->assertGuest('web');
    }

    public function test_a_link_needs_a_signed_in_app(): void
    {
        $this->postJson('/api/v1/web-link')->assertUnauthorized();
    }
}
