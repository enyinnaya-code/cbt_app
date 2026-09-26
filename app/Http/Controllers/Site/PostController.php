<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    /** News, exam news and results share one page; scholarships and the blog have their own. */
    public function index(Request $request, string $page = 'news')
    {
        $group = collect(Post::categories())->filter(fn ($c) => $c['path'] === $page)->keys()->all();
        $chosen = in_array($request->query('category'), $group, true) ? $request->query('category') : null;
        $search = trim((string) $request->query('q', ''));

        $posts = Post::live()->whereIn('category', $chosen ? [$chosen] : $group)
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', "%{$search}%")->orWhere('excerpt', 'like', "%{$search}%")))
            ->when($page === 'scholarships',
                fn ($q) => $q->orderByRaw('CASE WHEN deadline IS NULL OR deadline >= ? THEN 0 ELSE 1 END', [today()->toDateString()])->orderByDesc('published_at'),
                fn ($q) => $q->orderByDesc('is_featured')->orderByDesc('published_at'))
            ->paginate(12)->withQueryString();

        $titles = [
            'news' => ['News and updates', 'Exam news, result releases and announcements that matter to your exam.'],
            'scholarships' => ['Scholarships', 'Scholarships and awards you can apply for, with their deadlines.'],
            'blog' => ['Blog', 'Study tips, exam techniques and advice from TestaCBT.'],
        ];

        return view('site.posts.index', [
            'page' => $page,
            'heading' => $titles[$page][0] ?? 'Articles',
            'intro' => $titles[$page][1] ?? '',
            'group' => $group,
            'chosen' => $chosen,
            'search' => $search,
            'posts' => $posts,
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $post = Post::where('slug', $slug)->with('author:id,name')->firstOrFail();

        // Drafts and scheduled posts are visible only to admins, so they can check how it looks before publishing.
        $live = $post->status === Post::PUBLISHED && $post->published_at && $post->published_at->lte(now());
        abort_unless($live || $request->user()?->isAdmin(), 404);

        $related = Post::live()->where('category', $post->category)->where('id', '!=', $post->id)
            ->orderByDesc('published_at')->limit(3)->get();

        return view('site.posts.show', ['post' => $post, 'related' => $related, 'preview' => ! $live]);
    }
}
