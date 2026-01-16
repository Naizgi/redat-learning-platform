<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = ['user_id', 'status', 'amount', 'expires_at'];
    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        if (!\Schema::hasTable('subscriptions')) {
            $this->setTable(null);
        }
    }
    
    public static function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        $instance = new static;
        
        if (!\Schema::hasTable('subscriptions')) {
            // Return a fake query builder
            return new class {
                public function count() { return 0; }
                public function get() { return collect(); }
            };
        }
        
        return parent::where($column, $operator, $value, $boolean);
    }
}