<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    public function test_register_returns_a_token_and_a_student(): void
    {
        $r = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada', 'email' => 'Ada@Example.com', 'password' => 'password123', 'device_name' => 'Tecno Spark',
        ]);

        $r->assertCreated()->assertJsonPath('user.role', 'student')->assertJsonPath('user.email', 'ada@example.com');
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer ' . $r->json('token')])
            ->assertOk()->assertJsonPath('user.name', 'Ada');
    }

    public function test_register_cannot_choose_a_role(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'password123', 'device_name' => 'p',
            'role' => 'admin', 'user_type' => 1,
        ])->assertCreated()->assertJsonPath('user.role', 'student');
    }

    public function test_register_validates_input(): void
    {
        $this->makeUser(4, ['email' => 'taken@example.com']);

        $this->postJson('/api/v1/auth/register', ['name' => 'X', 'email' => 'taken@example.com', 'password' => 'short'])
            ->assertStatus(422)->assertJsonValidationErrors(['email', 'password', 'device_name']);
    }

    public function test_login_and_bad_password(): void
    {
        $this->makeUser(4, ['email' => 'a@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'password123', 'device_name' => 'p'])
            ->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'role']]);

        $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'nope', 'device_name' => 'p'])
            ->assertStatus(401);
    }

    public function test_login_is_throttled_and_never_suspends_the_account(): void
    {
        RateLimiter::clear('api-login|a@example.com|127.0.0.1');
        $user = $this->makeUser(4, ['email' => 'a@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'bad', 'device_name' => 'p'])->assertStatus(401);
        }
        $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'password123', 'device_name' => 'p'])->assertStatus(429);
        $this->assertSame(1, (int) $user->fresh()->is_active);
    }

    public function test_suspended_account_cannot_log_in_and_loses_existing_tokens(): void
    {
        $user = $this->makeUser(4, ['email' => 'a@example.com']);
        $token = $user->createToken('p')->plainTextToken;

        $user->update(['is_active' => 0]);

        $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'password123', 'device_name' => 'p'])->assertStatus(403);
        $this->getJson('/api/v1/me', ['Authorization' => "Bearer $token"])->assertStatus(403);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_protected_routes_return_json_401_without_a_token(): void
    {
        $this->getJson('/api/v1/catalog')->assertStatus(401)->assertJsonPath('message', 'Unauthenticated.');
        $this->get('/api/v1/catalog', ['Accept' => 'text/html'])->assertStatus(401);
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('p')->plainTextToken;

        $this->postJson('/api/v1/auth/logout', [], ['Authorization' => "Bearer $token"])->assertOk();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_signing_in_again_on_the_same_device_replaces_its_token(): void
    {
        $user = $this->makeUser(4, ['email' => 'a@example.com']);
        $body = ['email' => 'a@example.com', 'password' => 'password123', 'device_name' => 'same phone'];

        $this->postJson('/api/v1/auth/login', $body);
        $this->postJson('/api/v1/auth/login', $body);

        $this->assertSame(1, $user->tokens()->count());
    }

    // ---- Google ----

    private function fakeGoogle(array $overrides = [], int $status = 200): void
    {
        config(['services.google.client_ids' => ['my-android-client']]);

        Http::fake(['oauth2.googleapis.com/*' => Http::response($overrides + [
            'aud' => 'my-android-client', 'iss' => 'https://accounts.google.com', 'sub' => 'g-123',
            'email' => 'new@example.com', 'email_verified' => 'true', 'name' => 'New Person',
            'picture' => 'https://pic', 'exp' => (string) (time() + 3600),
        ], $status)]);
    }

    public function test_google_is_unavailable_until_client_ids_are_configured(): void
    {
        config(['services.google.client_ids' => []]);

        $this->postJson('/api/v1/auth/google', ['id_token' => 'x', 'device_name' => 'p'])->assertStatus(503);
    }

    public function test_google_creates_a_new_student(): void
    {
        $this->fakeGoogle();

        $this->postJson('/api/v1/auth/google', ['id_token' => 'x', 'device_name' => 'p'])
            ->assertOk()->assertJsonPath('user.role', 'student')->assertJsonPath('user.has_password', false);

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertSame('g-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_google_rejects_tokens_for_another_app_unverified_emails_and_expired_tokens(): void
    {
        $this->fakeGoogle(['aud' => 'someone-elses-app']);
        $this->postJson('/api/v1/auth/google', ['id_token' => 'x', 'device_name' => 'p'])->assertStatus(401);

        $this->fakeGoogle(['email_verified' => 'false']);
        $this->postJson('/api/v1/auth/google', ['id_token' => 'x', 'device_name' => 'p'])->assertStatus(401);

        $this->fakeGoogle(['exp' => (string) (time() - 10)]);
        $this->postJson('/api/v1/auth/google', ['id_token' => 'x', 'device_name' => 'p'])->assertStatus(401);

        $this->fakeGoogle([], 400);
        $this->postJson('/api/v1/auth/google', ['id_token' => 'x', 'device_name' => 'p'])->assertStatus(401);

        $this->assertSame(0, User::count());
    }

    public function test_google_refuses_to_attach_to_a_staff_account(): void
    {
        $this->makeUser(2, ['email' => 'new@example.com']);
        $this->fakeGoogle();

        $this->postJson('/api/v1/auth/google', ['id_token' => 'x', 'device_name' => 'p'])->assertStatus(403);
        $this->assertNull(User::where('email', 'new@example.com')->first()->google_id);
    }

    public function test_google_taking_over_an_unverified_email_removes_the_earlier_password_and_sessions(): void
    {
        // Someone registered the victim's address first, before the victim ever signed in with Google.
        $squatter = $this->makeUser(4, ['email' => 'new@example.com', 'password' => 'squatter-secret']);
        $oldToken = $squatter->createToken('attacker')->plainTextToken;
        $this->fakeGoogle();

        $this->postJson('/api/v1/auth/google', ['id_token' => 'x', 'device_name' => 'victim phone'])->assertOk();

        $fresh = $squatter->fresh();
        $this->assertNull($fresh->password);
        $this->assertSame('g-123', $fresh->google_id);
        $this->assertSame(1, $fresh->tokens()->count(), 'only the new Google session remains');
        $this->postJson('/api/v1/auth/login', ['email' => 'new@example.com', 'password' => 'squatter-secret', 'device_name' => 'a'])->assertStatus(401);
        $this->assertNotSame($oldToken, $fresh->tokens()->first()->name);
    }

    public function test_returning_google_user_is_found_by_google_id(): void
    {
        $this->fakeGoogle();
        $this->postJson('/api/v1/auth/google', ['id_token' => 'x', 'device_name' => 'p'])->assertOk();
        $this->postJson('/api/v1/auth/google', ['id_token' => 'x', 'device_name' => 'p'])->assertOk();

        $this->assertSame(1, User::count());
    }
}
