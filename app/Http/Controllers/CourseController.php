<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CourseController extends Controller
{
    public function create()
    {
        $sections = Section::all();
        return view('create_course', compact('sections'));
    }



    public function store(Request $request)
    {
        $courseName = strtoupper($request->input('course_name'));
        $request->merge(['course_name' => $courseName]);

        $validator = Validator::make($request->all(), [
            'course_name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($request) {
                    $exists = Course::where('course_name', strtoupper($value))
                        ->where('section_id', $request->section_id)
                        ->exists();
                    if ($exists) {
                        $fail('The course already exists for the selected section.');
                    }
                }
            ],
            'section_id' => 'required|exists:sections,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        Course::create([
            'course_name' => $courseName,
            'section_id' => $request->section_id,
            'added_by' => Auth::id(),
        ]);

        return redirect()->route('course.create')->with('success', 'Course added successfully!');
    }


    public function index(Request $request)
    {
        $query = Course::with('section');

        // Filter by course name
        if ($request->filled('filter_name')) {
            $query->where('course_name', 'like', '%' . $request->filter_name . '%');
        }

        // Filter by date range
        if ($request->filled('filter_date_from')) {
            $query->whereDate('created_at', '>=', $request->filter_date_from);
        }

        if ($request->filled('filter_date_to')) {
            $query->whereDate('created_at', '<=', $request->filter_date_to);
        }


        if ($request->filled('filter_section')) {
            $query->where('section_id', $request->filter_section);
        }

        $courses = $query->paginate(10);


        $sections = Section::all();

        return view('manage_course', compact('courses', 'sections'));
    }


    public function edit($id)
    {
        $course = Course::findOrFail($id);
        $sections = Section::all();
        return view('edit_course', compact('course', 'sections'));
    }

    public function update(Request $request, $id)
    {
        $course = Course::findOrFail($id);

        $request->validate([
            'course_name' => 'required|string|max:255|unique:courses,course_name,' . $id,
            'section_id' => 'required|exists:sections,id',
        ]);

        $course->update([
            'course_name' => $request->course_name,
            'section_id' => $request->section_id,
        ]);

        return redirect()->route('course.manage')->with('success', 'Course updated successfully!');
    }


    public function destroy($id)
    {
        $course = Course::findOrFail($id);
        $course->delete();

        return redirect()->route('course.manage')->with('success', 'Course deleted successfully!');
    }
}
