<?php

namespace App\Http\Controllers;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\Test;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Section;
use App\Models\Course;
use App\Models\SchoolClass;
use App\Models\TestSubmission;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;








class TestController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user(); // Get the logged-in user

        $query = Test::with(['creator', 'section', 'course'])
            ->where('created_by', $user->id); // Restrict to the user's tests

        if ($request->filled('filter_test_name')) {
            $query->where('test_name', 'like', '%' . $request->filter_test_name . '%');
        }

        if ($request->filled('filter_test_type')) {
            $query->where('test_type', $request->filter_test_type);
        }

        if ($request->filled('filter_duration')) {
            $query->where('duration', $request->filter_duration);
        }

        if ($request->filled('filter_section')) {
            $query->where('section_id', $request->filter_section);
        }

        if ($request->filled('filter_course')) {
            $query->where('course_id', $request->filter_course);
        }

        if ($request->filled('filter_creator')) {
            $query->whereHas('creator', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->filter_creator . '%');
            });
        }

        if ($request->filled('filter_date_from')) {
            $query->whereDate('created_at', '>=', $request->filter_date_from);
        }

        if ($request->filled('filter_date_to')) {
            $query->whereDate('created_at', '<=', $request->filter_date_to);
        }

        // Order by created_at descending
        $query->orderBy('created_at', 'desc');

        $tests = $query->paginate(15)->appends($request->all());

        return view('manage_tests', compact('tests'));
    }





    public function create()
    {
        $user = Auth::user();

        // Sections
        if ($user->user_type == 1 || $user->user_type == 2) {
            $sections = Section::select('id', 'section_name')->get();
        } else {
            $sections = Section::select('sections.id', 'sections.section_name')
                ->join('section_user', 'sections.id', '=', 'section_user.section_id')
                ->where('section_user.user_id', $user->id)
                ->get();
        }

        // Courses
        if ($user->user_type == 1 || $user->user_type == 2) {
            $courses = Course::select('id', 'course_name')->get();
        } else {
            $courses = Course::select('courses.id', 'courses.course_name')
                ->join('course_user', 'courses.id', '=', 'course_user.course_id')
                ->where('course_user.user_id', $user->id)
                ->get();
        }

        return view('create_test', compact('sections', 'courses'));
    }







    public function getClassesBySection($sectionId)
    {
        $user = Auth::user();

        // Check if user is super admin or admin (user_type 1 or 2)
        if ($user->user_type == 1 || $user->user_type == 2) {
            // Show all classes for the selected section
            $classes = SchoolClass::where('section_id', $sectionId)
                ->select('id', 'name')
                ->get();
        } else {
            // Get only the classes that belong to this section AND are associated with the user
            $classes = SchoolClass::where('school_classes.section_id', $sectionId)
                ->join('class_user', 'school_classes.id', '=', 'class_user.school_class_id')
                ->where('class_user.user_id', $user->id)
                ->select('school_classes.id', 'school_classes.name')
                ->get();
        }

        return response()->json($classes);
    }


    public function store(Request $request)
    {
        $request->validate([
            'test_name'   => 'required|string|max:255',
            'test_type'   => 'required|string',
            'duration'    => 'required|integer',
            'pass_mark'   => 'required|integer', // added validation
            'section_id'  => 'required|exists:sections,id',
            'class_id'    => 'required|exists:school_classes,id',
            'course_id'   => 'required|exists:courses,id',
        ]);

        $test = new Test();
        $test->test_name   = strtoupper($request->input('test_name'));
        $test->test_type   = $request->input('test_type');
        $test->duration    = $request->input('duration');
        $test->pass_mark   = $request->input('pass_mark'); // assign pass_mark
        $test->section_id  = $request->input('section_id');
        $test->class_id    = $request->input('class_id');
        $test->course_id   = $request->input('course_id');
        $test->created_by  = Auth::id(); // logged-in user

        $test->save();

        return redirect()->back()->with('success', 'Test created successfully.');
    }


    public function show($id)
    {
        $test = Test::with(['creator', 'section', 'course'])->findOrFail($id);
        return view('view_test', compact('test'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'test_name'   => 'required|string|max:255',
            'test_type'   => 'required|string',
            'duration'    => 'nullable|integer',
            'section_id'  => 'required|exists:sections,id',
            'class_id'    => 'required|exists:school_classes,id',
            'course_id'   => 'required|exists:courses,id',
            'pass_mark'   => 'required|integer|min:1',
        ]);

        $test = Test::findOrFail($id);

        // Prevent update if test is already submitted
        if ($test->is_submitted == 1) {
            return redirect()->route('tests.index')->with('error', 'Cannot update a submitted test.');
        }

        $test->test_name = strtoupper($request->input('test_name'));
        $test->test_type = $request->input('test_type');
        $test->duration = $request->input('duration');
        $test->section_id = $request->input('section_id');
        $test->class_id = $request->input('class_id');
        $test->course_id = $request->input('course_id');
        $test->pass_mark = $request->input('pass_mark');

        $test->save();

        return redirect()->route('tests.index')->with('success', 'Test updated successfully.');
    }


    public function edit($id)
    {
        $user = Auth::user();

        // Find the test by its ID
        $test = Test::with(['section', 'course', 'creator'])->findOrFail($id);

        // Get the sections for the dropdown
        if ($user->user_type == 1 || $user->user_type == 2) {
            // Admins get all sections
            $sections = Section::select('id', 'section_name')->get();
        } else {
            // Regular users get only their assigned sections
            $sections = Section::select('sections.id', 'sections.section_name')
                ->join('section_user', 'sections.id', '=', 'section_user.section_id')
                ->where('section_user.user_id', $user->id)
                ->get();
        }

        // Get the courses for the dropdown
        if ($user->user_type == 1 || $user->user_type == 2) {
            // Admins get all courses
            $courses = Course::select('id', 'course_name')->get();
        } else {
            // Regular users get only their assigned courses
            $courses = Course::select('courses.id', 'courses.course_name')
                ->join('course_user', 'courses.id', '=', 'course_user.course_id')
                ->where('course_user.user_id', $user->id)
                ->get();
        }

        // Return view with filtered data
        return view('edit_test', compact('test', 'sections', 'courses'));
    }



    public function destroy($id)
    {
        $test = Test::findOrFail($id); // Find the test or throw 404 if not found

        // Check if the test is submitted
        if ($test->is_submitted) {
            return redirect()->route('tests.index')->with('error', 'Cannot delete a submitted test.');
        }

        $test->delete(); // Delete the test

        return redirect()->route('tests.index')->with('success', 'Test deleted successfully.');
    }



    public function viewTests(Request $request)
    {
        $query = Test::with(['createdBy', 'submittedBy', 'approvedBy', 'schoolClass', 'course']);

        if ($request->filled('filter_test_title')) {
            $query->where('test_name', 'like', '%' . $request->filter_test_title . '%');
        }

        if ($request->filled('filter_class')) {
            $query->where('class_id', $request->filter_class);
        }

        if ($request->filled('filter_subject')) {
            $query->whereHas('course', function ($q) use ($request) {
                $q->where('course_name', 'like', '%' . $request->filter_subject . '%');
            });
        }

        if ($request->filled('filter_duration')) {
            $query->where('duration', $request->filter_duration);
        }

        if ($request->filled('filter_total_questions')) {
            $query->whereHas('questions', function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNull('not_question')->orWhere('not_question', '');
                });
            }, '=', $request->filter_total_questions);
        }

        if ($request->filled('filter_created_by')) {
            $query->whereHas('createdBy', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->filter_created_by . '%');
            });
        }

        if ($request->filled('filter_submitted_by')) {
            $query->whereHas('submittedBy', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->filter_submitted_by . '%');
            });
        }

        if ($request->filled('filter_submitted_on')) {
            $query->whereDate('submitted_at', $request->filter_submitted_on);
        }

        if ($request->filled('filter_approved_on')) {
            $query->whereDate('approved_at', $request->filter_approved_on);
        }

        if ($request->filled('filter_approved_by')) {
            $query->whereHas('approvedBy', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->filter_approved_by . '%');
            });
        }

        if ($request->filled('filter_status')) {
            switch ($request->filter_status) {
                case 'not_submitted':
                    $query->where('is_submitted', 0);
                    break;
                case 'not_approved':
                    $query->where('is_submitted', 1)->where('is_approved', 0);
                    break;
                case 'action_needed':
                    $query->where('is_submitted', 2)->where('is_approved', 0);
                    break;
                case 'approved':
                    $query->where('is_approved', 1);
                    break;
            }
        }

        // Order by submitted_at descending
        $tests = $query->orderBy('submission_date', 'desc')->paginate(20);

        foreach ($tests as $test) {
            $test->total_questions = $test->questions()
                ->where(function ($query) {
                    $query->whereNull('not_question')->orWhere('not_question', '');
                })->count();
        }

        $classes = SchoolClass::all();

        return view('view_tests', compact('tests', 'classes'));
    }



    public function editCheck($testId)
    {
        $test = Test::with('questions')->findOrFail($testId);

        // Flatten questions into a collection
        $questions = collect($test->questions);

        // Current page number from query
        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        // Paginate manually - 1 item per page
        $perPage = 1;
        $currentItems = $questions->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $currentItems,
            $questions->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('admin_check_questions', compact('test', 'paginator', 'questions'));
    }




    public function submitComment(Request $request, $test)
    {
        $request->validate([
            'comment' => 'required|string|max:2000',
        ]);

        $test = Test::findOrFail($test);
        $test->comments = $request->input('comment');

        // Reset submission status
        $test->is_submitted = 2;
        // $test->submission_date = null;

        $test->save();

        return redirect()->back()->with('success', 'Comment submitted successfully and submission not approved');
    }



    public function approveTest($testId)
    {
        $test = Test::findOrFail($testId);

        $test->is_approved = 1;
        $test->is_submitted = 1;
        $test->approved_by = Auth::id();
        $test->approval_date = Carbon::now();

        $test->save();

        return redirect()->back()->with('success', 'Test approved successfully.');
    }

    public function available(Request $request)
    {
        $user = Auth::user();

        // Base query for approved tests
        $query = Test::where('is_approved', 1);

        // Restrict by class for students (user_type 4)
        if ($user->user_type == 4) {
            $query->where('class_id', $user->class_id);
        }

        // Filters
        if ($request->filled('filter_test_name')) {
            $query->where('test_name', 'like', '%' . $request->filter_test_name . '%');
        }

        if ($request->filled('filter_class') && in_array($user->user_type, [1, 2])) {
            $query->where('class_id', $request->filter_class);
        }

        if ($request->filled('filter_schedule_from')) {
            $query->whereDate('scheduled_date', '>=', $request->filter_schedule_from);
        }

        if ($request->filled('filter_schedule_to')) {
            $query->whereDate('scheduled_date', '<=', $request->filter_schedule_to);
        }

        // Order by most recent scheduled date/time first
        $tests = $query->orderBy('scheduled_date', 'desc')
            ->paginate(10)
            ->appends($request->all());

        $classes = in_array($user->user_type, [1, 2]) ? SchoolClass::all() : [];

        return view('available_tests', compact('tests', 'classes'));
    }




   public function takeTest(Request $request, $id)
{
    $user = Auth::user();
    $test = Test::with('questions')->findOrFail($id);

    if ($user->user_type == 1 || $user->user_type == 2) {
        $user->class_id = 1;
    }

    if (is_null($user->class_id)) {
        return redirect()->back()->with('error', 'You do not have a class assigned.');
    }

    $scheduledDate = Carbon::parse($test->scheduled_date)->toDateString();
    $today = Carbon::today()->toDateString();

    if ($scheduledDate !== $today) {
        return redirect()->back()->with('error', 'This test is scheduled for another date: ' . Carbon::parse($test->scheduled_date)->format('j F Y'));
    }

    $existingExam = DB::table('students_exams')
        ->where('user_id', $user->id)
        ->where('test_id', $test->id)
        ->first();

    if (!$existingExam) {
        DB::table('students_exams')->insert([
            'user_id' => $user->id,
            'class_id' => $user->class_id,
            'test_id' => $test->id,
            'start_time' => Carbon::now(),
            'duration' => $test->duration,
        ]);
    }

    if (!$test->is_started) {
        $test->is_started = 1;
        $test->save();
    }

    $savedAnswers = TestSubmission::where('user_id', $user->id)
        ->where('test_id', $test->id)
        ->pluck('student_answer', 'question_id');

    // Get all shuffled questions for this specific user and test (no pagination)
    $questions = $this->getShuffledQuestions($test, $user->id);

    // Get the actual start time for this student's exam
    $examStartTime = $existingExam ? $existingExam->start_time : Carbon::now();

    return view('take_test', compact('test', 'questions', 'savedAnswers', 'examStartTime'));
}

