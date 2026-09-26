<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

/** The users screen keeps admins, examiners and students in separate, numbered tables. */
class UsersTablesTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeUser(2, ['name' => 'Ada Admin']);
    }

    /** @return string the HTML of one table section (admin, examiner or student), or '' when it is not on the page */
    private function section(string $html, string $role): string
    {
        return preg_match('#<section[^>]*aria-labelledby="users-' . $role . '".*?</section>#s', $html, $m) ? $m[0] : '';
    }

    private function students(int $n, string $prefix = 'Student'): void
    {
        foreach (range(1, $n) as $i) {
            $this->makeUser(4, ['name' => sprintf('%s %03d', $prefix, $i), 'email' => strtolower($prefix) . "$i@example.com"]);
        }
    }

    public function test_admins_examiners_and_students_each_have_their_own_table(): void
    {
        $this->makeUser(3, ['name' => 'Emeka Examiner']);
        $this->makeUser(4, ['name' => 'Sola Student']);
        $html = $this->actingAs($this->admin)->get('/console/users')->assertOk()->getContent();

        $admins = $this->section($html, 'admin');
        $examiners = $this->section($html, 'examiner');
        $students = $this->section($html, 'student');

        $this->assertStringContainsString('Ada Admin', $admins);
        $this->assertStringNotContainsString('Emeka Examiner', $admins . $students);
        $this->assertStringNotContainsString('Sola Student', $admins . $examiners);
        $this->assertStringContainsString('Emeka Examiner', $examiners);
        $this->assertStringContainsString('Sola Student', $students);
        $this->assertLessThan(strpos($html, 'users-examiner'), strpos($html, 'users-admin'));
        $this->assertLessThan(strpos($html, 'users-student'), strpos($html, 'users-examiner'));
    }

    public function test_every_table_numbers_its_rows_from_one(): void
    {
        $this->makeUser(3, ['name' => 'Emeka Examiner']);
        $this->makeUser(3, ['name' => 'Ngozi Examiner']);
        $this->students(3);
        $html = $this->actingAs($this->admin)->get('/console/users')->getContent();

        $this->assertStringContainsString('<th style="width:56px">S/N</th>', $html);
        foreach (['admin' => 1, 'examiner' => 2, 'student' => 3] as $role => $rows) {
            preg_match_all('#<td class="muted">(\d+)</td>#', $this->section($html, $role), $m);
            $this->assertSame(range(1, $rows), array_map('intval', $m[1]), $role);
        }
    }

    public function test_numbering_carries_on_across_pages_and_each_table_pages_on_its_own(): void
    {
        $this->students(30);   // 25 to a page

        $first = $this->actingAs($this->admin)->get('/console/users')->getContent();
        $this->assertStringContainsString('Student 001', $first);
        $this->assertStringNotContainsString('Student 030', $first);

        $second = $this->actingAs($this->admin)->get('/console/users?students_page=2')->getContent();
        $students = $this->section($second, 'student');
        preg_match_all('#<td class="muted">(\d+)</td>#', $students, $m);
        $this->assertSame(range(26, 30), array_map('intval', $m[1]), 'the second page carries on from 26');
        $this->assertStringContainsString('Student 030', $students);
        $this->assertStringContainsString('Ada Admin', $this->section($second, 'admin'), 'the admins table did not move');
    }

    public function test_the_show_filter_keeps_one_table(): void
    {
        $this->makeUser(3, ['name' => 'Emeka Examiner']);
        $this->students(2);

        $html = $this->actingAs($this->admin)->get('/console/users?role=examiner')->getContent();

        $this->assertSame('', $this->section($html, 'admin'));
        $this->assertSame('', $this->section($html, 'student'));
        $this->assertStringContainsString('Emeka Examiner', $this->section($html, 'examiner'));
    }

    public function test_searching_hides_tables_with_no_match(): void
    {
        $this->makeUser(3, ['name' => 'Emeka Examiner']);
        $this->students(2);

        $html = $this->actingAs($this->admin)->get('/console/users?q=Student 002')->getContent();

        $this->assertSame('', $this->section($html, 'admin'));
        $this->assertSame('', $this->section($html, 'examiner'));
        $this->assertStringContainsString('Student 002', $this->section($html, 'student'));
        $this->assertStringNotContainsString('Student 001', $html);
    }

    public function test_status_filter_and_a_search_with_no_matches(): void
    {
        $this->students(2);
        User::where('name', 'Student 002')->update(['is_active' => 0]);

        $suspended = $this->actingAs($this->admin)->get('/console/users?state=suspended')->getContent();
        $this->assertStringContainsString('Student 002', $suspended);
        $this->assertStringNotContainsString('Student 001', $suspended);

        $this->actingAs($this->admin)->get('/console/users?q=nobody-with-this-name')->assertOk()->assertSee('No users match');
    }

    public function test_empty_tables_say_so_when_nothing_is_filtered(): void
    {
        $html = $this->actingAs($this->admin)->get('/console/users')->getContent();

        $this->assertStringContainsString('No examiners', $this->section($html, 'examiner'));
        $this->assertStringContainsString('No students', $this->section($html, 'student'));
    }

    public function test_the_page_states_how_many_are_in_each_table(): void
    {
        $this->students(4);
        $html = $this->actingAs($this->admin)->get('/console/users')->getContent();

        $this->assertMatchesRegularExpression('#Students <span class="badge neutral">4</span>#', $html);
        $this->assertMatchesRegularExpression('#Admins <span class="badge neutral">1</span>#', $html);
    }

    public function test_only_admins_see_the_users_screen(): void
    {
        $this->actingAs($this->makeUser(3))->get('/console/users')->assertForbidden();
        $this->actingAs($this->makeUser(4))->get('/console/users')->assertForbidden();
    }
}
