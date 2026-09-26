<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\StudentStats;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $stats = new StudentStats($user);

        $hour = (int) now()->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

        return view('student.home', [
            'greeting' => $greeting,
            'firstName' => explode(' ', trim($user->name))[0],
            'streak' => $stats->streak(),
            'daysToExam' => $stats->daysToExam(),
            'targetExam' => $user->target_exam ? Exam::where('slug', $user->target_exam)->first() : null,
            'last' => $stats->lastPractised(),
            'subjects' => $stats->subjects(),
            'answered' => $stats->totalAnswered(),
        ]);
    }
}
