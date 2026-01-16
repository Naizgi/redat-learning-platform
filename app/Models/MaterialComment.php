<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialComment extends Model
{
    protected $fillable = ['user_id', 'material_id', 'content'];
    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        if (!\Schema::hasTable('material_comments')) {
            $this->setTable(null);
        }
    }
    
    public static function count()
    {
        if (!\Schema::hasTable('material_comments')) {
            return 0;
        }
        
        return parent::count();
    }
}