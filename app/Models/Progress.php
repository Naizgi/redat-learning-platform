<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Progress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'material_id',
        'progress',
        'time_spent_seconds',
        'last_page',
        'completed'
    ];

    protected $casts = [
        'completed' => 'boolean',
        'progress' => 'float',
        'time_spent_seconds' => 'integer'
    ];

    /**
     * Get the user that owns the progress.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the material that the progress is for.
     */
    public function material()
    {
        return $this->belongsTo(Material::class);
    }
}