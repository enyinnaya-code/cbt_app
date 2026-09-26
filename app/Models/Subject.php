<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $fillable = ['name', 'slug', 'code', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function exams()
    {
        return $this->belongsToMany(Exam::class, 'exam_subject')->withPivot('display_name');
    }

    public function topics()
    {
        return $this->hasMany(Topic::class);
    }

    public function papers()
    {
        return $this->hasMany(Paper::class);
    }
}
