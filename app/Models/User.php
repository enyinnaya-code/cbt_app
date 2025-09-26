<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'user_type',
        'phone',
        'added_by',
        'is_active',
        'login_attempts',
        'class_id',
        'gender',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function class()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function announcements()
    {
        return $this->belongsToMany(Announcement::class, 'announcement_user')
            ->withPivot('read_at')
            ->withTimestamps();
    }

    /**
     * Get unread announcements for the user
     */
    public function unreadAnnouncements()
    {
        return $this->announcements()
            ->wherePivot('read_at', null);
    }

    /**
     * Count unread announcements for the user
     */
    public function unreadAnnouncementsCount()
    {
        return $this->unreadAnnouncements()->count();
    }

    /**
     * Mark an announcement as read
     */
    public function markAnnouncementAsRead($announcementId)
    {
        $this->announcements()->updateExistingPivot($announcementId, [
            'read_at' => Carbon::now()
        ]);
    }

    /**
     * Mark all announcements as read
     */
    public function markAllAnnouncementsAsRead()
    {
        $this->announcements()
            ->whereNull('announcement_user.read_at')
            ->update(['announcement_user.read_at' => Carbon::now()]);
    }


    public function sections()
    {
        return $this->belongsToMany(Section::class, 'section_user', 'user_id', 'section_id');
    }

    public function classes()
    {
        return $this->belongsToMany(SchoolClass::class, 'class_user', 'user_id', 'school_class_id');
    }



    public function courses()
    {
        return $this->belongsToMany(Course::class, 'course_user', 'user_id', 'course_id');
    }
}
