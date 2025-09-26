<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;


class Question extends Model
{
    use HasFactory;

    protected $fillable = ['test_id', 'question', 'answer', 'mark', 'options', 'not_question'];

    public function test()
    {
        return $this->belongsTo(Test::class);
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
