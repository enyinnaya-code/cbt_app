<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentPack extends Model
{
    protected $fillable = [
        'exam_id', 'subject_id', 'version', 'is_current', 'path', 'size_bytes', 'sha256',
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

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }
}
