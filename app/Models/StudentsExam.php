<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentsExam extends Model
{
    use HasFactory;

    protected $table = 'students_exams';

    protected $fillable = [
        'user_id',
        'class_id',
        'test_id',
        'start_time',
        'duration',
        'exhausted_time',
        'score',
        'is_submited',
        'test_total_score',
    ];

    public function test()
    {
        return $this->belongsTo(Test::class, 'test_id');
    }
}
