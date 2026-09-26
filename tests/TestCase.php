<?php

namespace Tests;

use App\Models\Setting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Settings are cached for the length of a request; the database is reset between tests, so the cache must be too.
        Setting::forget();
    }
}
