<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Subscription extends Model
{
    protected $fillable = [
        'user_id', 
        'plan_id',
        'status', 
        'amount', 
        'expires_at',
        'start_date', // Add this
        'end_date',   // Add this
        'auto_renew',
        'created_by_admin',
        'admin_id',
        'notes',
        'updated_by_admin'
    ];

    // Tell Laravel to treat these as datetime
    protected $casts = [
        'expires_at' => 'datetime',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Check if subscription is active
    public function isActive()
    {
        // Use end_date if available, otherwise fall back to expires_at
        $endDate = $this->end_date ?? $this->expires_at;
        
        // Make sure it's a Carbon instance
        $expires = $endDate instanceof Carbon
            ? $endDate
            : Carbon::parse($endDate);

        return $this->status === 'active' && $expires->endOfDay()->gte(now());
    }

    // Relationship with user
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}