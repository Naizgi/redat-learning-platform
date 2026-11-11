<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SubDepartment extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id', 'name', 'slug', 'icon', 'color',
        'description', 'order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order'     => 'integer',
    ];

    protected $appends = ['display_name'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($s) {
            if (empty($s->slug)) {
                $s->slug = Str::slug($s->name);
            }
        });
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function materials()
    {
        return $this->hasMany(Material::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function getDisplayNameAttribute()
    {
        return $this->icon ? "{$this->icon} {$this->name}" : $this->name;
    }

    public function scopeActive($q)  { return $q->where('is_active', true); }
    public function scopeOrdered($q) { return $q->orderBy('order')->orderBy('name'); }
}