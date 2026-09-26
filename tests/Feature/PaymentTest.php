<?php

namespace Tests\Feature;

use App\Models\Entitlement;
use App\Models\Exam;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Subject;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    private const SECRET = 'sk_test_secret';

    private Exam $exam;
    private Subject $physics;
    private Subject $chemistry;
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        [$this->exam, $this->physics] = $this->makeExamAndSubject('JAMB', 'Physics', null, 1500);
        [, $this->chemistry] = $this->makeExamAndSubject('JAMB', 'Chemistry', null, 1000);
        [, $free] = $this->makeExamAndSubject('JAMB', 'English Language', 'Use of English', 0);

        foreach ([$this->physics, $this->chemistry, $free] as $subject) {
            $this->makeQuestion($this->makePaper($this->exam, $subject), '<p>Q</p>');
        }

        $this->student = $this->makeUser(4);
    }

    private function paystackOn(): void
    {
        config(['services.paystack.secret' => self::SECRET]);
    }

    private function bankOn(): void
    {
        Setting::put(['bank.name' => 'GTBank', 'bank.account_number' => '0123456789', 'bank.account_name' => 'TestaCBT Ltd']);
    }

    private function checkout(array $over = [])
    {
        return $this->actingAs($this->student)->post('/checkout', $over + ['exam' => 'jamb', 'subjects' => ['physics'], 'method' => 'paystack']);
    }

    private function verified(Order $order, array $over = []): array
    {
        return ['status' => true, 'data' => $over + ['status' => 'success', 'reference' => $order->reference, 'amount' => $order->amount * 100, 'currency' => 'NGN', 'id' => 42, 'channel' => 'card']];
    }

    private function signed(array $payload): array
    {
        $body = json_encode($payload);

        return [$body, hash_hmac('sha512', $body, self::SECRET)];
    }

    private function webhook(string $body, ?string $signature)
    {
        return $this->call('POST', '/api/webhooks/paystack', [], [], [], ['CONTENT_TYPE' => 'application/json'] + ($signature ? ['HTTP_X_PAYSTACK_SIGNATURE' => $signature] : []), $body);
    }

    private function entitlement(Subject $subject): ?Entitlement
    {
        return Entitlement::where('user_id', $this->student->id)->where('subject_id', $subject->id)->first();
    }

    // ------------------------------------------------------------------------------------------ what can be bought

    public function test_only_priced_subjects_with_questions_are_for_sale(): void
    {
        $empty = Subject::create(['name' => 'History', 'slug' => 'history', 'code' => 'Hi']);
        $this->exam->subjects()->attach($empty->id, ['price' => 900]);

        $catalog = app(OrderService::class)->catalog($this->student, $this->exam);

        $this->assertEqualsCanonicalizing(['chemistry', 'physics'], $catalog->pluck('slug')->all());
    }

    public function test_the_checkout_page_lists_what_can_be_bought_and_the_payment_options(): void
    {
        $this->paystackOn();
        $this->bankOn();

        $this->actingAs($this->student)->get('/checkout?exam=jamb&subjects[]=physics')->assertOk()
            ->assertSee('Physics')->assertSee('₦1,500')->assertSee('Chemistry')->assertDontSee('Use of English')
            ->assertSee('Card, bank or USSD')->assertSee('Bank transfer');
    }

    public function test_checkout_says_payments_are_closed_when_neither_method_is_set_up(): void
    {
        $this->actingAs($this->student)->get('/checkout?exam=jamb')->assertOk()->assertSee('Payments are not open yet');
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/checkout')->assertRedirect('/login');
        $this->post('/checkout', [])->assertRedirect('/login');
        $this->get('/orders')->assertRedirect('/login');
    }

    public function test_the_price_comes_from_the_server_not_the_browser(): void
    {
        $this->paystackOn();
        Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/x']])]);

        $this->checkout(['subjects' => ['physics', 'chemistry'], 'amount' => 1, 'price' => 1])->assertRedirect('https://checkout.paystack.com/x');

        $order = Order::firstOrFail();
        $this->assertSame(2500, $order->amount);
        $this->assertSame(2, $order->items()->count());
        Http::assertSent(fn ($r) => $r['amount'] === 250000 && $r['currency'] === 'NGN' && $r['email'] === $this->student->email && $r['reference'] === $order->reference
            && str_ends_with($r['callback_url'], '/checkout/callback') && $r->hasHeader('Authorization', 'Bearer ' . self::SECRET));
    }

    public function test_a_free_subject_or_an_unknown_one_cannot_be_ordered(): void
    {
        $this->paystackOn();

        $this->checkout(['subjects' => ['english-language']])->assertSessionHasErrors('subjects');
        $this->checkout(['subjects' => ['nonsense']])->assertSessionHasErrors('subjects');
        $this->checkout(['subjects' => []])->assertSessionHasErrors('subjects');
        $this->assertSame(0, Order::count());
    }

    public function test_the_bundle_costs_the_bundle_price_and_covers_every_paid_subject(): void
    {
        $this->paystackOn();
        $this->exam->update(['bundle_price' => 2000]);
        Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/x']])]);

        $this->checkout(['bundle' => 1, 'subjects' => []])->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertTrue($order->is_bundle);
        $this->assertSame(2000, $order->amount);
        $this->assertEqualsCanonicalizing([$this->physics->id, $this->chemistry->id], $order->items->pluck('subject_id')->all());
    }

    public function test_a_bundle_cannot_be_ordered_when_the_exam_has_no_bundle_price(): void
    {
        $this->paystackOn();

        $this->checkout(['bundle' => 1])->assertSessionHasErrors('subjects');
    }

    public function test_a_subject_you_already_have_cannot_be_bought_again_until_it_is_nearly_over(): void
    {
        $this->paystackOn();
        Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/x']])]);
        $entitlement = Entitlement::create(['user_id' => $this->student->id, 'exam_id' => $this->exam->id, 'subject_id' => $this->physics->id, 'expires_at' => now()->addDays(200)]);

        $this->checkout()->assertSessionHasErrors('subjects');

        $entitlement->update(['expires_at' => now()->addDays(10)]);
        $this->checkout()->assertRedirect('https://checkout.paystack.com/x');
    }

    // ------------------------------------------------------------------------------------------ Paystack

    public function test_online_payment_is_refused_when_paystack_is_not_set_up(): void
    {
        $this->checkout()->assertSessionHas('error');
        $this->assertSame(0, Order::count());
    }

    public function test_if_paystack_cannot_be_reached_nothing_is_charged_and_the_order_is_marked_failed(): void
    {
        $this->paystackOn();
        Http::fake(['api.paystack.co/*' => Http::response(['status' => false, 'message' => 'Invalid key'], 401)]);

        $this->checkout()->assertRedirect(route('checkout.show', ['exam' => 'jamb']))->assertSessionHas('error');

        $this->assertSame(Order::FAILED, Order::firstOrFail()->status);
        $this->assertNull($this->entitlement($this->physics));
    }

    public function test_a_confirmed_payment_unlocks_the_subject_for_the_purchased_time(): void
    {
        $this->paystackOn();
        $order = $this->pendingOrder();
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response($this->verified($order))]);

        $this->actingAs($this->student)->get('/checkout/callback?reference=' . $order->reference)->assertRedirect(route('orders.show', $order->reference))->assertSessionHas('success');

        $this->assertSame(Order::PAID, $order->fresh()->status);
        $this->assertEqualsWithDelta(now()->addDays(365)->timestamp, $this->entitlement($this->physics)->expires_at->timestamp, 5);
        $this->assertNull($this->entitlement($this->chemistry));

        $this->actingAs($this->student)->get('/practice?exam=jamb&subject=physics')->assertSee('Unlocked until');
    }

    private function pendingOrder(array $slugs = ['physics']): Order
    {
        return app(OrderService::class)->create($this->student, $this->exam, $slugs, false, Order::PAYSTACK);
    }

    public function test_the_access_length_is_fixed_when_the_order_is_made(): void
    {
        $this->paystackOn();
        Setting::put(['pricing.access_days' => '90']);
        $order = $this->pendingOrder();
        Setting::put(['pricing.access_days' => '10']);
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response($this->verified($order))]);

        $this->get('/checkout/callback?reference=' . $order->reference);

        $this->assertEqualsWithDelta(now()->addDays(90)->timestamp, $this->entitlement($this->physics)->expires_at->timestamp, 5);
    }

    public function test_paying_twice_for_the_same_order_only_counts_once(): void
    {
        $this->paystackOn();
        $order = $this->pendingOrder();
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response($this->verified($order))]);

        $this->get('/checkout/callback?reference=' . $order->reference);
        $first = $this->entitlement($this->physics)->expires_at;
        $this->get('/checkout/callback?reference=' . $order->reference);

        $this->assertTrue($first->equalTo($this->entitlement($this->physics)->fresh()->expires_at));
        $this->assertSame(1, Entitlement::count());
    }

    public function test_a_new_purchase_adds_time_to_the_end_of_the_current_one(): void
    {
        $this->paystackOn();
        $current = now()->addDays(10);
        Entitlement::create(['user_id' => $this->student->id, 'exam_id' => $this->exam->id, 'subject_id' => $this->physics->id, 'expires_at' => $current]);
        $order = $this->pendingOrder();
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response($this->verified($order))]);

        $this->get('/checkout/callback?reference=' . $order->reference);

        $this->assertEqualsWithDelta($current->copy()->addDays(365)->timestamp, $this->entitlement($this->physics)->expires_at->timestamp, 5);
        $this->assertSame(1, Entitlement::count());
    }

    public function test_a_payment_for_the_wrong_amount_unlocks_nothing(): void
    {
        $this->paystackOn();
        $order = $this->pendingOrder();
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response($this->verified($order, ['amount' => 100]))]);

        $this->actingAs($this->student)->get('/checkout/callback?reference=' . $order->reference)->assertSessionHas('error');

        $this->assertSame(Order::PENDING, $order->fresh()->status);
        $this->assertNull($this->entitlement($this->physics));
    }

    public function test_a_payment_in_another_currency_or_for_another_reference_unlocks_nothing(): void
    {
        $this->paystackOn();
        $order = $this->pendingOrder();

        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response($this->verified($order, ['currency' => 'USD']))]);
        $this->get('/checkout/callback?reference=' . $order->reference);
        $this->assertNull($this->entitlement($this->physics));

        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response($this->verified($order, ['reference' => 'SOMEONE-ELSE']))]);
        $this->get('/checkout/callback?reference=' . $order->reference);
        $this->assertNull($this->entitlement($this->physics));
    }

    public function test_a_failed_or_abandoned_payment_is_marked_failed(): void
    {
        $this->paystackOn();
        $order = $this->pendingOrder();
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response($this->verified($order, ['status' => 'abandoned']))]);

        $this->actingAs($this->student)->get('/checkout/callback?reference=' . $order->reference)->assertSessionHas('error');

        $this->assertSame(Order::FAILED, $order->fresh()->status);
    }

    public function test_if_paystack_is_unreachable_the_order_stays_open_for_the_webhook(): void
    {
        $this->paystackOn();
        $order = $this->pendingOrder();
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->actingAs($this->student)->get('/checkout/callback?reference=' . $order->reference)->assertSessionHas('error');

        $this->assertSame(Order::PENDING, $order->fresh()->status);
    }

    public function test_an_unknown_reference_is_handled_politely(): void
    {
        $this->get('/checkout/callback?reference=nope')->assertRedirect(route('pricing'));
    }

    // ------------------------------------------------------------------------------------------ webhook

    public function test_a_genuine_paystack_webhook_unlocks_the_subject(): void
    {
        $this->paystackOn();
        $order = $this->pendingOrder();
        [$body, $signature] = $this->signed(['event' => 'charge.success', 'data' => $this->verified($order)['data']]);

        $this->webhook($body, $signature)->assertOk();

        $this->assertSame(Order::PAID, $order->fresh()->status);
        $this->assertNotNull($this->entitlement($this->physics));
    }

    public function test_a_webhook_with_a_bad_or_missing_signature_is_refused(): void
    {
        $this->paystackOn();
        $order = $this->pendingOrder();
        $body = json_encode(['event' => 'charge.success', 'data' => $this->verified($order)['data']]);

        $this->webhook($body, str_repeat('a', 128))->assertUnauthorized();
        $this->webhook($body, null)->assertUnauthorized();
        $this->assertSame(Order::PENDING, $order->fresh()->status);
    }

    public function test_a_webhook_cannot_be_accepted_when_paystack_is_not_configured(): void
    {
        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => 'x']]);

        $this->webhook($body, hash_hmac('sha512', $body, ''))->assertUnauthorized();
    }

    public function test_a_webhook_with_the_wrong_amount_or_an_unknown_order_changes_nothing(): void
    {
        $this->paystackOn();
        $order = $this->pendingOrder();

        [$body, $signature] = $this->signed(['event' => 'charge.success', 'data' => $this->verified($order, ['amount' => 5000])['data']]);
        $this->webhook($body, $signature)->assertOk();
        $this->assertSame(Order::PENDING, $order->fresh()->status);

        [$body, $signature] = $this->signed(['event' => 'charge.success', 'data' => ['reference' => 'TCB-UNKNOWN', 'status' => 'success', 'amount' => 1, 'currency' => 'NGN']]);
        $this->webhook($body, $signature)->assertOk();

        [$body, $signature] = $this->signed(['event' => 'transfer.success', 'data' => $this->verified($order)['data']]);
        $this->webhook($body, $signature)->assertOk();
        $this->assertSame(Order::PENDING, $order->fresh()->status);
    }

    public function test_the_redirect_and_the_webhook_together_still_count_once(): void
    {
        $this->paystackOn();
        $order = $this->pendingOrder();
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response($this->verified($order))]);
        [$body, $signature] = $this->signed(['event' => 'charge.success', 'data' => $this->verified($order)['data']]);

        $this->webhook($body, $signature);
        $first = $this->entitlement($this->physics)->expires_at;
        $this->get('/checkout/callback?reference=' . $order->reference);
        $this->webhook($body, $signature);

        $this->assertTrue($first->equalTo($this->entitlement($this->physics)->fresh()->expires_at));
    }

    // ------------------------------------------------------------------------------------------ bank transfer

    public function test_bank_transfer_shows_the_account_and_the_reference_to_quote(): void
    {
        $this->bankOn();

        $this->checkout(['method' => 'bank_transfer'])->assertRedirect();
        $order = Order::firstOrFail();

        $this->actingAs($this->student)->get(route('orders.show', $order->reference))->assertOk()
            ->assertSee('GTBank')->assertSee('0123456789')->assertSee('TestaCBT Ltd')->assertSee($order->reference)->assertSee('₦1,500');
    }

    public function test_bank_transfer_is_refused_until_an_account_is_set(): void
    {
        $this->checkout(['method' => 'bank_transfer'])->assertSessionHas('error');
        $this->assertSame(0, Order::count());
    }

    private function transferOrder(): Order
    {
        $this->bankOn();
        $this->checkout(['method' => 'bank_transfer']);

        return Order::firstOrFail();
    }

    public function test_the_student_says_they_paid_and_an_admin_approves(): void
    {
        $order = $this->transferOrder();

        $this->actingAs($this->student)->post(route('orders.proof', $order->reference), [
            'payer_name' => 'Ada Obi', 'note' => 'Sent this morning', 'proof' => UploadedFile::fake()->image('receipt.jpg'),
        ])->assertRedirect()->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(Order::AWAITING, $order->status);
        $this->assertSame('Ada Obi', $order->payer_name);
        Storage::disk('local')->assertExists($order->proof_path);
        $this->assertNull($this->entitlement($this->physics));

        $admin = $this->makeUser(2);
        $this->actingAs($admin)->get('/console/payments')->assertOk()->assertSee($order->reference)->assertSee('1 bank transfer to check');
        $this->actingAs($admin)->get(route('console.payments.show', $order))->assertOk()->assertSee('Ada Obi');
        $this->actingAs($admin)->get(route('console.payments.proof', $order))->assertOk();

        $this->actingAs($admin)->post(route('console.payments.approve', $order))->assertRedirect()->assertSessionHas('success');

        $this->assertSame(Order::PAID, $order->fresh()->status);
        $this->assertSame($admin->id, $order->fresh()->decided_by);
        $this->assertNotNull($this->entitlement($this->physics));
    }

    public function test_approving_twice_does_not_extend_the_access_twice(): void
    {
        $order = $this->transferOrder();
        $admin = $this->makeUser(2);

        $this->actingAs($admin)->post(route('console.payments.approve', $order));
        $first = $this->entitlement($this->physics)->expires_at;
        $this->actingAs($admin)->post(route('console.payments.approve', $order))->assertSessionHas('error');

        $this->assertTrue($first->equalTo($this->entitlement($this->physics)->fresh()->expires_at));
    }

    public function test_a_rejected_transfer_shows_the_student_why_and_unlocks_nothing(): void
    {
        $order = $this->transferOrder();
        $admin = $this->makeUser(2);

        $this->actingAs($admin)->post(route('console.payments.reject', $order), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post(route('console.payments.reject', $order), ['reason' => 'No such amount in our account'])->assertRedirect();

        $this->assertSame(Order::REJECTED, $order->fresh()->status);
        $this->assertNull($this->entitlement($this->physics));
        $this->actingAs($this->student)->get(route('orders.show', $order->reference))->assertSee('No such amount in our account');
    }

    public function test_a_paid_order_cannot_be_rejected(): void
    {
        $order = $this->transferOrder();
        $admin = $this->makeUser(2);
        $this->actingAs($admin)->post(route('console.payments.approve', $order));

        $this->actingAs($admin)->post(route('console.payments.reject', $order), ['reason' => 'Changed my mind'])->assertSessionHas('error');

        $this->assertSame(Order::PAID, $order->fresh()->status);
    }

    public function test_the_receipt_must_be_a_picture_or_pdf_within_the_size_limit(): void
    {
        $order = $this->transferOrder();

        $this->actingAs($this->student)->post(route('orders.proof', $order->reference), ['payer_name' => 'A', 'proof' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('proof');
        $this->actingAs($this->student)->post(route('orders.proof', $order->reference), ['payer_name' => 'A', 'proof' => UploadedFile::fake()->create('big.pdf', 5000, 'application/pdf')])->assertSessionHasErrors('proof');
        $this->actingAs($this->student)->post(route('orders.proof', $order->reference), ['payer_name' => ''])->assertSessionHasErrors('payer_name');
        $this->assertSame(Order::PENDING, $order->fresh()->status);
    }

    public function test_a_student_can_cancel_an_unpaid_order_but_not_a_paid_one(): void
    {
        $order = $this->transferOrder();

        $this->actingAs($this->student)->post(route('orders.cancel', $order->reference))->assertRedirect(route('orders.index'));
        $this->assertSame(Order::CANCELLED, $order->fresh()->status);

        $paid = $this->transferOrder2();
        $this->actingAs($this->makeUser(2))->post(route('console.payments.approve', $paid));
        $this->actingAs($this->student)->post(route('orders.cancel', $paid->reference))->assertNotFound();
    }

    private function transferOrder2(): Order
    {
        $this->checkout(['method' => 'bank_transfer', 'subjects' => ['chemistry']]);

        return Order::where('id', '!=', Order::min('id'))->firstOrFail();
    }

    // ------------------------------------------------------------------------------------------ privacy and roles

    public function test_students_cannot_see_or_act_on_each_others_orders(): void
    {
        $order = $this->transferOrder();
        $other = $this->makeUser(4);

        $this->actingAs($other)->get(route('orders.show', $order->reference))->assertNotFound();
        $this->actingAs($other)->post(route('orders.proof', $order->reference), ['payer_name' => 'X'])->assertNotFound();
        $this->actingAs($other)->post(route('orders.cancel', $order->reference))->assertNotFound();
        $this->actingAs($other)->get('/orders')->assertOk()->assertDontSee($order->reference);
    }

    public function test_only_admins_reach_payments_and_receipts(): void
    {
        $order = $this->transferOrder();
        $this->actingAs($this->student)->post(route('orders.proof', $order->reference), ['payer_name' => 'Ada', 'proof' => UploadedFile::fake()->image('r.jpg')]);

        foreach ([[ 'get', '/console/payments'], ['get', route('console.payments.show', $order)], ['get', route('console.payments.proof', $order)], ['post', route('console.payments.approve', $order)]] as [$method, $url]) {
            $this->actingAs($this->student)->{$method}($url)->assertForbidden();
            $this->actingAs($this->makeUser(3))->{$method}($url)->assertForbidden();
        }

        $this->assertNull($this->entitlement($this->physics));
        $this->assertSame(Order::AWAITING, $order->fresh()->status);
    }

    public function test_the_pricing_page_is_public_and_shows_free_and_paid_subjects(): void
    {
        $this->exam->update(['bundle_price' => 2000]);

        $this->get('/pricing?exam=jamb')->assertOk()
            ->assertSee('Physics')->assertSee('₦1,500')->assertSee('Use of English')->assertSee('Free')
            ->assertSee('All JAMB subjects for ₦2,000')->assertSee('You save ₦500')->assertSee('Create a free account');
    }

    public function test_the_landing_page_mentions_pricing(): void
    {
        $this->get('/')->assertOk()->assertSee('Free to start')->assertSee('See pricing');
    }

    public function test_settings_save_the_bank_account_and_reject_bad_numbers(): void
    {
        $admin = $this->makeUser(2);
        $base = ['app_play_store_url' => '', 'app_app_store_url' => '', 'app_apk_url' => '', 'support_email' => '', 'support_whatsapp' => ''];

        $this->actingAs($admin)->put('/console/settings', $base + ['bank_name' => 'Access', 'bank_account_number' => '0987654321', 'bank_account_name' => 'TestaCBT'])->assertSessionHasNoErrors();
        $this->assertSame('0987654321', Setting::get('bank.account_number'));

        $this->actingAs($admin)->put('/console/settings', $base + ['bank_account_number' => '12ab'])->assertSessionHasErrors('bank_account_number');
    }
}
