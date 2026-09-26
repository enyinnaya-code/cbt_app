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
            // Match all image src paths from the HTML
            preg_match_all('/<img[^>]+src="([^">]+)"/', $question->question, $matches);

            foreach ($matches[1] as $imageUrl) {
                $imagePath = public_path(parse_url($imageUrl, PHP_URL_PATH));
                if (File::exists($imagePath)) {
                    File::delete($imagePath);
                }
            }
        });
    }
}
