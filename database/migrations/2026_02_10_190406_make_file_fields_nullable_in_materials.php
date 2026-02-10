<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->string('file_path')->nullable()->change();
            $table->string('file_name')->nullable()->change();
            $table->integer('file_size')->nullable()->change();
            $table->integer('duration')->nullable()->change();
        });
    }
    
    public function down()
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->string('file_path')->nullable(false)->change();
            $table->string('file_name')->nullable(false)->change();
            $table->integer('file_size')->nullable(false)->change();
            $table->integer('duration')->nullable(false)->change();
        });
    }
};