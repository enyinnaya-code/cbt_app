<?php

namespace Tests\Feature\Api;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The app asks which version is newest, to know when to show "update available". */
class AppVersionTest extends TestCase
{
    use RefreshDatabase;

    private array $dirs = [];

    /** An empty public folder, so the real release build in public/downloads never leaks into a test. */
    private function emptyPublic(): string
    {
        $dir = sys_get_temp_dir() . '/tc-av-' . uniqid();
        mkdir($dir . '/downloads', 0777, true);
        $this->app->usePublicPath($dir);

        return $this->dirs[] = $dir;
    }

    protected function tearDown(): void
    {
        foreach ($this->dirs as $dir) {
            foreach (glob($dir . '/downloads/*') ?: [] as $f) { @unlink($f); }
            @rmdir($dir . '/downloads');
            @rmdir($dir);
        }
        parent::tearDown();
    }

    public function test_it_needs_no_sign_in_and_says_nothing_is_published_yet(): void
    {
        $this->emptyPublic();

        $this->getJson('/api/v1/app-version')->assertOk()->assertJson(['latest' => null])->assertJsonStructure(['latest', 'update_url']);
    }

    public function test_it_reports_the_version_of_the_release_build_on_the_server(): void
    {
        $dir = $this->emptyPublic();
        file_put_contents($dir . '/downloads/TestaCBT.apk', 'PK pretend app');
        file_put_contents($dir . '/downloads/TestaCBT.json', json_encode(['version' => '1.1.0', 'built_at' => now()->toIso8601String(), 'size' => 14]));

        $this->getJson('/api/v1/app-version')->assertOk()->assertJson(['latest' => '1.1.0', 'update_url' => url('/download')]);
    }

    public function test_an_uploaded_app_with_its_own_version_wins_when_it_is_newer(): void
    {
        $dir = $this->emptyPublic();
        file_put_contents($dir . '/downloads/TestaCBT.apk', 'PK old');
        file_put_contents($dir . '/downloads/TestaCBT.json', json_encode(['version' => '1.0.0', 'built_at' => '2026-01-01T00:00:00Z']));
        touch($dir . '/downloads/TestaCBT.apk', time() - 3600);
        file_put_contents($dir . '/downloads/TestaCBT-upload.apk', 'PK newer');
        Setting::put(['app.apk_version' => '1.2.0']);

        $this->getJson('/api/v1/app-version')->assertJson(['latest' => '1.2.0']);
    }

    public function test_google_play_is_the_update_link_when_the_app_is_listed_there(): void
    {
        $this->emptyPublic();
        Setting::put(['app.play_store_url' => 'https://play.google.com/store/apps/details?id=com.testacbt.app']);

        $this->getJson('/api/v1/app-version')->assertJson(['update_url' => 'https://play.google.com/store/apps/details?id=com.testacbt.app']);
    }

    public function test_it_exposes_nothing_private(): void
    {
        $this->emptyPublic();

        $this->assertSame(['latest', 'update_url'], array_keys($this->getJson('/api/v1/app-version')->json()));
    }

    public function test_it_is_limited(): void
    {
        $this->emptyPublic();
        for ($i = 0; $i < 30; $i++) { $this->getJson('/api/v1/app-version')->assertOk(); }

        $this->getJson('/api/v1/app-version')->assertStatus(429);
    }
}
