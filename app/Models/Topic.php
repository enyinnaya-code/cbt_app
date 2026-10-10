<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    protected $fillable = ['subject_id', 'name', 'slug'];

    protected static function booted(): void
    {
        // Topic names are inside the packs, so renaming or removing one means a new pack for that subject in every exam.
        $refresh = function (Topic $topic) {
            $exams = Paper::published()->where('subject_id', $topic->subject_id)->distinct()->pluck('exam_id');
            foreach ($exams as $examId) {
                app(\App\Services\PackRefresher::class)->mark($examId, $topic->subject_id);
            }
        };
        static::saved($refresh);
        static::deleted($refresh);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }
}
