<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\Test;
use Illuminate\Http\Request;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;

class QuestionController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user(); // Get the logged-in user

        // Check if the user is a SuperAdmin (user_type 1) or Admin (user_type 2)
        if (in_array($user->user_type, [1, 2])) {
            // SuperAdmin and Admin can view all tests
            $query = Test::with(['creator', 'section', 'course', 'schoolClass', 'submittedBy']); // Add 'submittedBy' relationship
        } else {
            // Other users only see their own tests
            $query = Test::with(['creator', 'section', 'course', 'schoolClass', 'submittedBy']) // Add 'submittedBy' relationship
                ->where('created_by', $user->id);
        }

        // Apply filters
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

        if ($request->filled('filter_approval_status')) {
            switch ($request->filter_approval_status) {
                case 'not_submitted':
                    $query->where('is_submitted', 0);
                    break;
                case 'action_needed':
                    $query->where('is_submitted', 2);
                    break;
                case 'not_approved':
                    $query->where('is_submitted', 1)->where('is_approved', 0);
                    break;
                case 'approved':
                    $query->where('is_approved', 1);
                    break;
            }
        }


        $query->orderBy('created_at', 'desc');

        // Paginate the results and append filters to the pagination links
        $tests = $query->paginate(15)->appends($request->all());

        return view('add_question', compact('tests'));
    }



    public function viewQuestions(Test $test)
    {
        // Load all related questions
        $test->load('questions');

        $questions = collect($test->questions);

        // Current page from the query string
        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        // Only show 1 item per page
        $perPage = 1;
        $currentItems = $questions->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $currentItems,
            $questions->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('view_questions', compact('test', 'questions', 'paginator'));
    }



    public function setQuestions(Request $request, Test $test)
    {
        $test->load(['creator', 'section', 'course', 'schoolClass', 'questions']);

        $currentQuestion = null;
        $questionNumber = null;
        $nextQuestionId = null;

        if ($request->has('question_id')) {
            $questions = $test->questions->sortBy('id')->values();
            $currentQuestion = $questions->firstWhere('id', $request->question_id);

            if ($currentQuestion && !$currentQuestion->not_question) {
                $questionNumber = $questions
                    ->filter(fn($q) => !$q->not_question)
                    ->takeWhile(fn($q) => $q->id !== $currentQuestion->id)
                    ->count() + 1;
            }

            // Get the next question ID
            $currentIndex = $questions->search(fn($q) => $q->id == $currentQuestion->id);
            if ($currentIndex !== false && $currentIndex < $questions->count() - 1) {
                $nextQuestion = $questions[$currentIndex + 1];
                $nextQuestionId = $nextQuestion->id;
            }
        }

        return view('set_questions', compact('test', 'currentQuestion', 'questionNumber', 'nextQuestionId'));
    }




    public function store(Request $request)
    {
        $request->validate([
            'test_id' => 'required|exists:tests,id',
            'question_text' => 'nullable|string',
            'correct_option' => 'nullable|string',
            'options' => 'nullable|array',
            'mark' => 'nullable|numeric|min:1',
            'question_id' => 'nullable|exists:questions,id',
        ]);

        // Check if the associated test is submitted
        $test = Test::findOrFail($request->input('test_id'));
        if ($test->is_submitted == 1) {
            return redirect()->back()->with('error', 'Cannot add or update questions for a submitted test.');
        }

        // Check if it's an update
        if ($request->filled('question_id')) {
            $question = Question::findOrFail($request->input('question_id'));
        } else {
            $question = new Question();
        }

        $question->test_id = $request->input('test_id');
        $question->question = $request->input('question_text', null);
        $question->answer = $request->input('correct_option', null);
        $question->options = json_encode($request->input('options', []));
        $question->not_question = $request->has('is_instruction') ? true : null;
        $question->mark = $request->input('mark', null);

        $question->save();

        return redirect()->back()->with('success', $request->filled('question_id') ? 'Question updated successfully!' : 'Question saved successfully!');
    }





    public function submitForApproval(Request $request, Test $test)
    {
        $request->validate([
            'test_id' => 'required|exists:tests,id',
        ]);

        // Check if test is already submitted (is_submitted = 1)
        if ($test->is_submitted == 1) {
            return redirect()->route('questions.index')->with('error', 'Test has already been submitted for approval!');
        }

        $questions = $test->questions()
            ->where(function ($query) {
                $query->where('not_question', 0)
                    ->orWhereNull('not_question');
            })
            ->orderBy('id')
            ->get();

        // Check if test has any questions at all
        if ($questions->isEmpty()) {
            return redirect()->route('questions.index')->with('error', 'Cannot submit empty test. Please add questions before submitting for approval.');
        }

        // Validate each question
        foreach ($questions as $index => $question) {
            $questionNumber = $index + 1;

            if (empty($question->mark)) {
                return redirect()->route('questions.index')->with('error', "Question #{$questionNumber} does not have a mark attached to it.");
            }

            $options = json_decode($question->options, true);
            if (empty($options)) {
                return redirect()->route('questions.index')->with('error', "Question #{$questionNumber} does not have any options attached to it.");
            }
        }

        // Mark the test as submitted
        $test->is_submitted = 1;
        $test->submitted_by = Auth::id();
        $test->submission_date = now();
        $test->save();

        return redirect()->route('questions.index')->with('success', 'Test submitted for approval!');
    }

    public function approveTest(Test $test)
    {
        // Approve the test
        if ($test->is_submitted && !$test->is_approved) {
            $test->is_approved = 1;
            $test->save();
            return redirect()->route('questions.index')->with('success', 'Test approved successfully!');
        }

        return redirect()->route('questions.index')->with('error', 'Test cannot be approved.');
    }
}
