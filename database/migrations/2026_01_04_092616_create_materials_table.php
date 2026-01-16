<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::create('materials', function (Blueprint $table) {
    $table->id();

    $table->foreignId('department_id')->constrained()->cascadeOnDelete();
    $table->foreignId('created_by')->constrained('users');

    $table->string('title');
    $table->text('description')->nullable();

    $table->enum('type', ['pdf', 'file', 'video']);
    $table->string('file_path');
    $table->integer('duration')->nullable(); // seconds (for video)

    $table->boolean('is_published')->default(false);

    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
