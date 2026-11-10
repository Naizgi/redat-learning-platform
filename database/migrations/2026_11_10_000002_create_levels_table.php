<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('levels')) {
            return; // idempotent
        }

        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('code', 10)->nullable();
            $table->text('description')->nullable();
            $table->string('icon', 20)->nullable();
            $table->string('color', 20)->nullable();
            $table->enum('type', ['specialization', 'academic'])->default('academic');
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'order']);
        });

        $now = now();
        DB::table('levels')->insert([
            // Specializations (must appear BEFORE Level 1 in filters)
            ['name' => 'Auto Electric', 'slug' => 'auto-electric', 'code' => 'AE', 'description' => 'Electrical systems in automotive vehicles', 'icon' => '⚡', 'color' => '#f59e0b', 'type' => 'specialization', 'order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Auto Mechanic', 'slug' => 'auto-mechanic', 'code' => 'AM', 'description' => 'Mechanical systems in automotive vehicles', 'icon' => '🔧', 'color' => '#3b82f6', 'type' => 'specialization', 'order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],

            // Academic levels (map 1:1 to legacy `materials.level` 1-4)
            ['name' => 'Level 1', 'slug' => 'level-1', 'code' => 'L1', 'description' => 'First year', 'icon' => '📘', 'color' => '#10b981', 'type' => 'academic', 'order' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Level 2', 'slug' => 'level-2', 'code' => 'L2', 'description' => 'Second year', 'icon' => '📗', 'color' => '#06b6d4', 'type' => 'academic', 'order' => 11, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Level 3', 'slug' => 'level-3', 'code' => 'L3', 'description' => 'Third year', 'icon' => '📕', 'color' => '#ef4444', 'type' => 'academic', 'order' => 12, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Level 4', 'slug' => 'level-4', 'code' => 'L4', 'description' => 'Fourth year', 'icon' => '📓', 'color' => '#8b5cf6', 'type' => 'academic', 'order' => 13, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('levels');
    }
};