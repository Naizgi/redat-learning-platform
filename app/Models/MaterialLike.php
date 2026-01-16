<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialLike extends Model
{
    protected $fillable = ['user_id', 'material_id'];
    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        // If table doesn't exist yet, don't try to query it
        if (!\Schema::hasTable('material_likes')) {
            $this->setTable(null);
        }
    }
    
    public static function count()
    {
        // Return 0 if table doesn't exist
        if (!\Schema::hasTable('material_likes')) {
            return 0;
        }
        
        return parent::count();
    }
}