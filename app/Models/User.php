<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'department_id',
        'role',
        'is_active',
        'email_verified_at',
        'phone',
        'address',
        'bio',
        'avatar',
        'level',
        'preferences',
        'last_login_at',
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
            'is_active' => 'boolean',
            'preferences' => 'array', // This will automatically cast JSON to array
            'last_login_at' => 'datetime',
        ];
    }

    // Relationships
    public function emailOtp()
    {
        return $this->hasOne(EmailOtp::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function progress()
    {
        return $this->hasMany(Progress::class);
    }

    // Helper methods
    public function hasRole($role)
    {
        return $this->role === $role;
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isStudent()
    {
        return $this->role === 'student';
    }

    public function isInstructor()
    {
        return $this->role === 'instructor';
    }

    public function isActive()
    {
        return $this->is_active === true;
    }

    // Accessors
    public function getAvatarUrlAttribute()
    {
        return $this->avatar ? asset('storage/' . $this->avatar) : null;
    }

    public function getPreferencesAttribute($value)
    {
        $defaultPreferences = [
            'email_notifications' => true,
            'dark_mode' => false,
            'language' => 'en'
        ];

        if (empty($value)) {
            return $defaultPreferences;
        }

        return array_merge($defaultPreferences, is_array($value) ? $value : json_decode($value, true));
    }

    public function getFileUrlAttribute()
    {
        return route('materials.stream', ['material' => $this->id]);
    }
    
    public function getDownloadUrlAttribute()
    {
        return route('materials.download', ['material' => $this->id]);
    }
}