<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Event extends Model
{
    protected $fillable = ['title', 'slug', 'kind', 'description', 'starts_on', 'ends_on', 'location', 'link_url', 'status', 'exam_id'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    /** @return array<string,string> */
    public static function kinds(): array
    {
        return config('testacbt.event_kinds');
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function scopeLive($query)
    {
        return $query->where('status', 'published');
    }

    /** Not over yet: it starts today or later, or is a multi-day event still running. */
    public function scopeUpcoming($query)
    {
        return $query->where(fn ($q) => $q->where('starts_on', '>=', today())->orWhere('ends_on', '>=', today()));
    }

    public function kindLabel(): string
    {
        return self::kinds()[$this->kind] ?? Str::headline($this->kind);
    }

    public function isOver(): bool
    {
        return ($this->ends_on ?? $this->starts_on)->endOfDay()->isPast();
    }

    /** "12 Jan 2027", "12 to 16 Jan 2027" or "28 Jan to 3 Feb 2027". */
    public function dateLabel(): string
    {
        $start = $this->starts_on;
        $end = $this->ends_on;

        if (! $end || $end->equalTo($start)) { return $start->format('j M Y'); }
        if ($start->format('Y-m') === $end->format('Y-m')) { return $start->format('j') . ' to ' . $end->format('j M Y'); }

        return $start->format('j M') . ' to ' . $end->format('j M Y');
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug(Str::limit($title, 80, '')) ?: 'event';
        $slug = $base;
        $n = 2;

        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $n++;
        }

        return $slug;
    }
}
