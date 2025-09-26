<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_name',
        'section_id',
        'added_by',
    ];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'course_user');
    }
}
