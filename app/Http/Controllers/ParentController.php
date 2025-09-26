<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;


class ParentController extends Controller
{
    public function index()
    {
        $parents = User::where('user_type', 5)->paginate(20); // Assume user_type 5 is Parent
        return view('manage_parents', compact('parents'));
    }

    public function create()
    {
        return view('add_parent');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'gender' => 'required',
            // Add other rules as needed
        ]);

        User::create([
            'name' => strtoupper($request->name),
            'email' => $request->email,
            'gender' => $request->gender,
            'user_type' => 5, // Parent
            'password' => bcrypt('password'), // Default password
        ]);

        return redirect()->route('parents.index')->with('success', 'Parent added successfully.');
    }
}