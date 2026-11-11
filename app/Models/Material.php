<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class Material extends Model
{
    protected $fillable = [
        'department_id',
        'sub_department_id',
        'level_id',              // ← NEW: FK to levels table
        'created_by',
        'title',
        'description',
        'type',
        'file_path',
        'file_name',
        'file_size',
        'duration',
        'is_published',
        'level',                 // legacy tinyint (1-4), kept for backward compat
        'difficulty',
        'tags',
        'pages',
        'author',
        'views_count',
        'download_count',
        'average_rating',
        'youtube_id',
        'youtube_url',
        'thumbnail_url',
    ];

    protected $casts = [
        'is_published'   => 'boolean',
        'views_count'    => 'integer',
        'download_count' => 'integer',
        'average_rating' => 'float',
        'pages'          => 'integer',
        'file_size'      => 'integer',
        'tags'           => 'array',
        'level_id'       => 'integer',   // ← NEW
    ];

    /* ============================================================
     *  RELATIONSHIPS
     * ============================================================ */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }
    public function subDepartment()
    {
        return $this->belongsTo(\App\Models\SubDepartment::class);
    }

    public function level()   // ← NEW
    {
        return $this->belongsTo(Level::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function likes()
    {
        return $this->hasMany(MaterialLike::class);
    }

    public function comments()
    {
        return $this->hasMany(MaterialComment::class);
    }

    public function progress()
    {
        return $this->hasMany(Progress::class);
    }

    /* ============================================================
     *  ACCESSORS
     * ============================================================ */
    public function getLikesCountAttribute()
    {
        return $this->likes()->count();
    }

    public function getCommentsCountAttribute()
    {
        return $this->comments()->count();
    }

    public function getIsLikedAttribute()
    {
        if (!auth()->check()) {
            return false;
        }
        return $this->likes()->where('user_id', auth()->id())->exists();
    }

    /* ============================================================
     *  HELPERS
     * ============================================================ */
    public function safeIncrement($column)
    {
        if (Schema::hasColumn($this->getTable(), $column)) {
            return $this->increment($column);
        }
        return false;
    }

    /* ============================================================
     *  YOUTUBE
     * ============================================================ */
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
            return $this->thumbnail_url
                ?: "https://img.youtube.com/vi/{$this->youtube_id}/maxresdefault.jpg";
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
            return null;
        }
        return null;
    }

    public static function extractYoutubeId($url)
    {
        $patterns = [
            '/^.*(youtu\.be\/|v\/|u\/\w\/|embed\/|watch\?v=|&v=)([^#&?]*).*/',
            '/youtube\.com\/shorts\/([^#&?]+)/',
            '/youtube\.com\/live\/([^#&?]+)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[count($matches) - 1];
            }
        }
        return null;
    }

    public function getYoutubeDurationAttribute()
    {
        return $this->duration;
    }

    /* ============================================================
     *  SCOPES
     * ============================================================ */
    public function scopeYoutube($query)
    {
        return $query->where('type', 'youtube');
    }

    public function scopeUploadedVideo($query)
    {
        return $query->where('type', 'video');
    }

    public function scopeDocument($query)
    {
        return $query->where('type', 'document');
    }

    /**
     * Filter by levels table FK (new).
     */
    public function scopeByLevel($query, $levelId)   // ← NEW
    {
        return $query->where('level_id', $levelId);
    }
}