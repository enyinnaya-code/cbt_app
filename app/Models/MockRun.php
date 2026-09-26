<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MockRun extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'exam_id', 'subject_ids', 'question_ids', 'groups', 'minutes', 'started_at', 'deadline_at',
        'answers', 'flagged', 'position', 'submitted_at', 'score', 'total', 'result',
    ];

    protected function casts(): array
    {
        return [
            'subject_ids' => 'array',
            'question_ids' => 'array',
            'groups' => 'array',
            'answers' => 'array',
            'flagged' => 'array',
            'result' => 'array',
            'started_at' => 'datetime',
            'deadline_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->deadline_at->isPast();
    }
}
