<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Section;
use App\Models\User;



class SchoolClass extends Model
{
    use HasFactory;

    protected $table = 'school_classes'; // If not using default naming

    // Define fillable attributes for mass assignment
    protected $fillable = [
        'name',
        'section_id',
        'added_by',
    ];

    // Relationship with Section
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    // Relationship with User (added_by)
    public function user()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
