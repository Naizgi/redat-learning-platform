<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Material extends Model
{
    protected $fillable = [
        'department_id','created_by','title','description',
        'type','file_path','duration','is_published','level'
    ];

    public function department() {
        return $this->belongsTo(Department::class);
    }

    // Add this relationship
    public function createdBy() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function likes() {
        return $this->hasMany(MaterialLike::class);
    }

    public function comments() {
        return $this->hasMany(MaterialComment::class);
    }
}