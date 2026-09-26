<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    protected $fillable = ['name', 'slug', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'exam_subject')->withPivot('display_name');
    }

    public function papers()
    {
        return $this->hasMany(Paper::class);
    }
}
