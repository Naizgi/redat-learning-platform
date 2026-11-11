<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('icon', 20)->nullable();
            $table->string('color', 20)->nullable();
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['department_id', 'slug']);
            $table->index(['department_id', 'is_active', 'order']);
        });

        /*
         * MIGRATE existing "Auto Electric" / "Auto Mechanic" from levels
         * into sub_departments under Automotive (department_id = 1).
         * Levels stay intact — they're used for material.level_id.
         */
        $automotiveId = DB::table('departments')->where('name', 'like', '%Automotive%')->value('id') ?? 1;

        $specializations = DB::table('levels')->where('type', 'specialization')->get();
        $now = now();

        foreach ($specializations as $lvl) {
            // Skip if already exists (idempotent for re-runs)
            $exists = DB::table('sub_departments')
                ->where('department_id', $automotiveId)
                ->where('slug', $lvl->slug)
                ->exists();

            if (!$exists) {
                DB::table('sub_departments')->insert([
                    'department_id' => $automotiveId,
                    'name'          => $lvl->name,
                    'slug'          => $lvl->slug,
                    'icon'          => $lvl->icon,
                    'color'         => $lvl->color,
                    'description'   => $lvl->description,
                    'order'         => $lvl->order ?? 0,
                    'is_active'     => true,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }
        }

        // Fallback: if no sub-department exists, create "General" so existing
        // materials can optionally be mapped without error.
        if (DB::table('sub_departments')->where('department_id', $automotiveId)->count() === 0) {
            DB::table('sub_departments')->insert([
                'department_id' => $automotiveId,
                'name'          => 'General',
                'slug'          => 'general',
                'icon'          => '🚗',
                'description'   => 'General automotive materials',
                'order'         => 0,
                'is_active'     => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_departments');
    }
};