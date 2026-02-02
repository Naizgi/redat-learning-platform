<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // Add this line

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens; // Add HasApiTokens here

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'department_id', // Add this
        'role', // Add this
        'is_active', // Add this
        'email_verified_at', // Make sure this is fillable
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
            'is_active' => 'boolean', // Add this cast
        ];
    }

    public function emailOtp()
    {
        return $this->hasOne(EmailOtp::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    // Helper method to check if user has a role
    public function hasRole($role)
    {
        return $this->role === $role;
    }

    // Helper method to check if user is admin
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    // Helper method to check if user is active
    public function isActive()
    {
        return $this->is_active === true;
    }

    public function subscription()
{
    return $this->hasOne(Subscription::class)->latestOfMany();
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