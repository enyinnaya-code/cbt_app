<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_register_and_is_logged_in(): void
    {
        Exam::create(['name' => 'WAEC', 'slug' => 'waec']);

        $response = $this->post('/register', [
            'name' => 'Chidinma Okafor',
            'email' => 'chidinma@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'preferred_exams' => ['waec'],
        ]);

        $response->assertRedirect(route('dashboard'));
        $user = User::where('email', 'chidinma@example.com')->firstOrFail();
        $this->assertSame('student', $user->role);
        $this->assertSame(['waec'], $user->preferred_exams);
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_cannot_grant_a_privileged_role(): void
    {
        $this->post('/register', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
            'user_type' => 1,
        ]);

        $this->assertSame('student', User::where('email', 'sneaky@example.com')->firstOrFail()->role);
    }

    public function test_registration_rejects_duplicate_email_and_short_password(): void
    {
        User::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'password123', 'user_type' => 4]);

        $this->post('/register', [
            'name' => 'B', 'email' => 'a@example.com', 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors(['email', 'password']);
    }

    public function test_login_works_with_correct_credentials(): void
    {
        $user = User::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'password123', 'user_type' => 4]);

        $this->post('/login', ['email' => 'a@example.com', 'password' => 'password123'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_failed_logins_are_throttled_but_never_suspend_the_account(): void
    {
        RateLimiter::clear('a@example.com|127.0.0.1');
        $user = User::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'password123', 'user_type' => 4]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'a@example.com', 'password' => 'wrong'])
                ->assertSessionHas('error', 'Invalid email or password.');
        }

        // Sixth attempt is blocked, even with the right password.
        $this->post('/login', ['email' => 'a@example.com', 'password' => 'password123'])
            ->assertSessionHas('error');
        $this->assertGuest();

        // The account itself is untouched, so a stranger cannot lock the owner out permanently.
        $this->assertSame(1, (int) $user->fresh()->is_active);
    }

    public function test_admin_suspended_account_cannot_log_in(): void
    {
        User::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'password123', 'user_type' => 4, 'is_active' => 0]);

        $this->post('/login', ['email' => 'a@example.com', 'password' => 'password123'])
            ->assertSessionHas('error', 'Your account is suspended.');
        $this->assertGuest();
    }
}
