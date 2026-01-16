<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLevelToMaterialsTable extends Migration
{
    public function up()
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->default(1)->after('title')->comment('Material level 1-4');
        });
    }

    public function down()
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn('level');
        });
    }
}
