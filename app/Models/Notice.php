<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An announcement the admin switches on; it shows as a closable card at the top of Home on the website and in the app. */
class Notice extends Model
{
    protected $fillable = ['title', 'body', 'tone', 'link_url', 'link_label', 'audience', 'is_active', 'starts_at', 'ends_at'];

    /** At most this many cards at once, newest first, so Home never turns into a wall of notices. */
    public const MAX_SHOWN = 3;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /** @return array<string,string> */
    public static function tones(): array
    {
        return ['info' => 'News', 'success' => 'Good news', 'warning' => 'Important'];
    }

    /** @return array<string,string> */
    public static function audiences(): array
    {
        return ['all' => 'Website and app', 'web' => 'Website only', 'app' => 'App only'];
    }

    /** Switched on and inside its dates. $where is 'web' or 'app'. */
    public function scopeShowing($query, string $where)
    {
        return $query->where('is_active', true)
            ->whereIn('audience', ['all', $where])
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public static function forStudents(string $where)
    {
        return static::showing($where)->latest('id')->limit(self::MAX_SHOWN)->get();
    }

    public function isShowing(): bool
    {
        return $this->is_active && (! $this->starts_at || $this->starts_at->isPast()) && (! $this->ends_at || $this->ends_at->isFuture());
    }

    /** @return array<string,mixed> */
    public function toApp(): array
    {
        return [
            'id' => $this->id, 'title' => $this->title, 'body' => $this->body, 'tone' => $this->tone,
            'link_url' => $this->link_url, 'link_label' => $this->link_label,
        ];
    }
}
