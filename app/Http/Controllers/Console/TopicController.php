<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TopicController extends Controller
{
    public function index(Request $request)
    {
        $subjects = Subject::where('is_active', true)->orderBy('name')->withCount('topics')->get();
        $subject = $subjects->firstWhere('id', (int) $request->query('subject'))
            ?? $subjects->firstWhere('topics_count', '>', 0)
            ?? $subjects->first();

        return view('console.topics', [
            'subjects' => $subjects,
            'subject' => $subject,
            'topics' => $subject ? $subject->topics()->withCount('questions')->orderBy('name')->get() : collect(),
        ]);
    }

    /** One topic per line. Existing names (in any letter case) are skipped, not duplicated. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'names' => ['required', 'string', 'max:5000'],
        ]);

        $existing = Topic::where('subject_id', $data['subject_id'])->pluck('slug')->flip();
        $added = 0;

        foreach (preg_split('/\r\n|\r|\n/', $data['names']) as $line) {
            $name = trim(preg_replace('/\s+/', ' ', $line));
            $slug = Str::slug($name);
            if ($name === '' || $slug === '' || mb_strlen($name) > 120 || $existing->has($slug)) { continue; }

            Topic::create(['subject_id' => $data['subject_id'], 'name' => $name, 'slug' => $slug]);
            $existing->put($slug, true);
            $added++;
        }

        return redirect()->route('console.topics.index', ['subject' => $data['subject_id']])
            ->with($added ? 'success' : 'error', $added ? "Added {$added} " . Str::plural('topic', $added) . '.' : 'Nothing new to add. Those topics already exist.');
    }

    public function update(Request $request, Topic $topic)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $name = trim(preg_replace('/\s+/', ' ', $data['name']));
        $slug = Str::slug($name);

        if ($slug === '' || Topic::where('subject_id', $topic->subject_id)->where('slug', $slug)->where('id', '!=', $topic->id)->exists()) {
            return back()->with('error', 'Another topic in this subject already has that name.');
        }

        $topic->update(['name' => $name, 'slug' => $slug]);

        return back()->with('success', 'Renamed.');
    }

    /** Questions keep working: they just lose their topic label. */
    public function destroy(Topic $topic)
    {
        $tagged = $topic->questions()->count();
        $subject = $topic->subject_id;
        $topic->delete();

        return redirect()->route('console.topics.index', ['subject' => $subject])
            ->with('success', $tagged ? "Deleted. {$tagged} " . Str::plural('question', $tagged) . ' no longer have a topic.' : 'Deleted.');
    }
}
