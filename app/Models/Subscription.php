<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',      // active, inactive, canceled, etc.
        'amount',
        'start_date',
        'end_date',    // renamed from expires_at for clarity
    ];

    protected $dates = [
        'start_date',
        'end_date',
    ];

    /**
     * Check if the subscription is active.
     *
     * @return bool
     */
    public function isActive()
    {
        if ($this->status !== 'active') {
            return false;
        }

        $now = Carbon::now();
        return $now->between($this->start_date, $this->end_date->endOfDay());
    }

    /**
     * The user that owns this subscription.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
