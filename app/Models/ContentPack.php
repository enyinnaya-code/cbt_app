<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentPack extends Model
{
    protected $fillable = [
        'exam_id', 'subject_id', 'tier', 'version', 'is_current', 'path', 'size_bytes', 'sha256',
        'content_hash', 'paper_count', 'question_count', 'years', 'built_at',
    ];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'years' => 'array',
            'built_at' => 'datetime',
        ];
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public const FULL = 'full';
    public const FREE = 'free';

    /** The latest pack of each kind. The console and the reports below count only the full ones. */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeTier($query, string $tier)
    {
        return $query->where('tier', $tier);
    }
}
