<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use App\Models\Section;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;
use App\Models\Course;


class UserController extends Controller
{
    public function create()
    {
        return view('add_user');
    }

    public function createTeacher()
    {
        $sections = Section::all(); // fetch section_name and id
        $courses = Course::all();   // fetch all courses

        return view('add_teacher', compact('sections', 'courses'));
    }


    public function getClassesBySections(Request $request)
    {
        $sectionIds = $request->input('section_ids', []);
        $classes = SchoolClass::whereIn('section_id', $sectionIds)->get();
        return response()->json($classes);
    }


    public function getSubjectsBySection($sectionId)
    {
        $user = Auth::user();

        if ($user->user_type == 1 || $user->user_type == 2) {
            $subjects = Course::where('section_id', $sectionId)
                ->select('id', 'course_name')
                ->get();
        } else {
            $subjects = Course::where('section_id', $sectionId)
                ->join('course_user', 'courses.id', '=', 'course_user.course_id')
                ->where('course_user.user_id', $user->id)
                ->select('courses.id', 'courses.course_name')
                ->get();
        }

        return response()->json($subjects);
    }



    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'user_type' => 'required|integer',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'user_type' => $request->user_type,
            'added_by' => Auth::id(),
        ]);

        return redirect()->route('user.add')->with('success', 'User added successfully.');
    }



    public function storeTeacher(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'section_ids' => 'array',
            'class_ids' => 'array',
            'course_ids' => 'array', // validate course_ids
        ]);

        DB::beginTransaction();

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'user_type' => 3, // teacher
                'added_by' => Auth::id(),
            ]);

            if ($request->has('section_ids')) {
                $user->sections()->attach($request->section_ids);
            }

            if ($request->has('class_ids')) {
                $user->classes()->attach($request->class_ids);
            }

            if ($request->has('course_ids')) {
                $user->courses()->attach($request->course_ids); // attach to pivot table
            }

            DB::commit();

            return redirect()->route('teacher.add')->with('success', 'Teacher added successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->route('teacher.add')->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }


    public function manageUsers(Request $request)
    {
        $currentUser = Auth::user();

        // Get filter values from request
        $filter_name = $request->input('filter_name');
        $filter_email = $request->input('filter_email');
        $filter_user_type = $request->input('filter_user_type');
        $filter_status = $request->input('filter_status');
        $filter_date_from = $request->input('filter_date_from');
        $filter_date_to = $request->input('filter_date_to');

        // Start the query - exclude current user and user_type 4
        $query = User::where('id', '!=', $currentUser->id)
            ->where('user_type', '!=', 4);

        // Admin type 2 constraint - they can't see SuperAdmins (type 1)
        if ($currentUser->user_type == 2) {
            $query->where('user_type', '!=', 1);
        }


        // Apply filters if they exist
        if ($filter_name) {
            $query->where('name', 'like', '%' . $filter_name . '%');
        }

        if ($filter_email) {
            $query->where('email', 'like', '%' . $filter_email . '%');
        }

        if ($filter_user_type !== null && $filter_user_type !== '') {
            $query->where('user_type', $filter_user_type);
        }

        if ($filter_status !== null && $filter_status !== '') {
            $query->where('is_active', $filter_status);
        }

        // Apply date range filters if provided
        if ($filter_date_from) {
            $query->whereDate('created_at', '>=', $filter_date_from);
        }

        if ($filter_date_to) {
            $query->whereDate('created_at', '<=', $filter_date_to);
        }

        // Get users with pagination (15 per page, or adjust as needed)
        $users = $query->orderBy('created_at', 'desc')->paginate(15);

        return view('manage_user', compact('users'));
    }



    public function manageTeacher(Request $request)
    {
        $currentUser = Auth::user();

        // Get filter values from request
        $filter_name = $request->input('filter_name');
        $filter_email = $request->input('filter_email');
        $filter_user_type = $request->input('filter_teacher_type'); // match form input name
        $filter_status = $request->input('filter_status');
        $filter_date_from = $request->input('filter_date_from');
        $filter_date_to = $request->input('filter_date_to');

        // Start the query - exclude current user and user_type 4
        $query = User::where('id', '!=', $currentUser->id)
            ->whereNotIn('user_type', [1, 2, 4]);  // fixed

        // Admin type 2 constraint - they can't see SuperAdmins (type 1)
        if ($currentUser->user_type == 2) {
            $query->where('user_type', '!=', 1);
        }

        // Apply filters if they exist
        if ($filter_name) {
            $query->where('name', 'like', '%' . $filter_name . '%');
        }

        if ($filter_email) {
            $query->where('email', 'like', '%' . $filter_email . '%');
        }

        if ($filter_user_type !== null && $filter_user_type !== '') {
            $query->where('user_type', $filter_user_type);
        }

        if ($filter_status !== null && $filter_status !== '') {
            $query->where('is_active', $filter_status);
        }

        if ($filter_date_from) {
            $query->whereDate('created_at', '>=', $filter_date_from);
        }

        if ($filter_date_to) {
            $query->whereDate('created_at', '<=', $filter_date_to);
        }

        $teachers = $query->with(['classes', 'courses'])->orderBy('created_at', 'desc')->paginate(15);



        return view('manage_teachers', compact('teachers'));
    }




    public function edit($id)
    {
        try {
            // Decrypt the user ID
            $decryptedId = Crypt::decrypt($id);

            // Try to find the user by the decrypted ID
            $user = User::findOrFail($decryptedId);

            // If user found, return the edit view with the user data
            return view('edit_user', compact('user'));
        } catch (\Exception $e) {
            // If there is an error (either decryption or user not found), handle it and return an error message
            return redirect()->route('users.index')->with('error', 'User not found or invalid ID.');
        }
    }



    public function editTeacher($encryptedId)
    {
        $id = Crypt::decrypt($encryptedId);

        $teacher = User::with(['sections', 'classes', 'courses'])->findOrFail($id);

        $sections = Section::all();

        $assignedSectionIds = $teacher->sections()->pluck('sections.id')->toArray();
        $assignedClassIds = $teacher->classes()->pluck('school_classes.id')->toArray();
        $assignedCourseIds = $teacher->courses()->pluck('courses.id')->toArray(); // <-- added

        return view('edit_teacher', compact(
            'teacher',
            'sections',
            'assignedSectionIds',
            'assignedClassIds',
            'assignedCourseIds' // <-- added
        ));
    }




    public function updateTeacher(Request $request, $encryptedId)
    {
        $id = Crypt::decrypt($encryptedId);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => "required|email|unique:users,email,{$id}",
            'section_ids' => 'required|array|min:1',
            'section_ids.*' => 'exists:sections,id',
            'class_ids' => 'required|array|min:1',
            'class_ids.*' => 'exists:school_classes,id',
            'course_ids' => 'nullable|array', // Optional
            'course_ids.*' => 'exists:courses,id',
        ]);

        $teacher = User::findOrFail($id);

        // Update teacher basic info
        $teacher->name = strtoupper($request->name);
        $teacher->email = $request->email;
        $teacher->save();

        // Sync relationships
        $teacher->sections()->sync($request->section_ids);
        $teacher->classes()->sync($request->class_ids);
        $teacher->courses()->sync($request->course_ids ?? []); // Sync even if empty

        return redirect()->route('teachers.edit', $encryptedId)
            ->with('success', 'Teacher updated successfully.');
    }



    public function update(Request $request, $id)
    {
        $decryptedId = Crypt::decrypt($id);
        $user = User::findOrFail($decryptedId);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $decryptedId,
            'user_type' => 'required|integer',
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'user_type' => $request->user_type,
        ]);

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function toggleActive($encryptedId)
    {
        // Decrypt the encrypted user ID
        $id = Crypt::decrypt($encryptedId);

        // Find the user by ID
        $user = User::findOrFail($id);

        // Toggle the 'is_active' status
        $user->is_active = !$user->is_active;
        $user->save();

        // Determine the new status message
        $status = $user->is_active ? 'activated' : 'deactivated';

        // Redirect back to the user index with a success message
        return redirect()->route('users.index')->with('success', "User has been {$status} successfully.");
    }



    public function toggleActiveTeacher($encryptedId)
    {
        // Decrypt the encrypted user ID
        $id = Crypt::decrypt($encryptedId);

        // Find the user by ID
        $user = User::findOrFail($id);

        // Toggle the 'is_active' status
        $user->is_active = !$user->is_active;
        $user->save();

        // Determine the new status message
        $status = $user->is_active ? 'activated' : 'deactivated';

        // Redirect back to the user index with a success message
        return redirect()->route('teachers.index')->with('success', "Teacher has been {$status} successfully.");
    }


    public function resetPassword($encryptedId)
    {
        try {
            // Decrypt the user ID
            $id = Crypt::decrypt($encryptedId);

            // Find the user by the decrypted ID
            $user = User::findOrFail($id);

            // Default password to reset to
            $defaultPassword = '123456';

            // Update the user's password
            $user->password = Hash::make($defaultPassword);
            $user->save();

            // Return success message
            return redirect()->route('users.index')->with('success', 'User password has been reset successfully.');
        } catch (\Exception $e) {
            // If decryption fails or user not found
            return redirect()->route('users.index')->with('error', 'Failed to reset password. Please try again.');
        }
    }


    public function resetPasswordTeacher($encryptedId)
    {
        try {
            // Decrypt the user ID
            $id = Crypt::decrypt($encryptedId);

            // Find the user by the decrypted ID
            $user = User::findOrFail($id);

            // Default password to reset to
            $defaultPassword = '123456';

            // Update the user's password
            $user->password = Hash::make($defaultPassword);
            $user->save();

            // Return success message
            return redirect()->route('teachers.index')->with('success', 'Teacher password has been reset successfully to 123456.');
        } catch (\Exception $e) {
            // If decryption fails or user not found
            return redirect()->route('teachers.index')->with('error', 'Failed to reset password. Please try again.');
        }
    }

    public function destroy($encryptedId)
    {
        try {
            // Decrypt the user ID
            $id = Crypt::decrypt($encryptedId);

            // Find the user by the decrypted ID
            $user = User::findOrFail($id);

            // Delete the user
            $user->delete();

            // Return success message
            return redirect()->route('users.index')->with('success', 'User deleted successfully.');
        } catch (\Exception $e) {
            // If decryption fails, user not found, or deletion fails
            return redirect()->route('users.index')->with('error', 'Failed to delete the user. Please try again.');
        }
    }

    public function destroyTeacher($encryptedId)
    {
        try {
            // Decrypt the user ID
            $id = Crypt::decrypt($encryptedId);

            // Find the user by the decrypted ID
            $user = User::findOrFail($id);

            // Delete the user
            $user->delete();

            // Return success message
            return redirect()->route('teachers.index')->with('success', 'Teacher deleted successfully.');
        } catch (\Exception $e) {
            // If decryption fails, user not found, or deletion fails
            return redirect()->route('teachers.index')->with('error', 'Failed to delete the teacher. Please try again.');
        }
    }
}
