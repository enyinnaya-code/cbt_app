<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestSubmission extends Model
{
    //

    protected $fillable = [
        'user_id',
        'class_id',
        'test_id',
        'question_id',
        'answer',
        'student_answer',
        'submitted_at',
    ];
}
