<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Post extends Model
{
    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';

    protected $fillable = [
        'category', 'title', 'slug', 'excerpt', 'body', 'cover_path', 'status', 'is_featured', 'published_at',
        'deadline', 'source', 'link_url', 'exam_id', 'author_id',
    ];

    protected function casts(): array
    {
        return ['is_featured' => 'boolean', 'published_at' => 'datetime', 'deadline' => 'date'];
    }

    /** @return array<string,array{label:string,path:string}> */
    public static function categories(): array
    {
        return config('testacbt.post_categories');
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** Published, and not scheduled for later. */
    public function scopeLive($query)
    {
        return $query->where('status', self::PUBLISHED)->where('published_at', '<=', now());
    }

    public function categoryLabel(): string
    {
        return self::categories()[$this->category]['label'] ?? Str::headline($this->category);
    }

    /** Badge colour class for this category (see testacbt.css). */
    public function badgeClass(): string
    {
        return ['news' => 'b', 'exam-news' => 'g', 'results' => '', 'scholarship' => 'p', 'blog' => 'neutral'][$this->category] ?? 'neutral';
    }

    public function tileClass(): string
    {
        return ['news' => 'c-b', 'exam-news' => 'c-g', 'results' => 'c-a', 'scholarship' => 'c-p', 'blog' => 'c-g'][$this->category] ?? 'c-g';
    }

    public function tileIcon(): string
    {
        return ['news' => 'news', 'exam-news' => 'book', 'results' => 'trophy', 'scholarship' => 'cap', 'blog' => 'edit'][$this->category] ?? 'news';
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? asset($this->cover_path) : null;
    }

    public function isClosed(): bool
    {
        return $this->deadline !== null && $this->deadline->endOfDay()->isPast();
    }

    /** The excerpt an admin wrote, or the start of the article. */
    public function summary(int $limit = 160): string
    {
        return $this->excerpt ?: Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $this->body))), $limit);
    }

    public function readMinutes(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags((string) $this->body)) / 200));
    }

    /** A URL-safe slug that is not used by another post. */
    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug(Str::limit($title, 80, '')) ?: 'post';
        $slug = $base;
        $n = 2;

        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $n++;
        }

        return $slug;
    }
}
