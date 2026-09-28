<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

/** The users screen has one tab each for admins, examiners and students, with numbered rows and smart paging. */
class UsersTablesTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeUser(2, ['name' => 'Ada Admin']);
    }

    /** @return string the HTML of the table on the page (the section for the tab that is open) */
    private function section(string $html, string $role): string
    {
        return preg_match('#<section[^>]*aria-labelledby="users-' . $role . '".*?</section>#s', $html, $m) ? $m[0] : '';
    }

    /** @return array<int,int> the S/N numbers shown in the table */
    private function numbers(string $section): array
    {
        preg_match_all('#<td class="muted">(\d+)</td>#', $section, $m);

        return array_map('intval', $m[1]);
    }

    private function students(int $n, string $prefix = 'Student'): void
    {
        foreach (range(1, $n) as $i) {
            $this->makeUser(4, ['name' => sprintf('%s %03d', $prefix, $i), 'email' => strtolower($prefix) . "$i@example.com"]);
        }
    }

    private function open(string $url = '/console/users'): string
    {
        return $this->actingAs($this->admin)->get($url)->assertOk()->getContent();
    }

    public function test_there_is_a_tab_for_admins_examiners_and_students_and_one_table_is_shown_at_a_time(): void
    {
        $this->makeUser(3, ['name' => 'Emeka Examiner']);
        $this->makeUser(4, ['name' => 'Sola Student']);

        $html = $this->open();
        $this->assertStringContainsString('aria-label="Kinds of user"', $html);
        $this->assertLessThan(strpos($html, 'tab=examiner'), strpos($html, 'tab=admin'));
        $this->assertLessThan(strpos($html, 'tab=student'), strpos($html, 'tab=examiner'));

        // Admins is the first tab and opens by default. Nobody from another role is in it.
        $admins = $this->section($html, 'admin');
        $this->assertStringContainsString('Ada Admin', $admins);
        $this->assertStringNotContainsString('Emeka Examiner', $admins);
        $this->assertStringNotContainsString('Sola Student', $admins);
        $this->assertSame('', $this->section($html, 'examiner'));
        $this->assertSame('', $this->section($html, 'student'));

        $examiners = $this->section($this->open('/console/users?tab=examiner'), 'examiner');
        $this->assertStringContainsString('Emeka Examiner', $examiners);
        $this->assertStringNotContainsString('Ada Admin', $examiners);
        $this->assertStringNotContainsString('Sola Student', $examiners);

        $students = $this->section($this->open('/console/users?tab=student'), 'student');
        $this->assertStringContainsString('Sola Student', $students);
        $this->assertStringNotContainsString('Ada Admin', $students);
    }

    public function test_the_tabs_show_how_many_are_in_each(): void
    {
        $this->students(4);
        $this->makeUser(3);
        $html = $this->open();

        $this->assertMatchesRegularExpression('#Students <span class="badge neutral">4</span>#', $html);
        $this->assertMatchesRegularExpression('#Examiners <span class="badge neutral">1</span>#', $html);
        $this->assertMatchesRegularExpression('#Admins <span class="badge g">1</span>#', $html, 'the open tab is marked');
        $this->assertStringContainsString('aria-current="page"', $html);
    }

    public function test_every_table_numbers_its_rows_from_one(): void
    {
        $this->makeUser(3, ['name' => 'Emeka Examiner']);
        $this->makeUser(3, ['name' => 'Ngozi Examiner']);
        $this->students(3);

        $this->assertStringContainsString('<th style="width:56px">S/N</th>', $this->open());
        foreach (['admin' => 1, 'examiner' => 2, 'student' => 3] as $role => $rows) {
            $this->assertSame(range(1, $rows), $this->numbers($this->section($this->open("/console/users?tab=$role"), $role)), $role);
        }
    }

    public function test_numbering_carries_on_across_pages(): void
    {
        $this->students(30);   // 25 to a page

        $first = $this->open('/console/users?tab=student');
        $this->assertStringContainsString('Student 001', $first);
        $this->assertStringNotContainsString('Student 030', $first);
        $this->assertStringContainsString('Showing 1 to 25 of 30', $first);

        $second = $this->section($this->open('/console/users?tab=student&page=2'), 'student');
        $this->assertSame(range(26, 30), $this->numbers($second), 'the second page carries on from 26');
        $this->assertStringContainsString('Student 030', $second);
        $this->assertStringContainsString('Showing 26 to 30 of 30', $second);
    }

    public function test_the_number_of_people_per_page_can_be_chosen(): void
    {
        $this->students(60);

        $this->assertCount(50, $this->numbers($this->section($this->open('/console/users?tab=student&per_page=50'), 'student')));
        $this->assertCount(60, $this->numbers($this->section($this->open('/console/users?tab=student&per_page=100'), 'student')));
        $this->actingAs($this->admin)->get('/console/users?tab=student&per_page=9999')->assertSessionHasErrors('per_page');
    }

    public function test_a_page_past_the_end_goes_to_the_last_page(): void
    {
        $this->students(30);

        $this->actingAs($this->admin)->get('/console/users?tab=student&page=9')->assertRedirect();
        $followed = $this->followingRedirects()->actingAs($this->admin)->get('/console/users?tab=student&page=9')->getContent();
        $this->assertStringContainsString('Student 030', $followed);
    }

    public function test_long_lists_show_a_short_page_bar_with_the_first_and_last_page_and_a_jump_box(): void
    {
        $this->students(400);   // 16 pages of 25
        $middle = $this->open('/console/users?tab=student&page=8');

        $this->assertStringContainsString('aria-label="First page">1</a>', $middle);
        $this->assertStringContainsString('aria-label="Last page">16</a>', $middle);
        $this->assertStringContainsString('&hellip;', $middle);
        $this->assertStringContainsString('aria-current="page">8</span>', $middle);
        $this->assertStringNotContainsString('>3</a>', $middle, 'pages far from this one are left out');
        $this->assertStringContainsString('Go to page', $middle);
        $this->assertStringContainsString('max="16"', $middle);

        // A short list has no jump box.
        $this->assertStringNotContainsString('Go to page', $this->open('/console/users?tab=admin'));
    }

    public function test_searching_keeps_the_tab_and_opens_the_first_tab_that_has_a_match(): void
    {
        $this->makeUser(3, ['name' => 'Emeka Examiner']);
        $this->students(2);

        $html = $this->open('/console/users?q=Student 002');
        $this->assertStringContainsString('Student 002', $this->section($html, 'student'), 'admins has no match, so the students tab opens');
        $this->assertStringNotContainsString('Student 001', $html);
        $this->assertMatchesRegularExpression('#Students <span class="badge g">1</span>#', $html);
        $this->assertMatchesRegularExpression('#Admins <span class="badge neutral">0</span>#', $html);
        $this->assertStringContainsString('tab=student&amp;q=Student', $html, 'the tab links keep the search');

        // A tab chosen by hand stays open even when it has no match.
        $this->assertStringContainsString('No admins match', $this->open('/console/users?tab=admin&q=Student 002'));
    }

    public function test_status_filter_and_a_search_with_no_matches(): void
    {
        $this->students(2);
        User::where('name', 'Student 002')->update(['is_active' => 0]);

        $suspended = $this->open('/console/users?tab=student&state=suspended');
        $this->assertStringContainsString('Student 002', $suspended);
        $this->assertStringNotContainsString('Student 001', $suspended);

        $this->assertStringContainsString('match', $this->open('/console/users?q=nobody-with-this-name'));
    }

    public function test_empty_tabs_say_so_when_nothing_is_filtered(): void
    {
        $this->assertStringContainsString('No examiners', $this->section($this->open('/console/users?tab=examiner'), 'examiner'));
        $this->assertStringContainsString('No students', $this->section($this->open('/console/users?tab=student'), 'student'));
    }

    public function test_only_admins_see_the_users_screen(): void
    {
        $this->actingAs($this->makeUser(3))->get('/console/users')->assertForbidden();
        $this->actingAs($this->makeUser(4))->get('/console/users')->assertForbidden();
    }
}
