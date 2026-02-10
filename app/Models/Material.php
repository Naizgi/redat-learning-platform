<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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
        'average_rating',
        'youtube_id',        // New field
        'youtube_url',       // New field
        'thumbnail_url'      // New field
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
        return $this->hasMany(Progress::class);
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

    // New: YouTube-related accessors and methods
    public function getIsYoutubeAttribute()
    {
        return $this->type === 'youtube';
    }

    public function getYoutubeEmbedUrlAttribute()
    {
        if ($this->is_youtube && $this->youtube_id) {
            return "https://www.youtube.com/embed/{$this->youtube_id}";
        }
        return null;
    }

    public function getYoutubeThumbnailAttribute()
    {
        if ($this->is_youtube && $this->youtube_id) {
            return $this->thumbnail_url ?: "https://img.youtube.com/vi/{$this->youtube_id}/maxresdefault.jpg";
        }
        return null;
    }

    public function getYoutubeWatchUrlAttribute()
    {
        if ($this->is_youtube && $this->youtube_id) {
            return "https://www.youtube.com/watch?v={$this->youtube_id}";
        }
        return null;
    }

    public function getDisplayUrlAttribute()
    {
        if ($this->is_youtube) {
            return $this->youtube_embed_url;
        } elseif ($this->type === 'video' && $this->file_path) {
            return Storage::url($this->file_path);
        }
        return null;
    }

    public function getDisplayThumbnailAttribute()
    {
        if ($this->is_youtube) {
            return $this->youtube_thumbnail;
        } elseif ($this->type === 'video' && $this->file_path) {
            // You might want to generate thumbnails for uploaded videos
            return null;
        }
        return null;
    }

    // Helper to extract YouTube ID
    public static function extractYoutubeId($url)
    {
        $patterns = [
            '/^.*(youtu\.be\/|v\/|u\/\w\/|embed\/|watch\?v=|&v=)([^#&?]*).*/',
            '/youtube\.com\/shorts\/([^#&?]+)/',
            '/youtube\.com\/live\/([^#&?]+)/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[count($matches) - 1];
            }
        }
        
        return null;
    }

    // Get YouTube video duration (you might want to implement this via API)
    public function getYoutubeDurationAttribute()
    {
        // This would require YouTube API integration
        // For now, return null or implement caching
        return $this->duration;
    }

    // Scope for YouTube videos
    public function scopeYoutube($query)
    {
        return $query->where('type', 'youtube');
    }

    // Scope for uploaded videos
    public function scopeUploadedVideo($query)
    {
        return $query->where('type', 'video');
    }

    // Scope for documents
    public function scopeDocument($query)
    {
        return $query->where('type', 'document');
    }
}