/**
 * Get shuffled questions maintaining section integrity
 */
private function getShuffledQuestions($test, $userId)
{
    $cacheKey = "shuffled_questions_{$test->id}_{$userId}";

    // Check if we already have shuffled questions for this user/test combination
    if (Cache::has($cacheKey)) {
        $shuffledIds = Cache::get($cacheKey);
        // Return questions in the cached order
        return $test->questions->sortBy(function ($question) use ($shuffledIds) {
            return array_search($question->id, $shuffledIds);
        })->values();
    }

    $originalQuestions = $test->questions;
    $shuffledQuestions = collect();
    $sections = [];
    $currentSection = [];

    // Group questions into sections
    foreach ($originalQuestions as $question) {
        if ($question->not_question == 1) {
            // If we have a current section, add it to sections
            if (!empty($currentSection)) {
                $sections[] = collect($currentSection);
            }
            // Start new section with instruction
            $currentSection = [$question];
        } else {
            // Add question to current section
            $currentSection[] = $question;
        }
    }

    // Add the last section if it exists
    if (!empty($currentSection)) {
        $sections[] = collect($currentSection);
    }

    // Shuffle questions within each section (keeping instructions at the beginning)
    foreach ($sections as $section) {
        $instruction = $section->where('not_question', 1)->first();
        $questions = $section->where('not_question', 0);

        if ($instruction) {
            $shuffledQuestions->push($instruction);
        }

        // Shuffle the actual questions and add them
        $shuffledQuestions = $shuffledQuestions->merge($questions->shuffle());
    }

    // Cache the shuffled order for this user/test combination
    $shuffledIds = $shuffledQuestions->pluck('id')->toArray();
    Cache::put($cacheKey, $shuffledIds, now()->addDays(1)); // Cache for 1 day

    return $shuffledQuestions;
}




    public function forceStop($id)
    {
        // Find the test by its ID
        $test = Test::findOrFail($id);

        $test->scheduled_date = null;
        $test->scheduled_by = null;
        $test->save();

        // Delete related records
        DB::table('students_exams')->where('test_id', $id)->delete();
        DB::table('test_submissions')->where('test_id', $id)->delete();

        // Set the 'is_started' column to 0 (mark the test as not started)
        $test->is_started = 0;
        $test->save();

        // Redirect back with a success message
        return redirect()->back()->with('success', 'Test has been forcefully stopped.');
    }



    // public function submitTest(Request $request, $testId)
    // {
    //     $test = Test::with('questions')->findOrFail($testId);
    //     $submittedAnswers = $request->input('answers', []);
    //     $user = Auth::user();

    //     foreach ($test->questions as $question) {
    //         if (!$question->not_question) {
    //             $correctAnswer = strtoupper($question->answer);
    //             $studentAnswer = isset($submittedAnswers[$question->id])
    //                 ? strtoupper($submittedAnswers[$question->id])
    //                 : null;

    //             TestSubmission::create([
    //                 'user_id' => $user->id,
    //                 'class_id' => $user->class_id,
    //                 'test_id' => $test->id,
    //                 'question_id' => $question->id,
    //                 'answer' => $correctAnswer,
    //                 'student_answer' => $studentAnswer,
    //                 'submitted_at' => now(),
    //             ]);
    //         }
    //     }

    //     return redirect()->route('tests.index')
    //         ->with('success', 'Test submitted successfully!');
    // }


    // public function submitTest(Request $request, $testId)
    // {
    //     $test = Test::with('questions')->findOrFail($testId);
    //     $submittedAnswers = $request->input('answers', []);
    //     $user = Auth::user();

    //     foreach ($test->questions as $question) {
    //         if (!$question->not_question) {
    //             // Check if this question has already been submitted
    //             $alreadySubmitted = TestSubmission::where([
    //                 ['user_id', $user->id],
    //                 ['test_id', $test->id],
    //                 ['question_id', $question->id],
    //             ])->exists();

    //             if (!$alreadySubmitted) {
    //                 $correctAnswer = strtoupper($question->answer);
    //                 $studentAnswer = isset($submittedAnswers[$question->id])
    //                     ? strtoupper($submittedAnswers[$question->id])
    //                     : null;

    //                 TestSubmission::create([
    //                     'user_id' => $user->id,
    //                     'class_id' => $user->class_id ?? 1,
    //                     'test_id' => $test->id,
    //                     'question_id' => $question->id,
    //                     'answer' => $correctAnswer,
    //                     'student_answer' => $studentAnswer,
    //                     'submitted_at' => now(),
    //                 ]);
    //             }
    //         }
    //     }


    //     DB::table('students_exams')
    //         ->where('user_id', $user->id)
    //         ->where('test_id', $test->id)
    //         ->update(['is_submited' => 1]);

    //     return redirect()->route('tests.start')
    //         ->with('success', 'Test submitted successfully!');
    // }

   public function submitTest(Request $request, $testId)
{
    $test = Test::with('questions')->findOrFail($testId);
    $submittedAnswers = $request->input('answers', []);
    $user = Auth::user();
    
    // Ensure exhausted_time is properly cast to integer
    $exhaustedTime = (int) $request->input('exhausted_time', 0);
    
    // Validate exhausted_time to prevent negative values or unreasonable times
    if ($exhaustedTime < 0) {
        $exhaustedTime = 0;
    }
    
    // Optional: Cap the exhausted time to the test duration + buffer
    $maxAllowedTime = ($test->duration * 60) + 300; // Test duration + 5 minutes buffer
    if ($exhaustedTime > $maxAllowedTime) {
        $exhaustedTime = $maxAllowedTime;
    }

    $studentScore = 0;
    $totalScore = 0;

    foreach ($test->questions as $question) {
        if (!$question->not_question) {
            $alreadySubmitted = TestSubmission::where([
                ['user_id', $user->id],
                ['test_id', $test->id],
                ['question_id', $question->id],
            ])->exists();

            $correctAnswer = strtoupper($question->answer);
            $studentAnswer = isset($submittedAnswers[$question->id])
                ? strtoupper($submittedAnswers[$question->id])
                : null;

            $totalScore += $question->mark;

            if (!$alreadySubmitted) {
                TestSubmission::create([
                    'user_id' => $user->id,
                    'class_id' => $user->class_id ?? 1,
                    'test_id' => $test->id,
                    'question_id' => $question->id,
                    'answer' => $correctAnswer,
                    'student_answer' => $studentAnswer,
                    'submitted_at' => now(),
                ]);
            }

            if ($studentAnswer && $studentAnswer === $correctAnswer) {
                $studentScore += $question->mark;
            }
        }
    }

    // Compare with pass mark
    $isPassed = $studentScore >= $test->pass_mark ? 1 : 0;

    // Get the existing exam record to find the start_time
    $examRecord = DB::table('students_exams')
        ->where('user_id', $user->id)
        ->where('test_id', $test->id)
        ->first();

    // Calculate end time and exhausted time more safely
    $endTime = Carbon::now();
    $formattedExhaustedTime = null;
    
    try {
        if ($examRecord && $examRecord->start_time) {
            // Create a Carbon instance from the start_time
            $startTime = Carbon::parse($examRecord->start_time);
            
            // Ensure we have a valid integer for addSeconds
            if (is_numeric($exhaustedTime) && $exhaustedTime >= 0) {
                $formattedExhaustedTime = $startTime->copy()->addSeconds($exhaustedTime);
            } else {
                // Fallback: calculate based on actual time difference
                $formattedExhaustedTime = $endTime;
            }
        } else {
            // Fallback if no start_time is available
            $formattedExhaustedTime = $endTime;
        }
    } catch (\Exception $e) {
        // Error handling: log the error and use current time as fallback
        // Log::error('Error calculating exhausted time: ' . $e->getMessage(), [
        //     'user_id' => $user->id,
        //     'test_id' => $test->id,
        //     'exhausted_time' => $exhaustedTime,
        //     'start_time' => $examRecord->start_time ?? null
        // ]);
        
        $formattedExhaustedTime = $endTime;
    }

    // Update student exam record
    DB::table('students_exams')
        ->where('user_id', $user->id)
        ->where('test_id', $test->id)
        ->update([
            'is_submited' => 1,
            'end_time' => $endTime,
            'exhausted_time' => $formattedExhaustedTime,
            'score' => $studentScore,
            'test_total_score' => $totalScore,
            'is_passed' => $isPassed,
            'updated_at' => now(),
        ]);

    // Clear any saved time in localStorage
    return redirect()->route('tests.start')
        ->with('success', 'Test submitted successfully!')
        ->with('clearTestStorage', true);
}


    public function saveAnswer(Request $request)
    {
        $user = Auth::user();
        $testId = $request->input('test_id');
        $questionId = $request->input('question_id');
        $studentAnswer = strtoupper($request->input('answer'));

        $test = Test::findOrFail($testId);
        $question = $test->questions->where('id', $questionId)->first();

        if (!$question || $question->not_question) {
            return response()->json(['status' => 'ignored']);
        }

        $correctAnswer = strtoupper($question->answer);

        // Default to class_id = 1 if user's class_id is empty
        $classId = $user->class_id ?: 1;

        // Update or Create submission record
        TestSubmission::updateOrCreate(
            [
                'user_id' => $user->id,
                'test_id' => $testId,
                'question_id' => $questionId,
            ],
            [
                'class_id' => $classId,
                'answer' => $correctAnswer,
                'student_answer' => $studentAnswer,
                'submitted_at' => now(),
            ]
        );

        return response()->json(['status' => 'saved']);
    }


    public function schedule(Request $request)
    {
        $query = Test::with(['schoolClass', 'course'])
            ->where('is_approved', 1)
            ->where('is_submitted', 1); // Add this line

        if ($request->filled('filter_test_name')) {
            $query->where('test_name', 'like', '%' . $request->filter_test_name . '%');
        }
        if ($request->filled('filter_class')) {
            $query->where('class_id', $request->filter_class);
        }
        if ($request->filled('filter_duration')) {
            $query->where('duration', $request->filter_duration);
        }
        if ($request->filled('filter_scheduled_date')) {
            $query->whereDate('scheduled_date', $request->filter_scheduled_date);
        }
        if ($request->filled('filter_subject')) {
            $query->whereHas('course', function ($q) use ($request) {
                $q->where('course_name', 'like', '%' . $request->filter_subject . '%');
            });
        }
        if ($request->filled('filter_schedule_status')) {
            if ($request->filter_schedule_status === 'scheduled') {
                $query->whereNotNull('scheduled_date');
            } elseif ($request->filter_schedule_status === 'not_scheduled') {
                $query->whereNull('scheduled_date');
            }
        }

        $tests = $query->orderByDesc('created_at')->paginate(10);
        $classes = SchoolClass::orderBy('name')->get();

        return view('schedule_test', compact('tests', 'classes'));
    }



    public function saveSchedule(Request $request, $id)
    {
        $validated = $request->validate([
            'scheduled_date' => 'required|date',
        ]);

        $test = Test::findOrFail($id);
        $test->scheduled_date = $validated['scheduled_date'];
        $test->scheduled_by = Auth::id();

        $test->save();

        return redirect()->route('tests.schedule')->with('success', 'Test scheduled successfully!');
    }



    public function calendarEvents()
    {
        $tests = Test::with(['schoolClass'])->whereNotNull('scheduled_date')->get();

        $events = $tests->map(function ($test) {
            return [
                'title' => $test->test_name . ' - ' . ($test->schoolClass->name ?? 'N/A'),
                'start' => $test->scheduled_date,
                'description' => "Class: " . ($test->schoolClass->name ?? 'N/A') .
                    "\nTime: " . \Carbon\Carbon::parse($test->scheduled_date)->format('g:i A') .
                    "\nDuration: " . $test->duration . " minutes",
            ];
        });

        return response()->json($events);
    }

    public function cancelSchedule($id)
    {
        $test = Test::findOrFail($id);
        $test->scheduled_date = null;
        $test->scheduled_by = null;
        $test->save();

        return redirect()->route('tests.schedule')->with('success', 'Test schedule canceled.');
    }



    public function startTest()
    {
        $user = Auth::user();
        $today = Carbon::now('Africa/Lagos')->toDateString(); // 'Y-m-d' format (e.g., '2025-05-18')

        // Fetch today's tests and order by time (earliest first)
        if ($user->user_type == 1 || $user->user_type == 2) {
            // SuperAdmin/Admin: All approved tests scheduled for today
            $tests = Test::where('is_approved', 1)
                ->whereNotNull('scheduled_date')
                ->whereDate('scheduled_date', $today)
                ->orderBy('scheduled_date', 'asc') // Closest to current time first
                ->get();
        } else {
            // Regular user: Tests for their class scheduled for today
            if (!$user->class_id) {
                $tests = collect(); // Empty collection if user has no class assigned
            } else {
                $tests = Test::where('class_id', $user->class_id)
                    ->where('is_approved', 1)
                    ->whereNotNull('scheduled_date')
                    ->whereDate('scheduled_date', $today)
                    ->orderBy('scheduled_date', 'asc') // Changed to asc for consistency
                    ->get();
            }
        }

        // Get all student exam data (including times) in one query to improve performance
        $studentExams = DB::table('students_exams')
            ->where('user_id', $user->id)
            ->get();

        // Get test IDs already taken by the student
        $takenTestIds = $studentExams->pluck('test_id')->toArray();

        // Get scores and total scores for each test
        $studentScores = $studentExams->pluck('score', 'test_id');
        $testTotalScores = $studentExams->pluck('test_total_score', 'test_id');
        $isPassedFlags = $studentExams->pluck('is_passed', 'test_id');

        // Get start and end times for each test
        $testStartTimes = $studentExams->pluck('start_time', 'test_id');
        $testEndTimes = $studentExams->pluck('end_time', 'test_id');


        return view('start_test', compact(
            'tests',
            'takenTestIds',
            'studentScores',
            'testTotalScores',
            'isPassedFlags',
            'testStartTimes',
            'testEndTimes'
        ));
    }

    public function past()
    {
        $user = Auth::user();

        $submittedTestIds = DB::table('students_exams')
            ->where('user_id', $user->id)
            ->where('is_submited', 1)
            ->pluck('test_id');

        $tests = Test::with('schoolClass')
            ->whereIn('id', $submittedTestIds)
            ->orderBy('scheduled_date', 'desc')
            ->paginate(15);

        // Collect detailed exam data keyed by test_id
        $studentTestData = DB::table('students_exams')
            ->where('user_id', $user->id)
            ->whereIn('test_id', $submittedTestIds)
            ->get()
            ->keyBy('test_id');

        return view('past_test', compact('tests', 'studentTestData'));
    }


    public function viewPast($testId)
    {
        $user = Auth::user();

        $test = Test::with('questions')->findOrFail($testId);

        // Fetch student's answers (this is the correct logic to map question_id => selected_option)
        $studentAnswers = DB::table('test_submissions')
            ->where('user_id', $user->id)
            ->where('test_id', $test->id)
            ->pluck('student_answer', 'question_id');

        return view('view_past_questions', compact('test', 'studentAnswers'));
    }


    public function studentAnalytics()
    {
        $user = Auth::user();

        // Paginate student test analytics 3 per page
        $tests = DB::table('students_exams')
            ->join('tests', 'tests.id', '=', 'students_exams.test_id')
            ->select(
                'students_exams.test_id',
                'tests.test_name',
                'students_exams.score',
                'students_exams.test_total_score',
                'students_exams.created_at'
            )
            ->where('students_exams.user_id', $user->id)
            ->orderBy('students_exams.created_at', 'desc')
            ->paginate(3); // paginate by 3 tests per page

        return view('student_analytics', compact('tests'));
    }
}
