<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard: levels table must exist
        if (!Schema::hasTable('levels')) {
            return;
        }

        // 1) Add nullable FK
        if (!Schema::hasColumn('materials', 'level_id')) {
            Schema::table('materials', function (Blueprint $table) {
                $table->foreignId('level_id')
                      ->nullable()
                      ->after('department_id')
                      ->constrained('levels')
                      ->nullOnDelete();
            });
        }

        // 2) Backfill: map legacy `materials.level` (1-4) → levels.id (academic rows in order)
        $academic = DB::table('levels')
            ->where('type', 'academic')
            ->orderBy('order')
            ->get()
            ->values();

        if ($academic->count() === 4) {
            for ($legacy = 1; $legacy <= 4; $legacy++) {
                DB::table('materials')
                    ->where('level', $legacy)
                    ->whereNull('level_id')
                    ->update(['level_id' => $academic[$legacy - 1]->id]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('materials', 'level_id')) {
            Schema::table('materials', function (Blueprint $table) {
                $table->dropForeign(['level_id']);
                $table->dropColumn('level_id');
            });
        }
    }
};