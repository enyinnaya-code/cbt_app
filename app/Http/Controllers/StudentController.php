<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class StudentController extends Controller
{
   public function create()
{
    $sections = Section::all(); // Assuming your model is named Section
    return view('add_student', compact('sections'));
}


public function getClasses($section_id)
{
    $classes = SchoolClass::where('section_id', $section_id)->get();
    return response()->json($classes);
}



   public function index()
{
    // Restrict access
    if (!in_array(Auth::user()->user_type, [1, 2, 3])) {
        abort(403, 'Unauthorized access.');
    }

    // Build the query ordered by id desc
    $query = User::where('user_type', 4)->with('class')->orderBy('id', 'desc');

    // Apply filters
    if ($filterName = request('filter_name')) {
        $query->where('name', 'like', "%$filterName%");
    }

    if ($filterClass = request('filter_class')) {
        $query->where('class_id', $filterClass);
    }

    if ($filterGender = request('filter_gender')) {
        $query->where('gender', $filterGender);
    }

    if ($filterDateAdded = request('filter_date_added')) {
        $query->whereDate('created_at', '=', $filterDateAdded);
    }

    // Paginate results
    $students = $query->paginate(20);

    // Get all classes to populate class filter dropdown
    $classes = SchoolClass::all();

    return view('manage_students', compact('students', 'classes'));
}



    public function suspend(User $student)
    {
        $student->is_active = 0;
        $student->save();

        return redirect()->route('students.index')->with('success', 'Student suspended.');
    }


    public function activate(User $student)
    {
        $student->is_active = 1;
        $student->save();

        return redirect()->route('students.index')->with('success', 'Student activated.');
    }

    public function resetPassword(User $student)
    {
        $student->password = bcrypt('123456');
        $student->save();

        return redirect()->route('students.index')->with('success', 'Password reset to 123456.');
    }


    public function destroy(User $student)
    {
        if ($student->is_active) {
            return redirect()->route('students.index')->with('error', 'Active students cannot be deleted. Please suspend them first.');
        }

        $student->delete();

        return redirect()->route('students.index')->with('success', 'Student deleted successfully.');
    }



    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'class_id' => 'required|exists:school_classes,id',
            'gender' => 'required|string|max:15',
        ]);

        User::create([
            'name' => strtoupper($validated['student_name']),
            'email' => $validated['email'],
            'class_id' => $validated['class_id'],
            'password' => bcrypt('123456'),
            'user_type' => 4, // Student
            'gender' => $validated['gender'],
            'added_by' => Auth::id(), // ID of the currently logged-in user
        ]);

        return redirect()->route('students.create')->with('success', 'Student added successfully.');
    }

   public function edit($id)
{
    // Restrict access
    if (!in_array(Auth::user()->user_type, [1, 2, 3])) {
        abort(403, 'Unauthorized access.');
    }

    $student = User::where('user_type', 4)->findOrFail($id);
    $classes = SchoolClass::all();
    $sections = Section::all(); // Make sure to fetch sections

    // Get section_id via class
    $studentClass = SchoolClass::find($student->class_id);
    $studentSectionId = $studentClass ? $studentClass->section_id : null;

    return view('edit_student', compact('student', 'classes', 'sections', 'studentSectionId'));
}



    public function update(Request $request, $id)
    {
        // Restrict access to user_type 1, 2, 3
        if (!in_array(Auth::user()->user_type, [1, 2, 3])) {
            abort(403, 'Unauthorized access.');
        }

        $student = User::where('user_type', 4)->findOrFail($id);

        // Validate input
        $validated = $request->validate([
            'student_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $student->id,
            'gender' => 'required|in:Male,Female',
            'class_id' => 'required|exists:school_classes,id',
        ]);

        // Update student
        $student->update([
            'name' => strtoupper($validated['student_name']),
            'email' => $validated['email'],
            'gender' => $validated['gender'],
            'class_id' => $validated['class_id'],
        ]);

        return redirect()->route('students.index')->with('success', 'Student updated successfully.');
    }
}
