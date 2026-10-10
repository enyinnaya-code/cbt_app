<?php

namespace Tests\Feature;

use App\Mail\SettingsTestMail;
use App\Models\Setting;
use App\Services\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

/** The outgoing email account is set from Site settings, kept encrypted, and used for every email the site sends. */
class MailSettingsTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    private function form(array $over = []): array
    {
        return $over + [
            'mail_host' => 'smtp.example.com', 'mail_port' => 587, 'mail_encryption' => 'tls', 'mail_username' => 'sender@example.com',
            'mail_password' => 'S3cret-Pass!', 'mail_from_address' => 'no-reply@example.com', 'mail_from_name' => 'TestaCBT',
        ];
    }

    private function save(array $over = [])
    {
        return $this->actingAs($this->makeUser(1))->put(route('console.settings.mail'), $this->form($over));
    }

    public function test_an_admin_saves_the_email_account(): void
    {
        $this->save()->assertSessionHas('success')->assertSessionHasNoErrors();

        $this->assertSame('smtp.example.com', Setting::get('mail.host'));
        $this->assertSame('587', Setting::get('mail.port'));
        $this->assertSame('tls', Setting::get('mail.encryption'));
        $this->assertSame('sender@example.com', Setting::get('mail.username'));
        $this->assertSame('no-reply@example.com', Setting::get('mail.from_address'));
        $this->assertTrue(app(MailSettings::class)->configured());
    }

    public function test_the_password_is_stored_encrypted_and_never_shown_again(): void
    {
        $this->save();

        $stored = DB::table('settings')->where('key', 'mail.password')->value('value');
        $this->assertNotSame('S3cret-Pass!', $stored);
        $this->assertStringNotContainsString('S3cret', (string) $stored);
        $this->assertSame('S3cret-Pass!', Crypt::decryptString($stored));
        $this->assertSame('S3cret-Pass!', app(MailSettings::class)->password());

        $page = $this->actingAs($this->makeUser(1))->get(route('console.settings.edit'))->assertOk();
        $page->assertDontSee('S3cret-Pass!')->assertDontSee($stored)->assertSee('A password is saved')->assertSee('smtp.example.com')->assertSee('In use');
    }

    public function test_a_blank_password_keeps_the_saved_one_and_a_new_one_replaces_it(): void
    {
        $this->save();
        $first = DB::table('settings')->where('key', 'mail.password')->value('value');

        $this->save(['mail_host' => 'smtp.other.com', 'mail_password' => ''])->assertSessionHasNoErrors();
        $this->assertSame('smtp.other.com', Setting::get('mail.host'));
        $this->assertSame($first, DB::table('settings')->where('key', 'mail.password')->value('value'), 'password untouched');

        $this->save(['mail_password' => 'Brand-New-1'])->assertSessionHasNoErrors();
        $this->assertSame('Brand-New-1', app(MailSettings::class)->password());
    }

    public function test_the_saved_password_can_be_removed(): void
    {
        $this->save();

        $this->save(['mail_password' => '', 'clear_mail_password' => '1']);

        $this->assertNull(app(MailSettings::class)->password());
    }

    public function test_the_whole_account_can_be_forgotten(): void
    {
        $this->save();

        $this->actingAs($this->makeUser(1))->put(route('console.settings.mail'), ['forget_mail' => '1'])->assertSessionHas('success');

        $this->assertFalse(app(MailSettings::class)->configured());
        $this->assertNull(Setting::get('mail.host'));
        $this->assertNull(app(MailSettings::class)->password());
    }

    public function test_the_saved_account_is_what_laravel_uses_to_send(): void
    {
        $this->save(['mail_encryption' => 'ssl', 'mail_port' => 465]);
        config(['mail.default' => 'log']);

        app(MailSettings::class)->apply();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('sender@example.com', config('mail.mailers.smtp.username'));
        $this->assertSame('S3cret-Pass!', config('mail.mailers.smtp.password'));
        $this->assertSame('no-reply@example.com', config('mail.from.address'));
        $this->assertSame('TestaCBT', config('mail.from.name'));
    }

    public function test_with_nothing_saved_mail_keeps_going_where_the_server_says(): void
    {
        config(['mail.default' => 'log']);

        app(MailSettings::class)->apply();

        $this->assertSame('log', config('mail.default'));
    }

    public function test_the_form_checks_every_field(): void
    {
        $admin = $this->makeUser(1);

        foreach ([
            ['mail_host' => ''], ['mail_host' => 'https://smtp.example.com'], ['mail_host' => 'smtp.example.com:587'], ['mail_host' => 'bad host'],
            ['mail_port' => 0], ['mail_port' => 70000], ['mail_port' => 'abc'], ['mail_encryption' => 'none'], ['mail_from_address' => 'not-an-email'], ['mail_from_address' => ''],
        ] as $bad) {
            $this->actingAs($admin)->put(route('console.settings.mail'), $this->form($bad))->assertSessionHasErrors();
        }

        $this->assertNull(Setting::get('mail.host'), 'nothing was saved');
    }

    public function test_only_admins_can_see_or_change_it(): void
    {
        foreach ([3, 4] as $type) {
            $user = $this->makeUser($type);
            $this->actingAs($user)->put(route('console.settings.mail'), $this->form())->assertForbidden();
            $this->actingAs($user)->post(route('console.settings.mail.test'))->assertForbidden();
            $this->actingAs($user)->get(route('console.settings.edit'))->assertForbidden();
        }

        $this->assertNull(Setting::get('mail.host'));
    }

    public function test_a_signed_out_visitor_cannot_change_it(): void
    {
        $this->put(route('console.settings.mail'), $this->form())->assertRedirect(route('login'));

        $this->assertNull(Setting::get('mail.host'));
    }

    public function test_a_test_email_goes_to_the_admin_through_the_saved_account(): void
    {
        Mail::fake();
        $admin = $this->makeUser(1, ['email' => 'boss@example.com']);
        $this->actingAs($admin)->put(route('console.settings.mail'), $this->form());

        $this->actingAs($admin)->post(route('console.settings.mail.test'))->assertSessionHas('success');

        Mail::assertSent(SettingsTestMail::class, fn ($m) => $m->hasTo('boss@example.com'));
    }

    public function test_a_test_before_anything_is_saved_says_so(): void
    {
        Mail::fake();

        $this->actingAs($this->makeUser(1))->post(route('console.settings.mail.test'))->assertSessionHas('error');

        Mail::assertNothingSent();
    }

    public function test_a_mail_server_error_is_explained_without_giving_away_the_password(): void
    {
        $this->save();
        $mail = app(MailSettings::class);

        $leaky = $mail->explain('Failed to authenticate on SMTP server with username "sender@example.com" using the authenticators "LOGIN". Authenticator LOGIN returned Expected response code "235" but got code "535", with message "535 bad S3cret-Pass!".');
        $this->assertStringNotContainsString('S3cret-Pass!', $leaky);
        $this->assertStringContainsString('refused the username or password', $leaky);

        $this->assertStringContainsString('Could not connect', $mail->explain('Connection could not be established with host "smtp.example.com:587"'));
        $this->assertStringNotContainsString('S3cret-Pass!', $mail->explain('Something odd happened with S3cret-Pass! in it'));
    }

    public function test_the_settings_page_shows_the_email_section(): void
    {
        $this->actingAs($this->makeUser(1))->get(route('console.settings.edit'))->assertOk()->assertSee('Email account')->assertSee('Not set')->assertSee('Save email settings');
    }

    public function test_the_footer_credit_links_the_name_only(): void
    {
        $page = $this->get('/login')->assertOk();

        $page->assertSee('A product of', false)->assertSee('>Node Netcad Enterprise</a>', false)->assertDontSee('(nodenetcad.com)', false);
    }
}
