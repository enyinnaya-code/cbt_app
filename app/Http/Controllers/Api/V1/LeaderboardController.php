<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Leaderboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The same opt-in and boards as the website, for the app. */
class LeaderboardController extends Controller
{
    public function profile(Request $request): JsonResponse
    {
        return response()->json(Leaderboard::profile($request->user()));
    }

    /** Joins, or changes the nickname, picture or school of someone already in. Needs the privacy policy accepted. */
    public function join(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(Leaderboard::rules($user), Leaderboard::messages());

        Leaderboard::join($user, $data);

        return response()->json(Leaderboard::profile($user->fresh()));
    }

    public function leave(Request $request): JsonResponse
    {
        Leaderboard::leave($request->user());

        return response()->json(Leaderboard::profile($request->user()->fresh()));
    }

    public function board(Request $request, Leaderboard $boards): JsonResponse
    {
        $scope = $request->validate(['scope' => ['nullable', 'in:' . implode(',', Leaderboard::SCOPES)]])['scope'] ?? 'week';

        return response()->json($boards->board($scope, $request->user()));
    }
}
