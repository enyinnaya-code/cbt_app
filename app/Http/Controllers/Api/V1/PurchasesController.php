<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Purchases;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchasesController extends Controller
{
    /** The signed-in student's unlocked subjects and payments. Nobody else's. */
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json((new Purchases($request->user()))->toArray());
    }
}
