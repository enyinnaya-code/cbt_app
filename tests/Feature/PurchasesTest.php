<?php

namespace Tests\Feature;

use App\Models\Entitlement;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Purchases;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

/** My purchases: what is unlocked until when, the payments, receipts, and reminders before a subject ends. */
class PurchasesTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    private User $student;
    private $exam;
    private $physics;
    private $chemistry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->student = $this->makeUser(4, ['name' => 'Ada Obi', 'email' => 'ada@example.com']);
        [$this->exam, $this->physics] = $this->makeExamAndSubject('JAMB', 'Physics', null, 1500);
        [, $this->chemistry] = $this->makeExamAndSubject('JAMB', 'Chemistry', null, 1500);

        // A subject can only be bought when it has questions.
        foreach ([$this->physics, $this->chemistry] as $subject) {
            $this->makeQuestion($this->makePaper($this->exam, $subject), '<p>Q</p>');
        }
    }

    private function paid(User $user, array $slugs = ['physics'], string $method = Order::PAYSTACK): Order
    {
        $orders = app(OrderService::class);

        return $orders->markPaid($orders->create($user, $this->exam, $slugs, false, $method));
    }

    private function entitlement($subject, ?string $expires = null): Entitlement
    {
        return Entitlement::updateOrCreate(['user_id' => $this->student->id, 'exam_id' => $this->exam->id, 'subject_id' => $subject->id], ['expires_at' => $expires ? now()->parse($expires) : now()->addDays(300)]);
    }

    // ------------------------------------------------------------------ the page

    public function test_the_page_lists_what_is_unlocked_and_what_was_paid(): void
    {
        $order = $this->paid($this->student, ['physics', 'chemistry']);

        $this->actingAs($this->student)->get(route('orders.index'))->assertOk()
            ->assertSee('Physics')->assertSee('Chemistry')->assertSee($order->reference)->assertSee('Paid')
            ->assertSee(route('orders.receipt', $order->reference), false);
    }

    public function test_a_subject_ending_soon_or_ended_offers_renewal_and_a_healthy_one_does_not(): void
    {
        $this->entitlement($this->physics, null);                       // 300 days left
        $this->entitlement($this->chemistry, now()->addDays(5)->toDateTimeString());

        $page = $this->actingAs($this->student)->get(route('orders.index'))->assertOk();
        $page->assertSee('Ends in 5 days')->assertSee(e(route('checkout.show', ['exam' => 'jamb', 'subjects' => ['chemistry']])), false);
        $page->assertDontSee(e(route('checkout.show', ['exam' => 'jamb', 'subjects' => ['physics']])), false);

        $this->entitlement($this->chemistry, now()->subDay()->toDateTimeString());
        $this->actingAs($this->student)->get(route('orders.index'))->assertSee('Ended')->assertSee('Renew');
    }

    public function test_a_renewal_replaces_the_old_entry_instead_of_listing_the_subject_twice(): void
    {
        $this->paid($this->student, ['physics']);
        $this->entitlement($this->physics, now()->addDays(10)->toDateTimeString());   // close enough to the end to renew
        $this->paid($this->student, ['physics']);

        $unlocked = (new Purchases($this->student))->unlocked();

        $this->assertCount(1, $unlocked);
        $this->assertGreaterThan(300, Purchases::daysLeft($unlocked->first()), 'the renewal added a year on top of what was left');
    }

    // ------------------------------------------------------------------ reminders

    public function test_home_reminds_about_a_subject_ending_within_two_weeks_only(): void
    {
        $this->entitlement($this->physics, now()->addDays(9)->toDateTimeString());
        $this->entitlement($this->chemistry, now()->addDays(40)->toDateTimeString());

        $this->actingAs($this->student)->get(route('dashboard'))->assertOk()
            ->assertSee('Physics ends in 9 days')->assertDontSee('Chemistry ends');
    }

    public function test_an_ended_subject_is_not_a_reminder_it_is_on_my_purchases(): void
    {
        $this->entitlement($this->physics, now()->subDays(2)->toDateTimeString());

        $this->actingAs($this->student)->get(route('dashboard'))->assertOk()->assertDontSee('Physics ends');
        $this->assertCount(0, (new Purchases($this->student))->endingSoon());
    }

    // ------------------------------------------------------------------ receipts

    public function test_a_paid_order_has_a_receipt_with_the_details(): void
    {
        $order = $this->paid($this->student, ['physics']);

        $this->actingAs($this->student)->get(route('orders.receipt', $order->reference))->assertOk()
            ->assertSee('Payment receipt')->assertSee($order->reference)->assertSee('Ada Obi')->assertSee('ada@example.com')
            ->assertSee('Physics')->assertSee('JAMB')->assertSee('365 days')->assertSee('Total paid')->assertSee('PAID')->assertSee('window.print()', false);
    }

    public function test_an_unpaid_order_has_no_receipt(): void
    {
        $open = app(OrderService::class)->create($this->student, $this->exam, ['physics'], false, Order::PAYSTACK);

        $this->actingAs($this->student)->get(route('orders.receipt', $open->reference))->assertNotFound();
    }

    public function test_nobody_can_open_someone_elses_receipt_or_a_made_up_one(): void
    {
        $order = $this->paid($this->student, ['physics']);
        $other = $this->makeUser(4);

        $this->actingAs($other)->get(route('orders.receipt', $order->reference))->assertNotFound();
        $this->actingAs($this->student)->get(route('orders.receipt', 'TCB-NOPE000000'))->assertNotFound();
        auth()->logout();
        $this->get(route('orders.receipt', $order->reference))->assertRedirect(route('login'));
    }

    public function test_the_order_page_links_to_the_receipt_once_paid(): void
    {
        $order = $this->paid($this->student, ['physics']);

        $this->actingAs($this->student)->get(route('orders.show', $order->reference))->assertSee('View receipt');
    }

    // ------------------------------------------------------------------ easy to find

    public function test_the_profile_page_links_to_my_purchases(): void
    {
        $this->paid($this->student, ['physics']);

        $this->actingAs($this->student)->get(route('profile'))->assertOk()->assertSee('My purchases')->assertSee('1 subject unlocked')->assertSee(route('orders.index'), false);
    }

    // ------------------------------------------------------------------ the app

    public function test_the_app_gets_its_own_unlocked_subjects_and_payments(): void
    {
        $this->paid($this->student, ['physics']);
        $this->entitlement($this->chemistry, now()->addDays(3)->toDateTimeString());
        $auth = ['Authorization' => 'Bearer ' . $this->student->createToken('t')->plainTextToken];

        $json = $this->getJson('/api/v1/purchases', $auth)->assertOk()
            ->assertJsonPath('remind_days', 14)->assertJsonCount(2, 'unlocked')->assertJsonCount(1, 'payments')
            ->assertJsonPath('payments.0.status', 'paid')->assertJsonPath('payments.0.subjects.0', 'Physics')->json();

        $chem = collect($json['unlocked'])->firstWhere('subject_slug', 'chemistry');
        $this->assertSame(3, $chem['days_left']);
        $this->assertFalse($chem['expired']);
    }

    public function test_the_app_never_sees_another_students_payments(): void
    {
        $other = $this->makeUser(4);
        $this->paid($other, ['physics']);
        $auth = ['Authorization' => 'Bearer ' . $this->student->createToken('t')->plainTextToken];

        $this->getJson('/api/v1/purchases', $auth)->assertOk()->assertJsonCount(0, 'unlocked')->assertJsonCount(0, 'payments');

        $this->app['auth']->forgetGuards();   // a real second request starts with no remembered user
        $this->getJson('/api/v1/purchases')->assertUnauthorized();
    }
}
