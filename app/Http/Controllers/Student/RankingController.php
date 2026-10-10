<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Leaderboard;
use Illuminate\Http\Request;

class RankingController extends Controller
{
    public function index(Request $request, Leaderboard $boards)
    {
        $user = $request->user();
        $scope = in_array($request->query('scope'), Leaderboard::SCOPES, true) ? $request->query('scope') : 'week';

        return view('student.rankings', [
            'board' => $boards->board($scope, $user),
            'scope' => $scope,
            'joined' => Leaderboard::isParticipant($user),
        ]);
    }

    /** One short form: a nickname, a picture, a school if they like, and the privacy policy. */
    public function joinForm(Request $request)
    {
        return view('student.rankings-join', ['profile' => Leaderboard::profile($request->user()), 'editing' => Leaderboard::isParticipant($request->user())]);
    }

    public function join(Request $request)
    {
        $user = $request->user();
        $data = $request->validate(Leaderboard::rules($user), Leaderboard::messages());

        Leaderboard::join($user, $data);

        return redirect()->route('challenge.show')->with('success', "You are in, {$user->nickname}! Today's challenge is ready.");
    }

    public function leave(Request $request)
    {
        Leaderboard::leave($request->user());

        return redirect()->route('dashboard')->with('success', 'You have left the rankings. Your nickname, picture and school were deleted, and you no longer appear on any board.');
    }
}
