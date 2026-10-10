<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use Illuminate\Http\JsonResponse;

/** The announcements that should show at the top of Home in the app right now. */
class NoticesController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['notices' => Notice::forStudents('app')->map->toApp()->values()]);
    }
}
