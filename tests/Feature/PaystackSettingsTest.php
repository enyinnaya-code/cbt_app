<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Payments\Paystack;
use App\Services\PaystackSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

/** The Paystack key is set from Site settings, kept encrypted, and used for payments and webhooks. */
class PaystackSettingsTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    // Made-up keys, put together here so no key-shaped text sits in the repository (GitHub's secret scanner blocks those).
    private string $live;
    private string $test;

    private function save(string $key)
    {
        return $this->actingAs($this->makeUser(1))->put(route('console.settings.paystack'), ['paystack_secret' => $key]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->live = 'sk_' . 'live_' . str_repeat('abcdef1234567890', 2) . 'WXYZ';
        $this->test = 'sk_' . 'test_' . str_repeat('abcdef1234567890', 2) . 'TEST';
        config(['services.paystack.secret' => null]);
    }

    public function test_an_admin_saves_the_key_and_payments_turn_on(): void
    {
        $this->assertFalse(Paystack::configured());

        $this->save($this->live)->assertSessionHas('success')->assertSessionHasNoErrors();

        $this->assertTrue(Paystack::configured());
        $this->assertSame($this->live, config('services.paystack.secret'));
    }

    public function test_the_key_is_stored_encrypted_and_never_shown_again(): void
    {
        $this->save($this->live);

        $stored = DB::table('settings')->where('key', 'paystack.secret')->value('value');
        $this->assertStringNotContainsString('abcdef1234567890', (string) $stored);
        $this->assertSame($this->live, Crypt::decryptString($stored));

        $page = $this->actingAs($this->makeUser(1))->get(route('console.settings.edit'))->assertOk();
        $page->assertDontSee($this->live)->assertDontSee('abcdef1234567890')->assertDontSee($stored)
            ->assertSee('Live key')->assertSee('WXYZ')->assertSee('saved here');
    }

    public function test_a_test_key_is_labelled_as_such(): void
    {
        $this->save($this->test);

        $this->actingAs($this->makeUser(1))->get(route('console.settings.edit'))->assertSee('Test key')->assertSee('Test payments use no real money');
    }

    public function test_only_real_secret_keys_are_accepted(): void
    {
        foreach (['', 'pk_live_abcdef1234567890abcdef1234567890', 'sk_live_', 'sk_live_short', 'abcdef1234567890abcdef1234567890', 'sk_prod_abcdef1234567890abcdef1234567890', "sk_live_abc def1234567890abcdef12345", 'sk_live_<script>alert(1)</script>'] as $bad) {
            $this->save($bad)->assertSessionHasErrors('paystack_secret');
        }

        $this->assertFalse(Paystack::configured());
        $this->assertNull(Setting::get('paystack.secret'));
    }

    public function test_a_wrongly_pasted_public_key_explains_itself(): void
    {
        $this->save('pk_live_abcdef1234567890abcdef1234567890')->assertSessionHasErrors(['paystack_secret' => 'That does not look like a Paystack secret key. It starts with sk_live_ or sk_test_ (not pk_, which is the public key).']);
    }

    public function test_the_saved_key_wins_over_the_one_in_env_and_removing_it_goes_back(): void
    {
        config(['services.paystack.secret' => $this->test]);   // what .env would give

        $this->save($this->live);
        $this->assertSame($this->live, config('services.paystack.secret'));

        $this->actingAs($this->makeUser(1))->put(route('console.settings.paystack'), ['forget_paystack' => '1'])->assertSessionHas('success');
        config(['services.paystack.secret' => $this->test]);   // a fresh request would read .env again
        app(PaystackSettings::class)->apply();

        $this->assertNull(app(PaystackSettings::class)->saved());
        $this->assertSame($this->test, config('services.paystack.secret'));
    }

    public function test_webhooks_are_checked_with_the_saved_key(): void
    {
        $this->save($this->live);
        $body = '{"event":"charge.success"}';

        $this->assertTrue(Paystack::validSignature($body, hash_hmac('sha512', $body, $this->live)));
        $this->assertFalse(Paystack::validSignature($body, hash_hmac('sha512', $body, $this->test)));
    }

    public function test_the_check_button_tells_a_good_key_from_a_bad_one(): void
    {
        $this->save($this->live);
        $admin = $this->makeUser(1);

        Http::fake(['api.paystack.co/*' => Http::response(['status' => false, 'message' => 'Transaction reference not found.'], 404)]);
        $this->actingAs($admin)->post(route('console.settings.paystack.check'))->assertSessionHas('success', fn ($m) => str_contains($m, 'accepted the key'));
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer ' . $this->live) && str_contains($r->url(), '/transaction/verify/'));
    }

    public function test_the_check_refuses_a_key_paystack_does_not_accept(): void
    {
        $this->save($this->live);
        Http::fake(['api.paystack.co/*' => Http::response(['status' => false, 'message' => 'Invalid key'], 401)]);

        $this->actingAs($this->makeUser(1))->post(route('console.settings.paystack.check'))->assertSessionHas('error', fn ($m) => str_contains($m, 'refused this key'));
    }

    public function test_the_check_says_so_when_paystack_cannot_be_reached(): void
    {
        $this->save($this->live);
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->assertStringContainsString('Could not reach Paystack', (string) app(PaystackSettings::class)->check());
    }

    public function test_the_check_charges_nothing_and_changes_no_order(): void
    {
        $this->save($this->live);
        Http::fake(['api.paystack.co/*' => Http::response(['status' => false], 404)]);

        app(PaystackSettings::class)->check();

        Http::assertSent(fn ($r) => $r->method() === 'GET');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/initialize') || $r->method() === 'POST');
    }

    public function test_only_admins_can_see_or_change_it(): void
    {
        foreach ([3, 4] as $type) {
            $user = $this->makeUser($type);
            $this->actingAs($user)->put(route('console.settings.paystack'), ['paystack_secret' => $this->live])->assertForbidden();
            $this->actingAs($user)->post(route('console.settings.paystack.check'))->assertForbidden();
        }

        $this->assertNull(Setting::get('paystack.secret'));
        $this->assertFalse(Paystack::configured());
    }

    public function test_a_signed_out_visitor_cannot_change_it(): void
    {
        $this->put(route('console.settings.paystack'), ['paystack_secret' => $this->live])->assertRedirect(route('login'));
    }

    public function test_the_settings_page_shows_the_section_and_the_webhook_address(): void
    {
        $this->actingAs($this->makeUser(1))->get(route('console.settings.edit'))->assertOk()
            ->assertSee('Paystack key')->assertSee('Not set')->assertSee(url('/api/webhooks/paystack'))->assertSee(route('checkout.callback'));
    }

    public function test_the_check_uses_a_reference_made_of_plain_letters(): void
    {
        $this->save($this->live);
        Http::fake(['api.paystack.co/*' => Http::response(['status' => false, 'message' => 'Transaction reference not found.'], 404)]);

        app(PaystackSettings::class)->check();

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/transaction/verify/doesnotexist'));
    }

    public function test_an_unexpected_answer_shows_what_paystack_said_as_plain_text(): void
    {
        $this->save($this->live);
        Http::fake(['api.paystack.co/*' => Http::response(['status' => false, 'message' => 'Invalid reference <b>x</b>'], 400)]);

        $message = (string) app(PaystackSettings::class)->check();

        $this->assertStringContainsString('(400)', $message);
        $this->assertStringContainsString('Invalid reference', $message);

        // Shown through Blade it is escaped; and a very long message is cut short.
        Http::fake(['api.paystack.co/*' => Http::response(['status' => false, 'message' => str_repeat('x', 500)], 400)]);
        $this->assertLessThan(260, mb_strlen((string) app(PaystackSettings::class)->check()));
    }
}
