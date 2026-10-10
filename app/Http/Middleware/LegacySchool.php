<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The old school CBT screens (classes, sections, teachers, school tests) are retired and nothing links to them. They are
 * switched off unless LEGACY_SCHOOL=true, so a public app does not carry old code that nobody maintains: that code
 * prints stored HTML unescaped and was written for a trusted school, not for the open internet.
 */
class LegacySchool
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('testacbt.legacy_school'), 404);

        return $next($request);
    }
}
