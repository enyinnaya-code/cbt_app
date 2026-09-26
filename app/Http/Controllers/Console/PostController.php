<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Post;
use App\Support\HtmlCleaner;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** News, exam news, results, scholarships and blog articles. Admins only (see routes). */
class PostController extends Controller
{
    private const COVER_DIR = 'uploads/posts';

    public function index(Request $request)
    {
        $filters = $request->validate([
            'category' => ['nullable', Rule::in(array_keys(Post::categories()))],
            'status' => ['nullable', 'in:draft,published'],
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        return view('console.posts.index', [
            'posts' => Post::query()
                ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
                ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
                ->when($filters['q'] ?? null, fn ($q, $v) => $q->where('title', 'like', '%' . addcslashes($v, '%_\\') . '%'))
                ->latest('id')->paginate(20)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        $category = $request->query('category');

        return view('console.posts.form', [
            'post' => new Post(['category' => array_key_exists($category, Post::categories()) ? $category : 'news', 'status' => Post::DRAFT]),
            'exams' => Exam::orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $post = new Post($this->attributes($data, $request));
        $post->slug = Post::uniqueSlug($data['title']);
        $post->author_id = $request->user()->id;
        $post->save();

        return redirect()->route('console.posts.edit', $post)->with('success', $this->savedMessage($post));
    }

    public function edit(Post $post)
    {
        return view('console.posts.form', ['post' => $post, 'exams' => Exam::orderBy('sort_order')->get(['id', 'name'])]);
    }

    public function update(Request $request, Post $post)
    {
        $data = $this->validated($request);

        $attributes = $this->attributes($data, $request, $post);
        if ($post->title !== $data['title'] && $post->status !== Post::PUBLISHED) {
            $attributes['slug'] = Post::uniqueSlug($data['title'], $post->id);   // a published link never changes under readers
        }
        $post->update($attributes);

        return redirect()->route('console.posts.edit', $post)->with('success', $this->savedMessage($post));
    }

    public function destroy(Post $post)
    {
        $this->deleteCover($post->cover_path);
        $post->delete();

        return redirect()->route('console.posts.index')->with('success', 'Deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'category' => ['required', Rule::in(array_keys(Post::categories()))],
            'title' => ['required', 'string', 'max:160'],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string', 'max:200000'],
            'status' => ['required', 'in:draft,published'],
            'published_at' => ['nullable', 'date'],
            'is_featured' => ['nullable', 'boolean'],
            'exam_id' => ['nullable', 'exists:exams,id'],
            'deadline' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:160'],
            'link_url' => ['nullable', 'url:http,https', 'max:500'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_cover' => ['nullable', 'boolean'],
        ], [
            'cover.max' => 'The cover picture must be 2 MB or smaller.',
            'link_url.url' => 'The link must start with http:// or https://.',
        ]);
    }

    private function attributes(array $data, Request $request, ?Post $existing = null): array
    {
        $publishedAt = ! empty($data['published_at']) ? \Illuminate\Support\Carbon::parse($data['published_at']) : null;

        // Publishing without a date means "now"; a draft keeps whatever date it had.
        if ($data['status'] === Post::PUBLISHED) {
            $publishedAt ??= $existing?->published_at ?? now();
        }

        $attributes = [
            'category' => $data['category'],
            'title' => trim($data['title']),
            'excerpt' => filled($data['excerpt'] ?? null) ? trim($data['excerpt']) : null,
            'body' => HtmlCleaner::clean($data['body']),
            'status' => $data['status'],
            'published_at' => $publishedAt,
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'exam_id' => $data['exam_id'] ?? null,
            'deadline' => $data['category'] === 'scholarship' ? ($data['deadline'] ?? null) : null,
            'source' => $data['category'] === 'scholarship' ? ($data['source'] ?? null) : null,
            'link_url' => $data['link_url'] ?? null,
        ];

        if ($request->hasFile('cover')) {
            $this->deleteCover($existing?->cover_path);
            $attributes['cover_path'] = $this->storeCover($request->file('cover'));
        } elseif (! empty($data['remove_cover'])) {
            $this->deleteCover($existing?->cover_path);
            $attributes['cover_path'] = null;
        }

        return $attributes;
    }

    private function storeCover(UploadedFile $file): string
    {
        $name = Str::random(24) . '.' . ($file->guessExtension() ?: 'jpg');
        try {
            $file->move(public_path(self::COVER_DIR), $name);
        } catch (\Symfony\Component\HttpFoundation\File\Exception\FileException) {
            // Nearly always the server folder is not writable by the web user. Say so, instead of a server error page.
            throw \Illuminate\Validation\ValidationException::withMessages(['cover' => 'The picture could not be saved because the server folder public/' . self::COVER_DIR . ' is not writable. Ask whoever runs the server to fix its permissions, or save the article without a cover picture.']);
        }

        return self::COVER_DIR . '/' . $name;
    }

    private function deleteCover(?string $path): void
    {
        // Only files this screen created; never anything else that happens to be in public/.
        if ($path && str_starts_with($path, self::COVER_DIR . '/') && is_file(public_path($path))) {
            @unlink(public_path($path));
        }
    }

    private function savedMessage(Post $post): string
    {
        if ($post->status === Post::DRAFT) { return 'Saved as a draft. Students cannot see it yet.'; }

        return $post->published_at->isFuture()
            ? 'Saved. It will go live on ' . $post->published_at->format('j M Y g:i a') . '.'
            : 'Saved and live.';
    }
}
