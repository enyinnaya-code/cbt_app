<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function userOfType(int $type): User
    {
        return User::create([
            'name' => "User $type",
            'email' => 'user' . ++$this->seq . "-type{$type}@example.com",
            'password' => 'password123',
            'user_type' => $type,
        ]);
    }

    public function test_user_type_is_mapped_to_role_on_save(): void
    {
        $this->assertSame('admin', $this->userOfType(1)->role);
        $this->assertSame('admin', $this->userOfType(2)->role);
        $this->assertSame('examiner', $this->userOfType(3)->role);
        $this->assertSame('student', $this->userOfType(4)->role);

        $user = $this->userOfType(4);
        $user->update(['user_type' => 3]);
        $this->assertSame('examiner', $user->fresh()->role);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/manage-user')->assertRedirect(route('login'));
        $this->get('/questions')->assertRedirect(route('login'));
    }

    public function test_student_cannot_reach_admin_or_examiner_screens(): void
    {
        $student = $this->userOfType(4);

        foreach ([
            '/manage-user', '/add-user', '/manage-teacher', '/manage-sections',
            '/manage-school-classes', '/courses/manage', '/schedule-test', '/announcements/create',
            '/tests', '/tests/create', '/questions', '/students',
        ] as $url) {
            $this->actingAs($student)->get($url)->assertForbidden();
        }
    }

    public function test_student_cannot_change_data_through_admin_endpoints(): void
    {
        $student = $this->userOfType(4);
        $victim = $this->userOfType(3);

        $this->actingAs($student)->delete("/delete-user/{$victim->id}")->assertForbidden();
        $this->actingAs($student)->post('/add-user', ['name' => 'x', 'email' => 'x@example.com'])->assertForbidden();
        $this->assertNotNull($victim->fresh());
    }

    public function test_student_can_still_reach_student_screens(): void
    {
        $student = $this->userOfType(4);

        $this->actingAs($student)->get('/dashboard')->assertOk();
    }

    public function test_examiner_reaches_question_tools_but_not_admin_tools(): void
    {
        $examiner = $this->userOfType(3);

        $this->actingAs($examiner)->get('/manage-user')->assertForbidden();
        $this->actingAs($examiner)->get('/manage-sections')->assertForbidden();
        $this->actingAs($examiner)->get('/schedule-test')->assertForbidden();
        $this->actingAs($examiner)->get('/questions')->assertOk();
    }

    public function test_admin_reaches_admin_and_examiner_tools(): void
    {
        $admin = $this->userOfType(2);

        $this->actingAs($admin)->get('/manage-user')->assertOk();
        $this->actingAs($admin)->get('/questions')->assertOk();
    }
}
