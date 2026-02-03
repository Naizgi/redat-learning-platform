<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('materials', function (Blueprint $table) {
            // Add file_name column if it doesn't exist
            if (!Schema::hasColumn('materials', 'file_name')) {
                $table->string('file_name')->after('file_path');
            }
            
            // Add file_size column if it doesn't exist
            if (!Schema::hasColumn('materials', 'file_size')) {
                $table->integer('file_size')->nullable()->after('file_name');
            }
            
            // Add missing statistics columns
            if (!Schema::hasColumn('materials', 'views_count')) {
                $table->integer('views_count')->default(0);
            }
            
            if (!Schema::hasColumn('materials', 'download_count')) {
                $table->integer('download_count')->default(0);
            }
            
            // Add missing fields
            if (!Schema::hasColumn('materials', 'difficulty')) {
                $table->string('difficulty')->nullable();
            }
            
            if (!Schema::hasColumn('materials', 'tags')) {
                $table->json('tags')->nullable();
            }
            
            if (!Schema::hasColumn('materials', 'pages')) {
                $table->integer('pages')->nullable();
            }
            
            if (!Schema::hasColumn('materials', 'author')) {
                $table->string('author')->nullable();
            }
            
            if (!Schema::hasColumn('materials', 'average_rating')) {
                $table->decimal('average_rating', 3, 2)->nullable()->default(0);
            }
        });
    }

    public function down()
    {
        // Only drop columns if they exist
        Schema::table('materials', function (Blueprint $table) {
            $columns = [
                'file_name',
                'file_size',
                'views_count',
                'download_count',
                'difficulty',
                'tags',
                'pages',
                'author',
                'average_rating'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('materials', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};