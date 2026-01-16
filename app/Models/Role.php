<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    // Disable database table for this model
    protected $table = null;
    
    // Prevent all database operations
    public function save(array $options = []) { return false; }
    public function update(array $attributes = [], array $options = []) { return false; }
    public function delete() { return false; }
    public static function create(array $attributes = []) { return new static; }
    
    // Simple find method
    public static function find($id)
    {
        $role = new static;
        $role->id = $id;
        $role->name = ucfirst($id);
        return $role;
    }
    
    // Simple find by slug/name
    public static function where($column, $value)
    {
        $role = new static;
        $role->id = $value;
        $role->name = ucfirst($value);
        return (new class extends \Illuminate\Database\Eloquent\Builder {
            public function first() {
                return new Role;
            }
        });
    }
}