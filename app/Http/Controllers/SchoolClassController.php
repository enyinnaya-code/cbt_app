<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;

class SchoolClassController extends Controller
{
    // Display a list of school classes
    public function index(Request $request)
    {
        $query = SchoolClass::query();
        
        // Filter by class name
        if ($request->has('filter_name') && $request->filter_name != '') {
            $query->where('name', 'like', '%' . $request->filter_name . '%');
        }
        
        // Filter by date range
        if ($request->has('filter_date_from') && $request->filter_date_from != '') {
            $query->whereDate('created_at', '>=', $request->filter_date_from);
        }
        if ($request->has('filter_date_to') && $request->filter_date_to != '') {
            $query->whereDate('created_at', '<=', $request->filter_date_to);
        }
        
        // Order results by name (alphabetically)
        $query->orderBy('name', 'asc');
        
        // Paginate results
        $schoolClasses = $query->paginate(10);
        
        return view('manage_class', compact('schoolClasses'));
    }
    
    // Show form to add a new school class
    public function create()
    {
        $sections = Section::all(); // Get all sections
        return view('add_class', compact('sections'));
    }
    
    // Store a new school class
    public function store(Request $request) {
        // Check if class name already exists
        $existingClass = SchoolClass::where('name', $request->name)->first();
        if ($existingClass) {
            return redirect()->route('schoolClass.add')
                ->with('error', 'Class name already exists!')
                ->withInput();
        }
        
        // Validate the request
        $request->validate([
            'name' => 'required|string|max:255|unique:school_classes,name',
            'section_id' => 'required|exists:sections,id',
        ]);
        
        SchoolClass::create([
            'name' => $request->name,
            'section_id' => $request->section_id,
            'added_by' => Auth::id(),  // Store the ID of the authenticated user
        ]);
        
        return redirect()->route('schoolClass.add')->with('success', 'School class added successfully!');
    }
    
    public function edit($id)
    {
        $id = Crypt::decrypt($id);
        $schoolClass = SchoolClass::findOrFail($id);
        $sections = Section::all();
        return view('edit_class', compact('schoolClass', 'sections'));
    }
    
    public function destroy($id)
    {
        $id = Crypt::decrypt($id);
        $schoolClass = SchoolClass::findOrFail($id);
        $schoolClass->delete();
        return redirect()->route('schoolClass.manage')->with('success', 'Class deleted successfully.');
    }
    
    public function update(Request $request, $id)
    {
        $id = Crypt::decrypt($id);
        $schoolClass = SchoolClass::findOrFail($id);
        
        // Check if the updated name already exists in another class
        $existingClass = SchoolClass::where('name', $request->name)
            ->where('id', '!=', $id)
            ->first();
            
        if ($existingClass) {
            return redirect()->route('schoolClass.edit', Crypt::encrypt($id))
                ->with('error', 'Class name already exists!')
                ->withInput();
        }
        
        // Validate the request
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('school_classes')->ignore($id)
            ],
            'section_id' => 'required|exists:sections,id',
        ]);
        
        $schoolClass->update([
            'name' => $request->name,
            'section_id' => $request->section_id,
        ]);
        
        return redirect()->route('schoolClass.manage')->with('success', 'Class updated successfully!');
    }
}