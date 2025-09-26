<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class SectionController extends Controller
{
    // Show form to add a new section
    public function create()
    {
        return view('add_section'); // Make sure you have this view
    }

    // Store the new section in the database
    public function store(Request $request)
    {
        // Validate the input
        $request->validate([
            'section_name' => 'required|string|max:255',
        ]);

        // Create a new section
        Section::create([
            'section_name' => $request->section_name,
            'created_by' => Auth::id(),

        ]);

        // Redirect to a success page or back to the manage page with a success message
        return redirect()->route('section.create')->with('success', 'Section added successfully!');
    }

    // Manage existing sections
    public function index()
    {
        $sections = Section::all(); // Fetch all sections
        return view('manage_section', compact('sections')); // Make sure you have this view
    }

    public function showSections()
    {
        $sections = Section::all(); // Fetch all sections
        return view('add_section', compact('sections')); // Pass sections to the view
    }

    public function edit($id)
    {
        // Fetch the section by ID
        $section = Section::findOrFail($id);

        // Pass section data to the edit view
        return view('edit_section', compact('section'));
    }


    public function update(Request $request, $id)
    {
        // Validate the request
        $validated = $request->validate([
            'section_name' => 'required|string|max:255',
        ]);

        // Find the section
        $section = Section::findOrFail($id);

        // Update the section
        $section->section_name = $validated['section_name'];
        $section->save();

        // Redirect with success message
        return redirect()->route('section.index')->with('success', 'Section updated successfully');
    }


    // Delete the section
    public function destroy($id)
    {
        // Find the section and delete it
        $section = Section::findOrFail($id);
        $section->delete();

        // Redirect back with a success message
        return redirect()->route('section.index')->with('success', 'Section deleted successfully');
    }
}
