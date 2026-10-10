<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SummernoteController extends Controller
{
    /** Extensions by the type PHP actually detects in the file, never by what the browser says it is called. */
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

    public function uploadImage(Request $request)
    {
        $request->validate(['image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:4096']]);

        $file = $request->file('image');
        // Look inside the file ourselves: what the browser claims it is, and what it is called, mean nothing.
        $detected = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        $extension = self::TYPES[$detected] ?? null;
        abort_unless($extension && @getimagesize($file->getRealPath()) !== false, 422, 'Only JPG, PNG, GIF or WebP pictures can be uploaded.');

        $filename = time() . '-' . Str::random(16) . '.' . $extension;
        $file->move(public_path('uploads'), $filename);

        return response()->json(url('uploads/' . $filename));
    }
}
