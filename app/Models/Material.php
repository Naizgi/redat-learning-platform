<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Material extends Model
{
    protected $fillable = [
        'department_id', 
        'created_by', 
        'title', 
        'description',
        'type', 
        'file_path', 
        'file_name', 
        'file_size',
        'duration', 
        'is_published', 
        'level',
        'difficulty',
        'tags',
        'pages',
        'author',
        'views_count',
        'download_count',
        'average_rating'
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'views_count' => 'integer',
        'download_count' => 'integer',
        'average_rating' => 'float',
        'pages' => 'integer',
        'file_size' => 'integer',
        'tags' => 'array'
    ];

    public function department() {
        return $this->belongsTo(Department::class);
    }

    public function createdBy() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function likes() {
        return $this->hasMany(MaterialLike::class);
    }

    public function comments() {
        return $this->hasMany(MaterialComment::class);
    }

    public function progress() {
        return $this->hasMany(MaterialProgress::class);
    }

    // Accessor for likes_count
    public function getLikesCountAttribute()
    {
        return $this->likes()->count();
    }

    // Accessor for comments_count
    public function getCommentsCountAttribute()
    {
        return $this->comments()->count();
    }

    // Check if current user liked this material
    public function getIsLikedAttribute()
    {
        if (!auth()->check()) {
            return false;
        }
        return $this->likes()->where('user_id', auth()->id())->exists();
    }

    // Safe increment method
    public function safeIncrement($column)
    {
        if (Schema::hasColumn($this->getTable(), $column)) {
            return $this->increment($column);
        }
        return false;
    }
}