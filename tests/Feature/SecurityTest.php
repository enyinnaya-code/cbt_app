<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

/** Things an attacker would try, and that must not work. */
class SecurityTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    private string $uploads;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uploads = sys_get_temp_dir() . '/tc-sec-' . uniqid();
        mkdir($this->uploads . '/uploads', 0777, true);
        $this->app->usePublicPath($this->uploads);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->uploads . '/uploads/*') ?: [] as $f) { @unlink($f); }
        @rmdir($this->uploads . '/uploads');
        @rmdir($this->uploads);
        parent::tearDown();
    }

    /** A real, tiny PNG so the type check sees an image. */
    private function png(string $name): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($name, $png);
    }

    public function test_a_php_file_cannot_be_uploaded_as_a_picture(): void
    {
        $examiner = $this->makeUser(3);

        foreach (['shell.php', 'shell.php.jpg', 'shell.phtml', 'shell.png.php'] as $name) {
            $evil = UploadedFile::fake()->createWithContent($name, '<?php system($_GET["c"]);');
            $this->actingAs($examiner)->postJson(route('summernote.image.upload'), ['image' => $evil])->assertStatus(422);
        }

        $this->assertSame([], glob($this->uploads . '/uploads/*') ?: []);
    }

    public function test_a_real_picture_is_saved_with_a_safe_extension_chosen_by_the_server(): void
    {
        $response = $this->actingAs($this->makeUser(3))->postJson(route('summernote.image.upload'), ['image' => $this->png('holiday.php.png')])->assertOk();

        $files = glob($this->uploads . '/uploads/*');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.png', $files[0]);
        $this->assertStringNotContainsString('.php', basename($files[0]));
        $this->assertStringContainsString('/uploads/', $response->json());
    }

    public function test_students_cannot_upload_pictures_to_the_server(): void
    {
        $this->actingAs($this->makeUser(4))->postJson(route('summernote.image.upload'), ['image' => $this->png('a.png')])->assertForbidden();
    }

    public function test_the_retired_school_screens_are_off_by_default(): void
    {
        $admin = $this->makeUser(1);

        foreach (['/manage-user', '/add-user', '/tests', '/students', '/announcements', '/available-tests', '/calendar', '/questions'] as $url) {
            $this->actingAs($admin)->get($url)->assertNotFound();
        }
    }

    public function test_every_response_carries_the_security_headers(): void
    {
        $r = $this->get('/login')->assertOk();

        $r->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Referrer-Policy');
        $this->assertStringContainsString("frame-ancestors 'none'", $r->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("object-src 'none'", $r->headers->get('Content-Security-Policy'));
    }

    public function test_signed_in_pages_are_never_cached_by_a_shared_cache(): void
    {
        $r = $this->actingAs($this->makeUser(4))->get('/dashboard')->assertOk();

        $this->assertStringContainsString('no-store', (string) $r->headers->get('Cache-Control'));
    }

    public function test_api_tokens_expire(): void
    {
        $this->assertSame(60 * 24 * 90, config('sanctum.expiration'));

        $user = $this->makeUser(4);
        $token = $user->createToken('phone')->plainTextToken;
        $this->getJson('/api/v1/me', ['Authorization' => "Bearer $token"])->assertOk();

        $this->travel(91)->days();
        $this->app['auth']->forgetGuards();   // a real second request starts with no remembered user
        $this->getJson('/api/v1/me', ['Authorization' => "Bearer $token"])->assertUnauthorized();
    }

    public function test_sign_in_does_not_reveal_which_emails_have_accounts(): void
    {
        $this->makeUser(4, ['email' => 'real@example.com', 'is_active' => 0]);

        $unknown = $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'whatever1'])->getSession()->get('error');
        $suspendedWrongPassword = $this->post('/login', ['email' => 'real@example.com', 'password' => 'wrong-password1'])->getSession()->get('error');

        $this->assertSame('Invalid email or password.', $unknown);
        $this->assertSame($unknown, $suspendedWrongPassword);
    }

    public function test_a_suspended_account_is_still_blocked_with_the_right_password(): void
    {
        $this->makeUser(4, ['email' => 'gone@example.com', 'is_active' => 0, 'password' => 'password123']);

        $this->post('/login', ['email' => 'gone@example.com', 'password' => 'password123'])->assertSessionHas('error', 'Your account is suspended.');
        $this->assertGuest();
    }

    public function test_one_address_cannot_try_endless_accounts(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->post('/login', ['email' => "victim{$i}@example.com", 'password' => 'guess-number-1']);
        }

        $this->post('/login', ['email' => 'victim99@example.com', 'password' => 'guess-number-1'])
            ->assertSessionHas('error', fn ($m) => str_starts_with($m, 'Too many attempts'));
    }

    public function test_weak_passwords_are_refused_when_signing_up(): void
    {
        foreach (['short1', 'onlyletterspassword', '1234567890123'] as $weak) {
            $this->postJson('/api/v1/auth/register', ['name' => 'A', 'email' => "a{$weak}@example.com", 'password' => $weak, 'device_name' => 'p'])
                ->assertStatus(422)->assertJsonValidationErrors('password');
        }

        $this->postJson('/api/v1/auth/register', ['name' => 'A', 'email' => 'ok@example.com', 'password' => 'Better4Password', 'device_name' => 'p'])->assertCreated();
    }

    public function test_a_signup_cannot_make_itself_an_admin(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'A', 'email' => 'sneaky@example.com', 'password' => 'Better4Password', 'device_name' => 'p',
            'role' => 'admin', 'user_type' => 1, 'is_active' => 1,
        ])->assertCreated();

        $this->assertSame('student', User::where('email', 'sneaky@example.com')->value('role'));

        $this->post('/register', [
            'name' => 'B', 'email' => 'sneaky2@example.com', 'password' => 'Better4Password', 'password_confirmation' => 'Better4Password',
            'role' => 'admin', 'user_type' => 1,
        ]);
        $this->assertSame('student', User::where('email', 'sneaky2@example.com')->value('role'));
    }
}
