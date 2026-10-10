<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;


class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_id', 'paper_id', 'topic_id', 'question', 'answer',
        'explanation_en', 'explanation_pcm', 'mark', 'options', 'not_question',
    ];

    public function test()
    {
        return $this->belongsTo(Test::class);
    }

    public function paper()
    {
        return $this->belongsTo(Paper::class);
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }


    public static function boot()
    {
        parent::boot();

        static::deleting(function ($question) {
            // Pictures this question uploaded go with it. Only files inside public/uploads may ever be removed: the
            // address comes from HTML an examiner typed, so it can never be trusted to point where it says.
            preg_match_all('/<img[^>]+src="([^">]+)"/', (string) $question->question, $matches);
            $root = realpath(public_path('uploads'));

            foreach ($matches[1] as $imageUrl) {
                $path = parse_url($imageUrl, PHP_URL_PATH);
                $real = $root && is_string($path) ? realpath(public_path($path)) : false;

                if ($real && str_starts_with($real, $root . DIRECTORY_SEPARATOR) && is_file($real)) {
                    File::delete($real);
                }
            }
        });

        // A change to a question in a live paper changes what students download: rebuild its pack.
        $refresh = function ($question) {
            $paper = $question->paper_id ? Paper::find($question->paper_id) : null;
            if ($paper && $paper->status === Paper::PUBLISHED) {
                app(\App\Services\PackRefresher::class)->mark($paper->exam_id, $paper->subject_id);
            }
        };
        static::saved($refresh);
        static::deleted($refresh);
    }
}
