<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
        'avatar_url',
        'preferred_exams',
        'explanation_language',
        'target_exam',
        'target_exam_date',
    ];

    public const ROLE_ADMIN = 'admin';
    public const ROLE_EXAMINER = 'examiner';
    public const ROLE_STUDENT = 'student';

    /**
     * The legacy school screens still write user_type (1 super admin, 2 admin, 3 teacher, 4 student).
     * Keep `role` in step with it so role middleware works until those screens are retired.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->isDirty('user_type')) {
                $user->role = match ((int) $user->user_type) {
                    1, 2 => self::ROLE_ADMIN,
                    3 => self::ROLE_EXAMINER,
                    default => self::ROLE_STUDENT,
                };
            }
        });
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function canManageQuestions(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN, self::ROLE_EXAMINER);
    }

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
            'preferred_exams' => 'array',
            'target_exam_date' => 'date',
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
