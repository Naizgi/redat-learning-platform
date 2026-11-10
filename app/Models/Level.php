<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Level extends Model
{
    protected $fillable = [
        'name', 'slug', 'code', 'description', 'icon',
        'color', 'type', 'order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order'     => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($level) {
            if (empty($level->slug)) {
                $level->slug = Str::slug($level->name);
            }
        });
    }

    public function materials()
    {
        return $this->hasMany(Material::class);
    }

    public function scopeActive($q)          { return $q->where('is_active', true); }
    public function scopeOrdered($q)         { return $q->orderBy('order')->orderBy('name'); }
    public function scopeSpecializations($q) { return $q->where('type', 'specialization'); }
    public function scopeAcademic($q)        { return $q->where('type', 'academic'); }
}