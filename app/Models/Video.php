<?php

namespace App\Models;

use App\Services\VideoLink;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $fillable = [
        'title', 'description', 'platform', 'external_id', 'url', 'embed_url', 'thumbnail_url',
        'exam_id', 'is_featured', 'status', 'added_by',
    ];

    protected function casts(): array
    {
        return ['is_featured' => 'boolean'];
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function scopeLive($query)
    {
        return $query->where('status', 'published');
    }

    public function platformLabel(): string
    {
        return VideoLink::LABELS[$this->platform] ?? ucfirst($this->platform);
    }

    /** Badge colour class for this platform (see testacbt.css). */
    public function badgeClass(): string
    {
        return ['youtube' => 'r', 'facebook' => 'b', 'x' => 'neutral', 'tiktok' => 'p'][$this->platform] ?? 'neutral';
    }

    /** Vertical videos need a tall frame. */
    public function isTall(): bool
    {
        return $this->platform === VideoLink::TIKTOK;
    }
}
