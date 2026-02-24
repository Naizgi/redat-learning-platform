<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddYoutubeFieldsToMaterialsTable extends Migration
{
    public function up()
    {
        Schema::table('materials', function (Blueprint $table) {
            // Add new columns
            $table->string('youtube_id')->nullable()->after('file_size');
            $table->text('youtube_url')->nullable()->after('youtube_id');
            $table->text('thumbnail_url')->nullable()->after('youtube_url');
            
            // Update type enum to include 'youtube'
            DB::statement("ALTER TABLE materials MODIFY COLUMN type ENUM('video', 'document', 'youtube') NOT NULL");
        });
    }
    
    public function down()
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['youtube_id', 'youtube_url', 'thumbnail_url']);
            DB::statement("ALTER TABLE materials MODIFY COLUMN type ENUM('video', 'document') NOT NULL");
        });
    }
}