<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Site\HomeController;
use Illuminate\Http\JsonResponse;

/**
 * Tells the app which version is the newest one on offer, so a phone with an older build can show an "update available"
 * banner. Public on purpose: it holds nothing private, and it must work before anyone signs in.
 */
class AppVersionController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $stores = HomeController::stores();

        return response()->json([
            // Null when no app file or version has been published yet: the app then shows nothing.
            'latest' => $stores['apk_version'] ?: null,
            // Google Play when the app is listed there; otherwise our own download link, which sends Android phones to the file.
            'update_url' => $stores['android'] ?: url('/download'),
        ])->header('Cache-Control', 'public, max-age=300');
    }
}
