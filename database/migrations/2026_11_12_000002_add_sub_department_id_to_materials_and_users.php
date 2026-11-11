<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            if (!Schema::hasColumn('materials', 'sub_department_id')) {
                $table->foreignId('sub_department_id')
                      ->nullable()
                      ->after('department_id')
                      ->constrained('sub_departments')
                      ->nullOnDelete();
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'sub_department_id')) {
                $table->foreignId('sub_department_id')
                      ->nullable()
                      ->after('department_id')
                      ->constrained('sub_departments')
                      ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            if (Schema::hasColumn('materials', 'sub_department_id')) {
                $table->dropForeign(['sub_department_id']);
                $table->dropColumn('sub_department_id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'sub_department_id')) {
                $table->dropForeign(['sub_department_id']);
                $table->dropColumn('sub_department_id');
            }
        });
    }
};