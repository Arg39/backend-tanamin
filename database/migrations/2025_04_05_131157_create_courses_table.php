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
        Schema::create('courses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_category');
            $table->uuid('id_instructor');
            $table->string('title');
            $table->integer('price')->nullable();
            $table->enum('level', ['beginner', 'intermediate', 'advance'])->nullable();
            $table->string('image')->nullable();
            $table->enum('status', ['new', 'edited', 'awaiting_approval', 'published'])->default('new');
            $table->text('detail')->nullable();
            $table->float('avg_rating')->default(0);
            $table->integer('total_reviews')->default(0);

            $table->timestamps();

            $table->foreign('id_category')->references('id')->on('categories')->onDelete('cascade');
            $table->foreign('id_instructor')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('course_attributes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_course');
            $table->enum('type', ['description', 'prerequisite']);
            $table->text('content');
            $table->timestamps();

            $table->foreign('id_course')->references('id')->on('courses')->onDelete('cascade');
        });

        Schema::create('course_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_user');
            $table->uuid('id_course');
            $table->integer('rating');
            $table->text('review')->nullable();
            $table->timestamps();

            $table->foreign('id_course')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('id_user')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_reviews');
        Schema::dropIfExists('course_attributes');
        Schema::dropIfExists('courses');
    }
};
