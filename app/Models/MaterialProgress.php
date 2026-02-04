<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class MaterialProgress extends Model
{
    protected $fillable = ['user_id', 'material_id', 'progress', 'completed'];
    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        if (!Schema::hasTable('material_progress')) {
            $this->setTable(null);
        }
    }
    
    public static function avg($column)
    {
        if (!Schema::hasTable('material_progress')) {
            return 0;
        }
        
        return parent::avg($column);
    }
    
    public static function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        $instance = new static;
        
        if (!Schema::hasTable('material_progress')) {
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

    /**
     * Get the user that owns the progress.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the material that the progress belongs to.
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}