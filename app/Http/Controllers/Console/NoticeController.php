<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Announcements for students. Admins only (see routes). */
class NoticeController extends Controller
{
    public function index()
    {
        return view('console.notices.index', ['notices' => Notice::latest('id')->get()]);
    }

    public function create()
    {
        return view('console.notices.form', ['notice' => new Notice(['tone' => 'info', 'audience' => 'all', 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $notice = Notice::create($this->validated($request));

        return redirect()->route('console.notices.index')->with('success', $notice->is_active ? "\"{$notice->title}\" is live." : "Saved \"{$notice->title}\" (switched off).");
    }

    public function edit(Notice $notice)
    {
        return view('console.notices.form', ['notice' => $notice]);
    }

    public function update(Request $request, Notice $notice)
    {
        $notice->update($this->validated($request));

        return redirect()->route('console.notices.index')->with('success', 'Saved.');
    }

    public function toggle(Notice $notice)
    {
        $notice->update(['is_active' => ! $notice->is_active]);

        return back()->with('success', $notice->is_active ? "\"{$notice->title}\" is live." : "\"{$notice->title}\" is switched off.");
    }

    public function destroy(Notice $notice)
    {
        $notice->delete();

        return redirect()->route('console.notices.index')->with('success', 'Deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:600'],
            'tone' => ['required', Rule::in(array_keys(Notice::tones()))],
            'audience' => ['required', Rule::in(array_keys(Notice::audiences()))],
            'link_url' => ['nullable', 'url:http,https', 'max:500'],
            'link_label' => ['nullable', 'string', 'max:40'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ], [
            'ends_at.after' => 'The end must be after the start.',
            'link_url.url' => 'The link must start with http:// or https://.',
        ]);

        foreach (['body', 'link_url', 'link_label', 'starts_at', 'ends_at'] as $optional) {
            $data[$optional] = filled($data[$optional] ?? null) ? $data[$optional] : null;
        }
        if (! $data['link_url']) { $data['link_label'] = null; }
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
