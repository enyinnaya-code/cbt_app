<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SummernoteController extends Controller
{
    public function uploadImage(Request $request)
    {
        if ($request->hasFile('image')) {
            $file     = $request->file('image');
            $filename = time() . '-' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads'), $filename);
            return response()->json(url('uploads/' . $filename));
        }

        return response()->json(['error' => 'No image uploaded.'], 400);
    }
}
