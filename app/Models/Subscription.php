<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Subscription extends Model
{
    protected $fillable = ['user_id', 'status', 'amount', 'expires_at'];

    // Tell Laravel to treat 'expires_at' as a datetime
    protected $casts = [
        'expires_at' => 'datetime',
    ];

    // Check if subscription is active
    public function isActive()
    {
        // Make sure expires_at is a Carbon instance
        $expires = $this->expires_at instanceof Carbon
            ? $this->expires_at
            : Carbon::parse($this->expires_at);

        return $this->status === 'active' && $expires->endOfDay()->gte(now());
    }
}
