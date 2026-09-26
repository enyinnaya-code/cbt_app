<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\HandoffController;
use App\Http\Controllers\Api\V1\PackController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Webhooks\PaystackController;
use Illuminate\Support\Facades\Route;

// Paystack tells us about payments here (https://testacbt.com/api/webhooks/paystack). Verified by signature, not by sign-in.
Route::post('/webhooks/paystack', PaystackController::class);

// Mobile app API. Base URL: /api/v1
Route::prefix('v1')->group(function () {

    Route::get('/ping', fn () => response()->json(['ok' => true, 'time' => now()->toIso8601String()]));

    Route::middleware('throttle:10,1')->prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/google', [AuthController::class, 'google']);
    });

    Route::middleware(['auth:sanctum', 'active', 'throttle:120,1'])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/catalog', [CatalogController::class, 'index']);
        Route::post('/web-link', [HandoffController::class, 'link'])->middleware('throttle:10,1');
        Route::get('/packs/{exam}/{subject}', [PackController::class, 'show']);

        Route::post('/sync/progress', [SyncController::class, 'push']);
        Route::get('/sync/progress', [SyncController::class, 'pull']);
    });
});
