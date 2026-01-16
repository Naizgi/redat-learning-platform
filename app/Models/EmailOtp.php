<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailOtp extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'otp',
        'expires_at',
        'verified_at'
    ];

    protected $dates = ['expires_at', 'verified_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
