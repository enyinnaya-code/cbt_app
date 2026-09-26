<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    protected $fillable = ['name', 'slug', 'is_active', 'sort_order', 'bundle_price', 'mock_format'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'mock_format' => 'array'];
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'exam_subject')->withPivot('display_name', 'price', 'free_questions');
    }

    public function papers()
    {
        return $this->hasMany(Paper::class);
    }
}
