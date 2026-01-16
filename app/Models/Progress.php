<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Progress extends Model
{
    protected $fillable = ['user_id', 'material_id', 'progress', 'completed'];
    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        if (!\Schema::hasTable('progress')) {
            $this->setTable(null);
        }
    }
    
    public static function avg($column)
    {
        if (!\Schema::hasTable('progress')) {
            return 0;
        }
        
        return parent::avg($column);
    }
    
    public static function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        $instance = new static;
        
        if (!\Schema::hasTable('progress')) {
            // Return a fake query builder that returns empty results
            return new class {
                public function count() { return 0; }
                public function get() { return collect(); }
                public function selectRaw($sql) { return $this; }
                public function groupBy($column) { return $this; }
                public function orderByDesc($column) { return $this; }
                public function limit($number) { return $this; }
            };
        }
        
        return parent::where($column, $operator, $value, $boolean);
    }
}