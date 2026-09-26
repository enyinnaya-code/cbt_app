<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Paper extends Model
{
    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';
    public const ARCHIVED = 'archived';

    protected $fillable = [
        'exam_id', 'subject_id', 'year', 'title', 'status',
        'duration_minutes', 'created_by', 'published_at', 'legacy_test_id',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::PUBLISHED);
    }
}
