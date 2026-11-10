<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard: if a 'teacher' role was ever manually inserted, normalize it
        DB::table('users')->where('role', 'teacher')->update(['role' => 'student']);

        // Additive — safe, no data loss
        DB::statement("
            ALTER TABLE users
            MODIFY COLUMN role ENUM('admin', 'student', 'instructor')
            NOT NULL DEFAULT 'student'
        ");
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'instructor')->update(['role' => 'student']);

        DB::statement("
            ALTER TABLE users
            MODIFY COLUMN role ENUM('admin', 'student')
            NOT NULL DEFAULT 'student'
        ");
    }
};