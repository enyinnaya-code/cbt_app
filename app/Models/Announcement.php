<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Announcement extends Model
{
    //

    use HasFactory;

    protected $fillable = ['content', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

   
    public function users()
    {
        return $this->belongsToMany(User::class, 'announcement_user')
                    ->withPivot('read_at')
                    ->withTimestamps();
    }
}
