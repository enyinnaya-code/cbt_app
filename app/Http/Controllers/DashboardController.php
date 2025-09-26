<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Test;
use App\Models\User;
use App\Models\StudentsExam;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\SchoolClass;




class DashboardController extends Controller
{
    //


    public function index()
    {
        $totalTests = Test::count();
        $submittedTests = Test::where('is_submitted', 1)->count();
        $approvedTests = Test::where('is_approved', 1)->count();
        $notSubmittedTests = Test::where('is_submitted', 0)->count();
        $testsTaken = Test::where('is_started', 1)->count();
        $submittedNotApprovedTests = Test::where('is_submitted', 1)
            ->where('is_approved', 0)
            ->count();
        $scheduledTests = Test::whereNotNull('scheduled_date')
            ->where('scheduled_date', '!=', '')
            ->count();

        $approvedNotScheduledTests = Test::where('is_approved', 1)
            ->where(function ($query) {
                $query->whereNull('scheduled_date')
                    ->orWhere('scheduled_date', '');
            })
            ->count();

        $passedTests = StudentsExam::where('user_id', Auth::id())
            ->where('is_passed', 1)
            ->count();

        $totalSubmissions = StudentsExam::where('user_id', Auth::id())
            ->where('is_submited', 1)
            ->count(); // Total submitted tests

        $passedCount = StudentsExam::where('user_id', Auth::id())
            ->where('is_submited', 1)
            ->where('is_passed', 1)
            ->count(); // Only those that are submitted and passed

        $failedCount = $totalSubmissions - $passedCount; // Remaining are failed

        $passPercentage = $totalSubmissions > 0 ? round(($passedCount / $totalSubmissions) * 100, 1) : 0;
        $failPercentage = $totalSubmissions > 0 ? round(($failedCount / $totalSubmissions) * 100, 1) : 0;




        $totalStudents = User::where('user_type', 4)->count();
        $totalTeachers = User::where('user_type', 3)->count();
        $totalAdmins = User::where('user_type', 2)->count();
        $suspendedUsers = User::where('is_active', 0)->count();

        $studentTestsTaken = 0;
        $availableTests = 0;

        if (Auth::check() && Auth::user()->user_type == 4) {
            $studentClassId = Auth::user()->class_id;

            $studentTestsTaken = StudentsExam::where('user_id', Auth::id())->count();

            $availableTests = Test::where('is_started', 0)
                ->where('class_id', $studentClassId)
                ->count();
        }

        $lastFiveExams = StudentsExam::with('test')
            ->where('user_id', Auth::id())
            ->where('is_submited', 1)
            ->orderBy('id', 'desc')
            ->take(5)
            ->get()
            ->reverse(); // display oldest to newest

        $examLabels = [];
        $scores = [];

        foreach ($lastFiveExams as $exam) {
            $examLabels[] = $exam->test->test_name ?? 'Test';
            $scores[] = $exam->test_total_score ?? 0; // use correct column name
        }

        $failedTestsCount = DB::table('students_exams')
            ->where('user_id', Auth::id())
            ->where('is_submited', 1)
            ->where('is_passed', 0)
            ->count();



        if (Auth::check() && Auth::user()->user_type == 4) {
            // Step 1: Get last 5 submitted exam records for this user
            $latestExamRecords = DB::table('students_exams')
                ->where('user_id', Auth::id())
                ->where('is_submited', 1)
                ->orderByDesc('id')
                ->take(5)
                ->get();

            // Step 2: Extract test IDs and keep their order
            $submittedTestIds = $latestExamRecords->pluck('test_id')->toArray();


            $tests = Test::with('schoolClass')
                ->whereIn('id', $submittedTestIds)
                ->get()
                ->sortBy(function ($test) use ($submittedTestIds) {
                    return array_search($test->id, $submittedTestIds);
                });

            // Step 4: Key student exam data by test_id
            $studentTestData = $latestExamRecords->keyBy('test_id');
        } else {
            $tests = collect();
            $studentTestData = collect();
        }

        $submittedCount = DB::table('students_exams')
            ->where('user_id', Auth::id())
            ->where('is_submited', 1)
            ->count();

        $myCreatedTests = Test::where('created_by', Auth::id())->count();
        $mySubmittedTests = Test::where('created_by', Auth::id())
            ->where('is_submitted', 1)
            ->count();
        $myApprovedTests = Test::where('created_by', Auth::id())
            ->where('is_approved', 1)
            ->count();
        $myNotSubmittedTests = Test::where('created_by', Auth::id())
            ->where('is_submitted', 0)
            ->count();



        $recentClassTests = DB::table('students_exams as se')
            ->join('tests as t', 'se.test_id', '=', 't.id')
            ->join('school_classes as sc', 't.class_id', '=', 'sc.id')
            ->where('se.is_submited', 1)
            ->select(
                'se.test_id',
                't.test_name',
                'sc.name',
                DB::raw("SUM(CASE WHEN se.is_passed = 1 THEN 1 ELSE 0 END) as pass_count"),
                DB::raw("SUM(CASE WHEN se.is_passed = 0 THEN 1 ELSE 0 END) as fail_count"),
                DB::raw("MAX(se.id) as latest_submission_id") // for getting recent
            )
            ->groupBy('se.test_id', 't.test_name', 'sc.name')
            ->orderByDesc('latest_submission_id')
            ->limit(5)
            ->get();

        $testLabels = [];
        $passCounts = [];
        $failCounts = [];

        foreach ($recentClassTests as $test) {
            $testLabels[] = "{$test->test_name} ({$test->name})";
            $passCounts[] = (int) $test->pass_count;
            $failCounts[] = (int) $test->fail_count;
        }


        return view('dashboard', compact(
            'myNotSubmittedTests',
            'myCreatedTests',
            'myApprovedTests',
            'mySubmittedTests',
            'submittedCount',
            'failedTestsCount',
            'examLabels',
            'scores',
            'totalTests',
            'submittedTests',
            'approvedTests',
            'notSubmittedTests',
            'testsTaken',
            'submittedNotApprovedTests',
            'scheduledTests',
            'approvedNotScheduledTests',
            'totalStudents',
            'totalTeachers',
            'totalAdmins',
            'suspendedUsers',
            'studentTestsTaken',
            'availableTests',
            'passedTests',
            'passPercentage',
            'failPercentage',
            'totalSubmissions',
            'tests', // for table
            'studentTestData',
            'testLabels',
            'passCounts',
            'failCounts'
        ));
    }
    
}
